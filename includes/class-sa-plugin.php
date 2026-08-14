<?php

defined( 'ABSPATH' ) || exit;

final class SA_Plugin {
	private $registration;
	private $profile;
	private $google;
	private $access;
	private $privacy;

	public function __construct() {
		$this->registration = new SA_Registration();
		$this->profile      = new SA_Profile();
		$this->google       = new SA_Google_OAuth();
		$this->access       = new SA_Access_Control();
		$this->privacy      = new SA_Privacy();
	}

	public function run() {
		$this->privacy->hooks();
		$this->access->privacy_hooks();
		$this->registration->hooks();
		$this->profile->hooks();
		$this->access->hooks();

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );

		add_shortcode( 'sabri_auth_login', array( $this, 'login_shortcode' ) );
		add_shortcode( 'sabri_auth_signup', array( $this, 'signup_shortcode' ) );
		add_shortcode( 'sabri_auth_verify_email', array( 'SAUTH_Email_Verification', 'render' ) );
		add_shortcode( 'sabri_auth_risk_challenge', array( 'SAUTH_Login_Risk', 'render' ) );
		add_shortcode( 'sabri_auth_complete_profile', array( $this, 'profile_shortcode' ) );
		add_shortcode( 'sabri_auth_forgot_password', array( $this, 'forgot_shortcode' ) );
		add_shortcode( 'sabri_auth_reset_password', array( $this, 'reset_shortcode' ) );
		add_shortcode( 'sabri_auth_sessions', array( 'SAUTH_Session_Manager', 'render' ) );
		add_shortcode( 'sabri_auth_access_required', array( $this, 'access_shortcode' ) );
		add_shortcode( 'sabri_auth_google_account', array( $this, 'google_account_shortcode' ) );
		add_shortcode( 'sabri_auth_google_verify', array( $this, 'google_verify_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );

		if ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
			return;
		}

		/* Reconcile File 02 only after the exact File 00 runtime/DB/contracts are
		 * ready. Protected settings mutations are registered only after that
		 * reconciliation succeeds, so a degraded dependency cannot mutate auth
		 * provider configuration through admin-post. */
		if ( ! SA_Activator::maybe_upgrade() ) {
			add_action( 'admin_notices', array( $this, 'migration_notice' ) );
			return;
		}
		add_action( 'admin_post_sa_save_auth_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_sauth_save_auth_settings', array( $this, 'save_settings' ) );
		$this->google->hooks();
	}

	public function assets() {
		if ( ! SA_Access_Control::is_file02_page() ) {
			return;
		}
		wp_enqueue_style( 'sauth-authentication', SAUTH_URL . 'assets/css/authentication.css', array(), SAUTH_VERSION );
		wp_enqueue_script( 'sauth-authentication', SAUTH_URL . 'assets/js/authentication.js', array(), SAUTH_VERSION, true );
		wp_localize_script( 'sauth-authentication', 'SabriAuth', array( 'loggedIn' => is_user_logged_in(), 'loginUrl' => SA_Security::page_url( 'login', wp_login_url() ) ) );
	}

