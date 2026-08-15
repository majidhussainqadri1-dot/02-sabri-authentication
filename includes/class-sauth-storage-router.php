<?php

defined( 'ABSPATH' ) || exit;

/**
 * Runtime-safe storage migration router.
 *
 * File 02 historically used `sa_*` table identifiers. Version 1.1.0 creates
 * and owns only canonical `sauth_*` tables, copies legacy rows idempotently,
 * and rewrites retained compatibility queries only in SQL identifier positions.
 */
final class SAUTH_Storage_Router {
	private static $initialized = false;
	private static $suspension_depth = 0;

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
		if ( self::suspended() || '' === $query || ! isset( $wpdb->prefix ) ) {
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

			/* Rewrite only SQL table-identifier positions. Broad string replacement
			 * can corrupt literal values, JSON or diagnostic text that merely contains
			 * a legacy table name. */
			$pattern = '/\b(FROM|JOIN|UPDATE|INTO|TABLE)\s+`?' . preg_quote( $legacy, '/' ) . '`?/i';
			$query = preg_replace_callback(
				$pattern,
				static function ( $matches ) use ( $canonical ) {
					return strtoupper( (string) $matches[1] ) . ' `' . $canonical . '`';
				},
				$query
			);
		}
		return $query;
	}

	/**
	 * Explicitly suspend compatibility routing around the activator's audited,
	 * one-way legacy copy. Query text is never trusted to declare itself a
	 * migration because attacker-controlled literal values can contain SQL-like
	 * text. The depth counter keeps nested repair calls balanced.
	 */
	public static function suspend() {
		self::$suspension_depth++;
	}

	public static function resume() {
		self::$suspension_depth = max( 0, self::$suspension_depth - 1 );
	}

	public static function suspended() {
		return self::$suspension_depth > 0;
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
