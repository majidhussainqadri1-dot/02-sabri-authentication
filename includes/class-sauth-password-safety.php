<?php

defined( 'ABSPATH' ) || exit;

/** Privacy-preserving password safety checks. */
final class SAUTH_Password_Safety {
	const CONTRACT_VERSION = '1.0.0';
	const HIBP_MAX_RESPONSE_BYTES = 1048576;

	private static $local_blocklist = array(
		'password','password1','password123','12345678','123456789','1234567890','qwerty123',
		'letmein123','admin123','welcome123','iloveyou','pakistan123','sabri123','homeopathy123',
	);

	public static function check( $password ) {
		$password = (string) $password;
		if ( '' === $password ) { return new WP_Error( 'sauth_password_empty', 'Password is required.' ); }
		$normalized = strtolower( trim( $password ) );
		if ( in_array( $normalized, self::$local_blocklist, true ) ) {
			return new WP_Error( 'sauth_password_blocklisted', 'Choose a password that is not commonly used.' );
		}
		$sha1 = strtoupper( sha1( $password ) );
		$prefix = substr( $sha1, 0, 5 );
		$suffix = substr( $sha1, 5 );
		/* Only the 5-hex prefix is exposed to an approved adapter. The raw
		 * password and full SHA-1 are never passed to the provider boundary. */
		$response = apply_filters( 'sauth_breached_password_prefix_lookup_v1', null, $prefix );
		/* An operator may explicitly opt into the public HIBP range service.
		 * No HTTP request is made unless this separate policy is configured. */
		if ( null === $response && defined( 'SAUTH_HIBP_PASSWORD_CHECK_ENABLED' ) && true === SAUTH_HIBP_PASSWORD_CHECK_ENABLED ) {
			$response = self::lookup_hibp_prefix( $prefix );
		}
		$password = ''; $normalized = ''; $sha1 = '';
		if ( null === $response ) { return true; } // Explicitly unconfigured: local-only policy.
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password exposure verification is temporarily unavailable.' );
		}
		$matches = self::normalize_prefix_response( $response );
		if ( is_wp_error( $matches ) ) { return $matches; }
		if ( isset( $matches[ $suffix ] ) && absint( $matches[ $suffix ] ) > 0 ) {
			return new WP_Error( 'sauth_password_breached', 'Choose a password that has not appeared in a known breach corpus.' );
		}
		return true;
	}

	public static function prefix_for_test( $password ) {
		return substr( strtoupper( sha1( (string) $password ) ), 0, 5 );
	}

	/** Exact fixed-origin k-anonymity adapter; never sends raw/full SHA-1. */
	public static function lookup_hibp_prefix( $prefix ) {
		$prefix = (string) $prefix;
		if ( ! preg_match( '/^[A-F0-9]{5}$/D', $prefix ) ) {
			return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Invalid password hash prefix.' );
		}
		$reply = wp_safe_remote_get( 'https://api.pwnedpasswords.com/range/' . $prefix, array(
			'timeout'             => 4,
			'redirection'         => 0,
			'sslverify'          => true,
			'reject_unsafe_urls' => true,
			'limit_response_size'=> self::HIBP_MAX_RESPONSE_BYTES,
			'headers'            => array(
				'Add-Padding' => 'true',
				'User-Agent'  => 'Sabri-Authentication/' . ( defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : self::CONTRACT_VERSION ),
			),
		) );
		if ( is_wp_error( $reply ) || 200 !== (int) wp_remote_retrieve_response_code( $reply ) ) {
			return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range service unavailable.' );
		}
		$body = wp_remote_retrieve_body( $reply );
		if ( ! is_string( $body ) || '' === trim( $body ) || strlen( $body ) >= self::HIBP_MAX_RESPONSE_BYTES ) {
			return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range response unavailable or incomplete.' );
		}
		return $body;
	}

	private static function normalize_prefix_response( $response ) {
		$out = array();
		if ( is_string( $response ) ) {
			if ( '' === trim( $response ) || strlen( $response ) >= self::HIBP_MAX_RESPONSE_BYTES ) {
				return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range response empty or incomplete.' );
			}
			$lines = preg_split( '/\\r?\\n/', $response );
			foreach ( is_array( $lines ) ? $lines : array() as $line ) {
				$line = trim( $line );
				if ( '' === $line ) { continue; }
				if ( ! preg_match( '/^([0-9A-F]{35}):([0-9]+)$/iD', $line, $m ) ) {
					return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range data malformed.' );
				}
				$out[ strtoupper( $m[1] ) ] = absint( $m[2] );
			}
		} elseif ( is_array( $response ) ) {
			if ( count( $response ) > 30000 ) {
				return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range data too large.' );
			}
			foreach ( $response as $suffix=>$count ) {
				$suffix = strtoupper( (string) $suffix );
				if ( ! preg_match( '/^[0-9A-F]{35}$/D', $suffix ) || ! is_numeric( $count ) || (string)$count !== (string)absint( $count ) ) {
					return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range data malformed.' );
				}
				$out[$suffix] = absint( $count );
			}
		} else {
			return new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range data invalid.' );
		}
		return $out ? $out : new WP_Error( 'sauth_password_breach_lookup_unavailable', 'Password range data empty.' );
	}
}