	public function login_shortcode() {
		if ( is_user_logged_in() ) {
			return $this->signed_in_card();
		}
		$redirect = isset( $_GET['redirect_to'] ) ? SA_Security::safe_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) ) : home_url( '/' );
		return $this->template(
			'login',
			array(
				'google_ready'   => ! SAUTH_Operations::safe_mode() && SAUTH_Provider_Health::available_for_ui( 'google' ) && SA_Google_OAuth::configured(),
				'password_ready' => SA_Membership_Adapter::available() && SAUTH_Account_Contract::provider_available(),
				'redirect_to'    => $redirect,
				'form_action'     => admin_url( 'admin-post.php' ),
				'forgot_url'      => SA_Security::page_url( 'forgot', wp_lostpassword_url() ),
				'signup_url'      => SA_Security::page_url( 'signup', wp_registration_url() ),
			)
		);
	}

	public function signup_shortcode() {
		if ( is_user_logged_in() ) {
			return $this->signed_in_card();
		}
		$google_token   = isset( $_GET['google_registration'] ) ? sanitize_text_field( wp_unslash( $_GET['google_registration'] ) ) : '';
		$google_context = '' !== $google_token ? SAUTH_Google_Registration::context( $google_token ) : array();
		if ( '' !== $google_token && empty( $google_context ) ) {
			$google_token = '';
		}
		return $this->template(
			'signup',
			array(
				'form_action'             => admin_url( 'admin-post.php' ),
				'login_url'               => SA_Security::page_url( 'login', wp_login_url() ),
				'account_contract_ready'  => ! SAUTH_Operations::safe_mode() && SA_Membership_Adapter::available() && SAUTH_Account_Contract::provider_available(),
				'google_ready'            => SAUTH_Google_Registration::available(),
				'google_registration_url' => SAUTH_Google_Registration::start_url(),
				'google_token'            => $google_token,
				'google_context'          => $google_context,
				'account_types'           => SA_Registration::account_types(),
			)
		);
	}

	public function profile_shortcode() {
		if ( ! is_user_logged_in() ) {
			return $this->template( 'access-required', array() );
		}
		return $this->template( 'complete-profile', array( 'profile_url' => SA_Membership_Adapter::profile_url(), 'verification_url' => SA_Membership_Adapter::verification_url() ) );
	}

	public function forgot_shortcode() {
		return is_user_logged_in() ? $this->signed_in_card() : $this->template( 'forgot-password', array() );
	}

	public function reset_shortcode() {
		if ( is_user_logged_in() ) {
			return $this->signed_in_card();
		}
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';
		return $this->template( 'reset-password', array( 'key' => $key, 'login' => $login ) );
	}

	public function access_shortcode() {
		return $this->template( 'access-required', array() );
	}

	public function google_account_shortcode() {
		if ( ! is_user_logged_in() ) {
			return $this->template( 'access-required', array() );
		}
		$user = wp_get_current_user();
		return $this->template(
			'google-account',
			array(
				'user'         => $user,
				'linked'       => SA_Google_OAuth::explicitly_linked( $user->ID ),
				'google_email' => (string) get_user_meta( $user->ID, '_sa_google_email', true ),
				'linked_at'    => (string) get_user_meta( $user->ID, '_sa_google_linked_at', true ),
				'google_ready' => ! SAUTH_Operations::safe_mode() && SAUTH_Provider_Health::available_for_ui( 'google' ) && SA_Google_OAuth::configured(),
				'eligible'     => SA_Membership_Adapter::can_use_google( $user->ID ),
				'security_url' => SA_Membership_Adapter::security_url(),
				'verify_url'   => SA_Membership_Adapter::verification_url(),
			)
		);
	}

	public function google_verify_shortcode() {
		$token = isset( $_GET['challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['challenge'] ) ) : '';
		return $this->template( 'google-verify', array( 'challenge' => $token, 'data' => SA_Google_OAuth::challenge( $token ) ) );
	}

	private function signed_in_card() {
		$user       = wp_get_current_user();
		$logout_url = wp_logout_url( home_url( '/' ) );
		return '<div class="sa-auth-shell"><div class="sa-auth-card sa-signed-in"><h2>' . esc_html__( 'You are signed in', 'sabri-authentication' ) . '</h2><p>' . esc_html( $user->display_name ) . '</p><a class="sa-primary-button" href="' . esc_url( SA_Membership_Adapter::profile_url() ) . '">Membership Profile</a><a class="sa-secondary-button" href="' . esc_url( SA_Security::page_url( 'sessions' ) ) . '">Active Sessions</a><a class="sa-secondary-button" href="' . esc_url( SA_Security::page_url( 'google_account', SA_Membership_Adapter::profile_url() ) ) . '">Google Account Security</a><a class="sa-text-link" href="' . esc_url( $logout_url ) . '">Log Out</a></div></div>';
	}

	private function template( $name, array $vars ) {
		$path = SAUTH_DIR . 'templates/' . sanitize_file_name( $name ) . '.php';
		if ( ! file_exists( $path ) ) {
			return '';
		}
		extract( $vars, EXTR_SKIP );
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	public function admin_menu() {
		$parent = defined( 'SABRI_SHELL_VERSION' ) ? 'sabri-shell' : 'options-general.php';
		add_submenu_page( $parent, 'Sabri Authentication', 'Authentication', 'manage_options', 'sabri-authentication', array( $this, 'admin_page' ) );
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$counts                 = count_users();
		$dependency_ready       = SA_Membership_Adapter::available();
		$account_contract_ready = SAUTH_Account_Contract::provider_available();
		$legacy_roles           = SA_Membership_Adapter::legacy_role_count();
		include SAUTH_DIR . 'admin/account-settings.php';
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) );
		}
		check_admin_referer( 'sa_save_auth_settings', 'sa_nonce' );
		if ( SAUTH_Operations::safe_mode() ) {
			wp_safe_redirect( add_query_arg( 'error', 'safe_mode_active', self::settings_url() ) );
			exit;
		}
		if ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {
			wp_safe_redirect( add_query_arg( 'error', 'dependency_unavailable', self::settings_url() ) );
			exit;
		}
		$client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
		if ( '' !== $client_id && ! preg_match( '/^[0-9A-Za-z._-]+\.apps\.googleusercontent\.com$/', $client_id ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_client_id', self::settings_url() ) );
			exit;
		}

		$encrypted = (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) );
		if ( ! empty( $_POST['clear_google_client_secret'] ) ) {
			$encrypted = '';
		} elseif ( isset( $_POST['google_client_secret'] ) && '' !== trim( (string) wp_unslash( $_POST['google_client_secret'] ) ) ) {
			$secret = trim( sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) );
			$encrypted = SA_Security::encrypt( $secret );
			$secret = '';
			unset( $_POST['google_client_secret'] );
			if ( '' === $encrypted ) {
				wp_safe_redirect( add_query_arg( 'error', 'encryption_failed', self::settings_url() ) );
				exit;
			}
		}

		$enable = ! empty( $_POST['google_enabled'] );
		if ( $enable && ( SAUTH_Operations::safe_mode() || ! is_ssl() || '' === $client_id || ! SA_Security::current_cipher_ready( $encrypted ) ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'not_ready', self::settings_url() ) );
			exit;
		}

		$keys = array(
			'sauth_google_client_id', 'sa_google_client_id',
			'sauth_google_client_secret', 'sa_google_client_secret',
			'sauth_google_enabled', 'sa_google_enabled',
		);
		$snapshot = array();
		foreach ( $keys as $key ) { $snapshot[ $key ] = get_option( $key, null ); }
		$desired = array(
			'sauth_google_client_id' => $client_id,
			'sa_google_client_id' => $client_id,
			'sauth_google_client_secret' => $encrypted,
			'sa_google_client_secret' => $encrypted,
			'sauth_google_enabled' => $enable ? '1' : '0',
			'sa_google_enabled' => $enable ? '1' : '0',
		);
		$stored_ok = true;
		foreach ( $desired as $key => $value ) {
			if ( '' === $value && false !== strpos( $key, 'client_secret' ) ) { delete_option( $key ); }
			else { update_option( $key, $value, false ); }
			$current = get_option( $key, null );
			if ( '' === $value && false !== strpos( $key, 'client_secret' ) ) {
				$stored_ok = $stored_ok && null === $current;
			} else {
				$stored_ok = $stored_ok && (string) $current === (string) $value;
			}
		}
		if ( ! $stored_ok ) {
			foreach ( $snapshot as $key => $value ) {
				if ( null === $value ) { delete_option( $key ); }
				else { update_option( $key, $value, false ); }
			}
			$rollback_ok = true;
			foreach ( $snapshot as $key => $value ) {
				$current = get_option( $key, null );
				$rollback_ok = $rollback_ok && ( null === $value ? null === $current : (string) $current === (string) $value );
			}
			if ( ! $rollback_ok ) {
				SAUTH_Operations::enter_safe_mode();
				wp_safe_redirect( add_query_arg( 'error', 'settings_rollback_failed', self::settings_url() ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'error', 'settings_store_failed', self::settings_url() ) );
			exit;
		}
		SAUTH_Provider_Health::reset( 'google' );
		$receipt = SA_Security::random_token( 16 );
		$receipt_key = 'sauth_settings_saved_' . get_current_user_id();
		$receipt_hash = hash( 'sha256', $receipt );
		set_transient( $receipt_key, $receipt_hash, 120 );
		if ( '' === $receipt || ! hash_equals( $receipt_hash, (string) get_transient( $receipt_key ) ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'settings_receipt_failed', self::settings_url() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'updated_token', $receipt, self::settings_url() ) );
		exit;
	}

	public function activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'sauth_activation_notice' ) ) {
			return;
		}
		delete_transient( 'sauth_activation_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>Sabri Authentication and Accounts ' . esc_html( SAUTH_VERSION ) . ' activated.</strong> File 00 remains the exclusive identity, membership, account-class, guardian, role, verification and MFA-policy authority. File 02 supplies password, Google-first and passkey authentication orchestration, risk challenge, signed email verification, session controls, event outbox and redacted operations.</p></div>';
	}

	public function migration_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>Sabri Authentication migration is incomplete:</strong> File 02 entered Safe Mode and provider/settings mutation hooks were not registered for this request. Run the guarded repair and verify storage postconditions before resuming authentication changes.</p></div>';
		}
	}

	public function dependency_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>Sabri Authentication is in safe degraded mode:</strong> File 00 — Sabri Membership Core 1.2.43+ with current DB migration, Safe Mode clear, the approved assurance contract and smc.authentication-account 1.1.0 is required. Public reading remains available; registration and protected sign-in actions fail closed.</p></div>';
		}
	}

	private static function settings_url() {
		$base = defined( 'SABRI_SHELL_VERSION' ) ? admin_url( 'admin.php' ) : admin_url( 'options-general.php' );
		return add_query_arg( 'page', 'sabri-authentication', $base );
	}
}
