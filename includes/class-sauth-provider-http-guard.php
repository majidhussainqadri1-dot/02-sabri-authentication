<?php

defined( 'ABSPATH' ) || exit;

/**
 * HTTP boundary for approved authentication providers.
 */
final class SAUTH_Provider_HTTP_Guard {
	private static $started = array();

	public static function init() {
		add_filter( 'pre_http_request', array( __CLASS__, 'guard' ), 10, 3 );
		add_filter( 'http_request_args', array( __CLASS__, 'bound_args' ), 10, 2 );
		add_action( 'http_api_debug', array( __CLASS__, 'observe' ), 10, 5 );
	}

	public static function guard( $preempt, $args, $url ) {
		$provider = self::provider_for_url( $url );
		if ( '' === $provider ) {
			return $preempt;
		}
		if ( SAUTH_Operations::safe_mode() ) {
			return new WP_Error( 'sauth_provider_safe_mode', 'Authentication provider calls are temporarily disabled by Safe Mode.' );
		}
		if ( ! SAUTH_Provider_Health::allow_request( $provider ) ) {
			return new WP_Error( 'sauth_provider_circuit_open', 'Authentication provider is temporarily unavailable. Please retry later.' );
		}
		self::$started[ self::request_key( $provider, $url ) ] = microtime( true );
		return $preempt;
	}

	public static function bound_args( $args, $url ) {
		$provider = self::provider_for_url( $url );
		if ( '' === $provider ) {
			return $args;
		}
		$args = is_array( $args ) ? $args : array();
		$args['timeout'] = min( 15, max( 5, absint( $args['timeout'] ?? 10 ) ) );
		$args['redirection'] = min( 2, max( 0, absint( $args['redirection'] ?? 0 ) ) );
		$args['reject_unsafe_urls'] = true;
		$args['sslverify'] = true;
		return $args;
	}

	public static function observe( $response, $context, $class, $args, $url ) {
		if ( 'response' !== $context ) {
			return;
		}
		$provider = self::provider_for_url( $url );
		if ( '' === $provider ) {
			return;
		}
		$key = self::request_key( $provider, $url );
		$started = isset( self::$started[ $key ] ) ? (float) self::$started[ $key ] : microtime( true );
		unset( self::$started[ $key ] );
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		if ( is_wp_error( $response ) ) {
			SAUTH_Provider_Health::record_failure( $provider, sanitize_key( $response->get_error_code() ), $latency );
			return;
		}
		$code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
		if ( $code >= 200 && $code < 400 ) {
			SAUTH_Provider_Health::record_success( $provider, $latency );
		} else {
			SAUTH_Provider_Health::record_failure( $provider, 'http_' . $code, $latency );
		}
	}

	private static function provider_for_url( $url ) {
		$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
		return in_array( $host, array( 'oauth2.googleapis.com', 'accounts.google.com' ), true ) ? 'google' : '';
	}

	private static function request_key( $provider, $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		return hash( 'sha256', sanitize_key( $provider ) . '|' . $path );
	}
}
