<?php

defined( 'ABSPATH' ) || exit;

/** Privacy-preserving password safety checks. */
final class SAUTH_Password_Safety {
	const CONTRACT_VERSION = '1.0.0';

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
		$password = ''; $normalized = ''; $sha1 = '';
		if ( null === $response ) { return true; } // Adapter optional; local policy still applies.
		$matches = self::normalize_prefix_response( $response );
		if ( isset( $matches[ $suffix ] ) && absint( $matches[ $suffix ] ) > 0 ) {
			return new WP_Error( 'sauth_password_breached', 'Choose a password that has not appeared in a known breach corpus.' );
		}
		return true;
	}

	public static function prefix_for_test( $password ) {
		return substr( strtoupper( sha1( (string) $password ) ), 0, 5 );
	}

	private static function normalize_prefix_response( $response ) {
		$out = array();
		if ( is_string( $response ) ) {
			$lines = preg_split( '/\r?\n/', $response );
			foreach ( is_array( $lines ) ? $lines : array() as $line ) {
				if ( preg_match( '/^([0-9A-F]{35}):([0-9]+)$/i', trim( $line ), $m ) ) { $out[ strtoupper( $m[1] ) ] = absint( $m[2] ); }
			}
		} elseif ( is_array( $response ) ) {
			foreach ( $response as $suffix=>$count ) {
				$suffix = strtoupper( (string) $suffix );
				if ( preg_match( '/^[0-9A-F]{35}$/', $suffix ) ) { $out[$suffix]=absint($count); }
			}
		}
		return $out;
	}
}
