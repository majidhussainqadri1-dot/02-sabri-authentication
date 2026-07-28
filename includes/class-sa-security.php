<?php

defined( 'ABSPATH' ) || exit;

final class SA_Security {
	public static function client_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	public static function rate_limited( $action, $limit = 6, $window = 900 ) {
		$key  = 'sa_rate_' . sanitize_key( $action ) . '_' . substr( self::client_ip_hash(), 0, 32 );
		$data = get_transient( $key );
		$data = is_array( $data ) ? $data : array( 'count' => 0 );
		$data['count']++;
		set_transient( $key, $data, absint( $window ) );
		return $data['count'] > absint( $limit );
	}

	public static function safe_redirect( $url, $fallback = '' ) {
		$fallback = $fallback ? $fallback : home_url( '/' );
		return wp_validate_redirect( $url, $fallback );
	}

	public static function encrypt( $plain ) {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = random_bytes( 12 );
		$tag = '';
		$out = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $out ? '' : base64_encode( $iv . $tag . $out );
	}

	public static function decrypt( $cipher ) {
		if ( '' === $cipher || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( $cipher, true );
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$out = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $out ? '' : $out;
	}

	public static function message_url( $page_key, $type, $message ) {
		$pages   = (array) get_option( 'sa_page_map', array() );
		$page_id = isset( $pages[ $page_key ] ) ? absint( $pages[ $page_key ] ) : 0;
		$base    = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		return add_query_arg(
			array(
				'sa_notice' => sanitize_key( $type ),
				'sa_msg'    => rawurlencode( $message ),
			),
			$base
		);
	}
}

