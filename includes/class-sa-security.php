<?php

defined( 'ABSPATH' ) || exit;

final class SA_Security {
	public static function client_fingerprint() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';
		return hash_hmac( 'sha256', $ip . '|' . $ua, wp_salt( 'auth' ) );
	}

	private static function bucket_hash( $action, $subject = '' ) {
		$material = sanitize_key( $action ) . '|' . strtolower( trim( (string) $subject ) ) . '|' . self::client_fingerprint();
		return hash_hmac( 'sha256', $material, wp_salt( 'nonce' ) );
	}

	public static function rate_limited( $action, $limit = 6, $window = 900, $subject = '' ) {
		global $wpdb;
		$limit   = max( 1, absint( $limit ) );
		$window  = max( 60, absint( $window ) );
		$bucket  = self::bucket_hash( $action, $subject );
		$table   = $wpdb->prefix . 'sa_rate_limits';
		$now     = gmdate( 'Y-m-d H:i:s' );
		$expires = gmdate( 'Y-m-d H:i:s', time() + $window );
		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (bucket_hash, hits, window_started, expires_at, updated_at)
			 VALUES (%s, 1, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE
			 hits = IF(expires_at <= VALUES(updated_at), 1, hits + 1),
			 window_started = IF(expires_at <= VALUES(updated_at), VALUES(window_started), window_started),
			 expires_at = IF(expires_at <= VALUES(updated_at), VALUES(expires_at), expires_at),
			 updated_at = VALUES(updated_at)",
			$bucket,
			$now,
			$expires,
			$now
		);
		$result = $wpdb->query( $sql );
		if ( false === $result ) {
			$key    = 'sauth_fallback_' . substr( $bucket, 0, 32 );
			$state  = get_transient( $key );
			$now_ts = time();
			if ( ! is_array( $state ) || empty( $state['expires'] ) || (int) $state['expires'] <= $now_ts ) {
				$state = array( 'hits' => 0, 'expires' => $now_ts + $window );
			}
			$state['hits']++;
			$ttl = max( 1, (int) $state['expires'] - $now_ts );
			set_transient( $key, $state, $ttl );
			return $state['hits'] > $limit;
		}
		$hits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM {$table} WHERE bucket_hash = %s", $bucket ) );
		if ( 1 === wp_rand( 1, 100 ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		}
		return $hits > $limit;
	}

	public static function clear_rate_limit( $action, $subject = '' ) {
		global $wpdb;
		$bucket = self::bucket_hash( $action, $subject );
		$wpdb->delete( $wpdb->prefix . 'sa_rate_limits', array( 'bucket_hash' => $bucket ), array( '%s' ) );
		delete_transient( 'sauth_fallback_' . substr( $bucket, 0, 32 ) );
		delete_transient( 'sa_fallback_' . substr( $bucket, 0, 32 ) );
	}

	public static function safe_redirect( $url, $fallback = null ) {
		$fallback = func_num_args() < 2 ? home_url( '/' ) : (string) $fallback;
		return wp_validate_redirect( (string) $url, $fallback );
	}

	private static function encryption_key() {
		$material = defined( 'SA_MASTER_KEY' ) && is_string( SA_MASTER_KEY ) && strlen( SA_MASTER_KEY ) >= 32 ? SA_MASTER_KEY : wp_salt( 'auth' );
		return hash( 'sha256', 'sabri-authentication|v2|' . $material, true );
	}

	public static function encrypt( $plain ) {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		try {
			$iv = random_bytes( 12 );
		} catch ( Exception $exception ) {
			return '';
		}
		$tag = '';
		$out = openssl_encrypt( $plain, 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag, 'sa-google-secret-v2' );
		return false === $out ? '' : 'v2:' . base64_encode( $iv . $tag . $out );
	}

	public static function decrypt( $cipher ) {
		if ( '' === $cipher || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		if ( 0 !== strpos( $cipher, 'v2:' ) ) {
			return self::decrypt_legacy( $cipher );
		}
		$raw = base64_decode( substr( $cipher, 3 ), true );
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return '';
		}
		$iv  = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$out = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag, 'sa-google-secret-v2' );
		return false === $out ? '' : $out;
	}

	private static function decrypt_legacy( $cipher ) {
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

	public static function page_url( $page_key, $fallback = '' ) {
		$canonical = apply_filters( 'sauth_canonical_route_url', '', (string) $page_key );
		if ( is_string( $canonical ) && '' !== $canonical ) {
			return $canonical;
		}
		$pages = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) );
		$page_id = isset( $pages[ $page_key ] ) ? absint( $pages[ $page_key ] ) : 0;
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return get_permalink( $page_id );
		}
		return $fallback ? $fallback : home_url( '/' );
	}

	public static function message_url( $page_key, $type, $message, array $extra = array() ) {
		$args = array_merge(
			$extra,
			array(
				'sa_notice' => sanitize_key( $type ),
				'sa_msg'    => rawurlencode( $message ),
			)
		);
		return add_query_arg( $args, self::page_url( $page_key ) );
	}

	public static function random_token( $bytes = 32 ) {
		try {
			return bin2hex( random_bytes( max( 16, absint( $bytes ) ) ) );
		} catch ( Exception $exception ) {
			return wp_generate_password( 64, false, false );
		}
	}
}
