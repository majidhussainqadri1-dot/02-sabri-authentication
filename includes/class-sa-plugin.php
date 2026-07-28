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
		SA_Activator::register_roles();
		$this->registration->hooks();
		$this->profile->hooks();
		$this->google->hooks();
		$this->access->hooks();
		$this->privacy->hooks();

		add_shortcode( 'sabri_auth_login', array( $this, 'login_shortcode' ) );
		add_shortcode( 'sabri_auth_signup', array( $this, 'signup_shortcode' ) );
		add_shortcode( 'sabri_auth_complete_profile', array( $this, 'profile_shortcode' ) );
		add_shortcode( 'sabri_auth_forgot_password', array( $this, 'forgot_shortcode' ) );
		add_shortcode( 'sabri_auth_access_required', array( $this, 'access_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_post_sa_save_auth_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
	}

	public function assets() {
		global $post;
		$shortcodes = array( 'sabri_auth_login', 'sabri_auth_signup', 'sabri_auth_complete_profile', 'sabri_auth_forgot_password', 'sabri_auth_access_required' );
		$load = false;
		if ( $post instanceof WP_Post ) {
			foreach ( $shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) { $load = true; break; }
			}
		}
		if ( ! $load && ! function_exists( 'spf_start_plugin' ) ) { return; }
		wp_enqueue_style( 'sa-authentication', SA_URL . 'assets/css/authentication.css', array(), SA_VERSION );
		wp_enqueue_script( 'sa-authentication', SA_URL . 'assets/js/authentication.js', array(), SA_VERSION, true );
		wp_localize_script( 'sa-authentication', 'SabriAuth', array( 'loggedIn' => is_user_logged_in(), 'loginUrl' => wp_login_url( self::current_url() ) ) );
	}

	public function login_shortcode() {
		if ( is_user_logged_in() ) { return $this->signed_in_card(); }
		return $this->template( 'login', array( 'google_ready' => SA_Google_OAuth::configured(), 'redirect_to' => isset( $_GET['redirect_to'] ) ? SA_Security::safe_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) ) : home_url( '/' ) ) );
	}

	public function signup_shortcode() {
		if ( is_user_logged_in() ) { return $this->signed_in_card(); }
		return $this->template( 'signup', array( 'registration_open' => '1' === get_option( 'sa_allow_registration', '1' ), 'google_ready' => SA_Google_OAuth::configured() ) );
	}

	public function profile_shortcode() {
		if ( ! is_user_logged_in() ) { return $this->template( 'access-required', array() ); }
		return $this->template( 'complete-profile', array( 'user' => wp_get_current_user() ) );
	}

	public function forgot_shortcode() {
		return is_user_logged_in() ? $this->signed_in_card() : $this->template( 'forgot-password', array() );
	}

	public function access_shortcode() {
		return $this->template( 'access-required', array() );
	}

	private function signed_in_card() {
		$user = wp_get_current_user();
		return '<div class="sa-auth-shell"><div class="sa-auth-card sa-signed-in"><h2>' . esc_html__( 'You are signed in', 'sabri-authentication' ) . '</h2><p>' . esc_html( $user->display_name ) . '</p><a class="sa-primary-button" href="' . esc_url( get_edit_profile_url( $user->ID ) ) . '">My Profile</a><a class="sa-text-link" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Log Out</a></div></div>';
	}

	private function template( $name, array $vars ) {
		$path = SA_DIR . 'templates/' . sanitize_file_name( $name ) . '.php';
		if ( ! file_exists( $path ) ) { return ''; }
		extract( $vars, EXTR_SKIP );
		ob_start(); include $path; return (string) ob_get_clean();
	}

	public function admin_menu() {
		$parent = function_exists( 'spf_start_plugin' ) ? 'sabri-platform-foundation' : 'options-general.php';
		add_submenu_page( $parent, 'Sabri Accounts', 'Accounts', 'manage_options', 'sabri-authentication', array( $this, 'admin_page' ) );
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$counts = count_users();
		include SA_DIR . 'admin/account-settings.php';
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) ); }
		check_admin_referer( 'sa_save_auth_settings', 'sa_nonce' );
		update_option( 'sa_allow_registration', empty( $_POST['allow_registration'] ) ? '0' : '1', false );
		update_option( 'sa_google_enabled', empty( $_POST['google_enabled'] ) ? '0' : '1', false );
		$client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
		update_option( 'sa_google_client_id', $client_id, false );
		if ( isset( $_POST['google_client_secret'] ) && '' !== trim( (string) $_POST['google_client_secret'] ) ) {
			$secret = sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) );
			$encrypted = SA_Security::encrypt( $secret );
			if ( $encrypted ) { update_option( 'sa_google_client_secret', $encrypted, false ); }
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-authentication', 'updated' => '1' ), admin_url( function_exists( 'spf_start_plugin' ) ? 'admin.php' : 'options-general.php' ) ) );
		exit;
	}

	public function activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'sa_activation_notice' ) ) { return; }
		delete_transient( 'sa_activation_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>Sabri Authentication activated.</strong> Configure Google credentials under Sabri Platform → Accounts. Email/password registration is available immediately.</p></div>';
	}

	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return SA_Security::safe_redirect( $scheme . $host . $uri );
	}
}
