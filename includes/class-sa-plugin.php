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

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
		add_action( 'admin_post_sa_save_auth_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );

		if ( ! SA_Membership_Adapter::available() ) {
			add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
			return;
		}

		SA_Activator::maybe_upgrade();
		$this->registration->hooks();
		$this->profile->hooks();
		$this->google->hooks();
		$this->access->hooks();

		add_shortcode( 'sabri_auth_login', array( $this, 'login_shortcode' ) );
		add_shortcode( 'sabri_auth_signup', array( $this, 'signup_shortcode' ) );
		add_shortcode( 'sabri_auth_complete_profile', array( $this, 'profile_shortcode' ) );
		add_shortcode( 'sabri_auth_forgot_password', array( $this, 'forgot_shortcode' ) );
		add_shortcode( 'sabri_auth_access_required', array( $this, 'access_shortcode' ) );
		add_shortcode( 'sabri_auth_google_account', array( $this, 'google_account_shortcode' ) );
		add_shortcode( 'sabri_auth_google_verify', array( $this, 'google_verify_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets() {
		if ( ! SA_Access_Control::is_file02_page() ) {
			return;
		}
		wp_enqueue_style( 'sa-authentication', SA_URL . 'assets/css/authentication.css', array(), SA_VERSION );
		wp_enqueue_script( 'sa-authentication', SA_URL . 'assets/js/authentication.js', array(), SA_VERSION, true );
		wp_localize_script(
			'sa-authentication',
			'SabriAuth',
			array(
				'loggedIn' => is_user_logged_in(),
				'loginUrl' => SA_Membership_Adapter::login_url( self::current_url() ),
			)
		);
	}

	public function login_shortcode() {
		if ( is_user_logged_in() ) {
			return $this->signed_in_card();
		}
		$redirect = isset( $_GET['redirect_to'] ) ? SA_Security::safe_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) ) : home_url( '/' );
		return $this->template(
			'login',
			array(
				'google_ready' => SA_Google_OAuth::configured(),
				'redirect_to'  => $redirect,
				'member_login' => SA_Membership_Adapter::login_url( $redirect ),
			)
		);
	}

	public function signup_shortcode() {
		if ( is_user_logged_in() ) {
			return $this->signed_in_card();
		}
		return $this->template(
			'signup',
			array(
				'register_url' => SA_Membership_Adapter::register_url(),
				'login_url'    => SA_Membership_Adapter::login_url(),
			)
		);
	}

	public function profile_shortcode() {
		if ( ! is_user_logged_in() ) {
			return $this->template( 'access-required', array() );
		}
		return $this->template(
			'complete-profile',
			array(
				'profile_url'      => SA_Membership_Adapter::profile_url(),
				'verification_url' => SA_Membership_Adapter::verification_url(),
			)
		);
	}

	public function forgot_shortcode() {
		return is_user_logged_in() ? $this->signed_in_card() : $this->template( 'forgot-password', array() );
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
				'user'          => $user,
				'linked'        => SA_Google_OAuth::explicitly_linked( $user->ID ),
				'google_email'  => (string) get_user_meta( $user->ID, '_sa_google_email', true ),
				'linked_at'     => (string) get_user_meta( $user->ID, '_sa_google_linked_at', true ),
				'google_ready'  => SA_Google_OAuth::configured(),
				'eligible'      => SA_Membership_Adapter::can_use_google( $user->ID ),
				'security_url'  => SA_Membership_Adapter::security_url(),
				'verify_url'    => SA_Membership_Adapter::verification_url(),
			)
		);
	}

	public function google_verify_shortcode() {
		$token = isset( $_GET['challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['challenge'] ) ) : '';
		$data  = SA_Google_OAuth::challenge( $token );
		return $this->template(
			'google-verify',
			array(
				'challenge' => $token,
				'data'      => $data,
			)
		);
	}

	private function signed_in_card() {
		$user = wp_get_current_user();
		return '<div class="sa-auth-shell"><div class="sa-auth-card sa-signed-in"><h2>' . esc_html__( 'You are signed in', 'sabri-authentication' ) . '</h2><p>' . esc_html( $user->display_name ) . '</p><a class="sa-primary-button" href="' . esc_url( SA_Membership_Adapter::profile_url() ) . '">Membership Profile</a><a class="sa-secondary-button" href="' . esc_url( SA_Security::page_url( 'google_account' ) ) . '">Google Account Security</a><a class="sa-text-link" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Log Out</a></div></div>';
	}

	private function template( $name, array $vars ) {
		$path = SA_DIR . 'templates/' . sanitize_file_name( $name ) . '.php';
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
		$counts           = count_users();
		$dependency_ready = SA_Membership_Adapter::available();
		$legacy_roles     = SA_Membership_Adapter::legacy_role_count();
		include SA_DIR . 'admin/account-settings.php';
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) );
		}
		check_admin_referer( 'sa_save_auth_settings', 'sa_nonce' );

		$client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
		if ( '' !== $client_id && ! preg_match( '/^[0-9A-Za-z._-]+\.apps\.googleusercontent\.com$/', $client_id ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'invalid_client_id', self::settings_url() ) );
			exit;
		}
		update_option( 'sa_google_client_id', $client_id, false );

		if ( ! empty( $_POST['clear_google_client_secret'] ) ) {
			delete_option( 'sa_google_client_secret' );
		} elseif ( isset( $_POST['google_client_secret'] ) && '' !== trim( (string) wp_unslash( $_POST['google_client_secret'] ) ) ) {
			$secret    = trim( sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) );
			$encrypted = SA_Security::encrypt( $secret );
			if ( '' === $encrypted ) {
				wp_safe_redirect( add_query_arg( 'error', 'encryption_failed', self::settings_url() ) );
				exit;
			}
			update_option( 'sa_google_client_secret', $encrypted, false );
		}

		$enable = ! empty( $_POST['google_enabled'] );
		if ( $enable && ( ! SA_Membership_Adapter::available() || ! is_ssl() || '' === $client_id || '' === SA_Security::decrypt( (string) get_option( 'sa_google_client_secret', '' ) ) ) ) {
			update_option( 'sa_google_enabled', '0', false );
			wp_safe_redirect( add_query_arg( 'error', 'not_ready', self::settings_url() ) );
			exit;
		}
		update_option( 'sa_google_enabled', $enable ? '1' : '0', false );

		wp_safe_redirect( add_query_arg( 'updated', '1', self::settings_url() ) );
		exit;
	}

	public function activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'sa_activation_notice' ) ) {
			return;
		}
		delete_transient( 'sa_activation_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>Sabri Authentication activated.</strong> File 00 remains the exclusive membership, role, profile, verification, and two-factor authority. Configure optional Google linking under Authentication.</p></div>';
	}

	public function dependency_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>Sabri Authentication is inactive at runtime:</strong> File 00 — Sabri Membership Core 1.0.1 or later is required and must load correctly. No fallback roles, registration, or profile system has been started.</p></div>';
		}
	}

	private static function settings_url() {
		$base = defined( 'SABRI_SHELL_VERSION' ) ? admin_url( 'admin.php' ) : admin_url( 'options-general.php' );
		return add_query_arg( 'page', 'sabri-authentication', $base );
	}

	private static function current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return SA_Security::safe_redirect( home_url( $uri ) );
	}
}
