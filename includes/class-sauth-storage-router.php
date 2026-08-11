<?php

defined( 'ABSPATH' ) || exit;

/**
 * Runtime-safe storage migration router.
 *
 * File 02 historically used `sa_*` table identifiers. Version 1.1.0 creates
 * and owns only canonical `sauth_*` tables, copies legacy rows idempotently,
 * and rewrites any retained compatibility query before it reaches MySQL.
 */
final class SAUTH_Storage_Router {
	private static $initialized = false;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'query', array( __CLASS__, 'canonicalize_query' ), PHP_INT_MAX );
	}

	public static function canonicalize_query( $query ) {
		global $wpdb;
		$query = (string) $query;
		if ( '' === $query || ! isset( $wpdb->prefix ) ) {
			return $query;
		}
		$map = array(
			$wpdb->prefix . 'sa_rate_limits'          => SAUTH_Activator::table( 'rate_limits' ),
			$wpdb->prefix . 'sa_auth_outbox'          => SAUTH_Activator::table( 'auth_outbox' ),
			$wpdb->prefix . 'sa_email_verifications'  => SAUTH_Activator::table( 'email_verifications' ),
			$wpdb->prefix . 'sa_auth_sessions'        => SAUTH_Activator::table( 'auth_sessions' ),
			$wpdb->prefix . 'sa_auth_devices'         => SAUTH_Activator::table( 'auth_devices' ),
			$wpdb->prefix . 'sa_auth_risk_challenges' => SAUTH_Activator::table( 'risk_challenges' ),
			$wpdb->prefix . 'sa_auth_attempts'        => SAUTH_Activator::table( 'auth_attempts' ),
		);
		foreach ( $map as $legacy => $canonical ) {
			if ( '' === $canonical || $legacy === $canonical ) {
				continue;
			}
		/* Do not rewrite the source side of the explicit one-way migration. */
			$migration = false !== strpos( $query, 'INSERT IGNORE INTO ' . $canonical )
				&& false !== strpos( $query, ' FROM ' . $legacy );
			if ( $migration ) {
				continue;
			}
			$query = str_replace( '`' . $legacy . '`', '`' . $canonical . '`', $query );
			$query = str_replace( $legacy, $canonical, $query );
		}
		return $query;
	}

	public static function canonical_tables() {
		return array_values( SAUTH_Activator::required_tables() );
	}

	public static function legacy_tables() {
		return array(
			SAUTH_Activator::legacy_table( 'rate_limits' ),
			SAUTH_Activator::legacy_table( 'auth_outbox' ),
			SAUTH_Activator::legacy_table( 'email_verifications' ),
			SAUTH_Activator::legacy_table( 'auth_sessions' ),
			SAUTH_Activator::legacy_table( 'auth_devices' ),
			SAUTH_Activator::legacy_table( 'risk_challenges' ),
			SAUTH_Activator::legacy_table( 'auth_attempts' ),
		);
	}
}
