<?php

defined( 'ABSPATH' ) || exit;

/**
 * Canonical route constitution and compatibility migration.
 */
final class SAUTH_Canonical_Routes {
	const QUERY_VAR = 'sauth_canonical_route';
	const SESSIONS  = 'account_sessions';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 1 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_legacy_route' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'render' ), 5 );
		add_filter( 'sabri_shell_route_manifests', array( __CLASS__, 'shell_manifest' ), 99 );
		add_filter( 'spf_module_manifests', array( __CLASS__, 'foundation_manifest' ), 99 );
		add_filter( 'sauth_canonical_route_url', array( __CLASS__, 'route_url' ), 10, 2 );
		self::migrate_legacy_options();
	}

	public static function register() {
		add_rewrite_rule( '^account/sessions/?$', 'index.php?' . self::QUERY_VAR . '=' . self::SESSIONS, 'top' );
		if ( SAUTH_VERSION !== (string) get_option( 'sauth_rewrite_version', '' ) ) {
			flush_rewrite_rules( false );
			update_option( 'sauth_rewrite_version', SAUTH_VERSION, false );
		}
	}

	public static function query_vars( $vars ) {
		$vars   = is_array( $vars ) ? $vars : array();
		$vars[] = self::QUERY_VAR;
		return array_values( array_unique( $vars ) );
	}

	public static function route_url( $url, $key ) {
		return 'sessions' === (string) $key ? home_url( '/account/sessions/' ) : $url;
	}

	public static function redirect_legacy_route() {
		if ( is_page() && function_exists( 'get_queried_object_id' ) ) {
			$page_map = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) );
			$page_id  = absint( $page_map['sessions'] ?? 0 );
			if ( $page_id && get_queried_object_id() === $page_id ) {
				wp_safe_redirect( home_url( '/account/sessions/' ), 301 );
				exit;
			}
		}
	}

	public static function render() {
		if ( self::SESSIONS !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		status_header( 200 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, noarchive, nosnippet', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'Cross-Origin-Opener-Policy: same-origin', true );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect(
				add_query_arg(
					'redirect_to',
					rawurlencode( home_url( '/account/sessions/' ) ),
					SA_Security::page_url( 'login', wp_login_url() )
				)
			);
			exit;
		}
		get_header();
		echo '<main id="main" class="sauth-canonical-route">' . do_shortcode( '[sabri_auth_sessions]' ) . '</main>';
		get_footer();
		exit;
	}

	public static function shell_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		if ( ! isset( $manifests['file02-authentication'] ) || ! is_array( $manifests['file02-authentication'] ) ) {
			$manifests['file02-authentication'] = array();
		}
		$manifest = $manifests['file02-authentication'];
		$manifest['owner']   = 'File 02';
		$manifest['version'] = SAUTH_VERSION;
		$manifest['layout']  = 'single-column-account';
		$manifest['cache']   = 'private-no-store';
		$routes = isset( $manifest['routes'] ) && is_array( $manifest['routes'] ) ? $manifest['routes'] : array();
		$routes['sessions'] = array(
			'owner'     => 'File 02',
			'route'     => '/account/sessions/',
			'access'    => 'authenticated',
			'index'     => 'noindex',
			'cache'     => 'no-store',
			'layout'    => 'single-column-account',
			'shortcode' => '[sabri_auth_sessions]',
		);
		$manifest['routes'] = $routes;
		$manifests['file02-authentication'] = $manifest;
		return $manifests;
	}

	public static function foundation_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		if ( ! isset( $manifests['file02-authentication'] ) || ! is_array( $manifests['file02-authentication'] ) ) {
			$manifests['file02-authentication'] = array();
		}
		$manifests['file02-authentication']['canonical_repository'] = '02-sabri-authentication-and-accounts';
		$manifests['file02-authentication']['package_folder']       = '02-sabri-authentication';
		$manifests['file02-authentication']['php_prefix']            = 'SAUTH_';
		$manifests['file02-authentication']['version']               = SAUTH_VERSION;
		$manifests['file02-authentication']['canonical_sessions_route'] = '/account/sessions/';
		return $manifests;
	}

	private static function migrate_legacy_options() {
		$map = array(
			'sa_google_enabled'       => 'sauth_google_enabled',
			'sa_google_client_id'     => 'sauth_google_client_id',
			'sa_google_client_secret' => 'sauth_google_client_secret',
			'sa_page_map'             => 'sauth_page_map',
			'sa_version'              => 'sauth_version',
			'sa_db_version'           => 'sauth_db_version',
		);
		foreach ( $map as $legacy => $canonical ) {
			if ( false === get_option( $canonical, false ) ) {
				$value = get_option( $legacy, false );
				if ( false !== $value ) {
					add_option( $canonical, $value, '', false );
				}
			}
		}
		if ( get_transient( 'sa_activation_notice' ) ) {
			set_transient( 'sauth_activation_notice', '1', 120 );
			delete_transient( 'sa_activation_notice' );
		}
		/* Runtime/schema version markers are intentionally not written here.
		 * SAUTH_Activator::repair() publishes them only after storage postconditions. */
	}
}
