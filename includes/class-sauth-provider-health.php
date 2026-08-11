<?php

defined( 'ABSPATH' ) || exit;

/**
 * Privacy-minimized provider circuit breaker and health projection.
 *
 * No provider payload, credential, endpoint query string or user identifier is
 * stored. The class records only bounded operational state needed to prevent a
 * failing provider from amplifying latency and false-success behavior.
 */
final class SAUTH_Provider_Health {
	const FAILURE_THRESHOLD = 4;
	const OPEN_SECONDS      = 120;
	const STATE_TTL         = 7 * DAY_IN_SECONDS;

	private static $providers = array( 'membership', 'email', 'google', 'notifications' );

	public static function init() {
		add_action( 'sauth_provider_health_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'sauth_provider_health_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sauth_provider_health_cleanup' );
		}
	}

	public static function allow_request( $provider ) {
		$state = self::state( $provider );
		return empty( $state['opened_until'] ) || (int) $state['opened_until'] <= time();
	}

	public static function record_success( $provider, $latency_ms = 0 ) {
		$provider = self::provider( $provider );
		if ( '' === $provider ) {
			return false;
		}
		$state = array(
			'provider'             => $provider,
			'status'               => 'healthy',
			'consecutive_failures' => 0,
			'opened_until'         => 0,
			'last_success'         => time(),
			'last_failure'         => 0,
			'last_reason'          => '',
			'latency_ms'           => min( 60000, max( 0, absint( $latency_ms ) ) ),
			'updated_at'           => time(),
		);
		set_transient( self::key( $provider ), $state, self::STATE_TTL );
		return true;
	}

	public static function record_failure( $provider, $reason = 'provider_failure', $latency_ms = 0 ) {
		$provider = self::provider( $provider );
		if ( '' === $provider ) {
			return false;
		}
		$state = self::state( $provider );
		$state['consecutive_failures'] = min( 100, absint( $state['consecutive_failures'] ?? 0 ) + 1 );
		$state['last_failure']         = time();
		$state['last_reason']          = sanitize_key( (string) $reason );
		$state['latency_ms']           = min( 60000, max( 0, absint( $latency_ms ) ) );
		$state['updated_at']           = time();
		if ( $state['consecutive_failures'] >= self::FAILURE_THRESHOLD ) {
			$state['status']       = 'open';
			$state['opened_until'] = time() + self::OPEN_SECONDS;
		} else {
			$state['status'] = 'degraded';
		}
		set_transient( self::key( $provider ), $state, self::STATE_TTL );
		return true;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function state( $provider ) {
		$provider = self::provider( $provider );
		$default  = array(
			'provider'             => $provider,
			'status'               => 'unknown',
			'consecutive_failures' => 0,
			'opened_until'         => 0,
			'last_success'         => 0,
			'last_failure'         => 0,
			'last_reason'          => '',
			'latency_ms'           => 0,
			'updated_at'           => 0,
		);
		if ( '' === $provider ) {
			return $default;
		}
		$stored = get_transient( self::key( $provider ) );
		if ( ! is_array( $stored ) ) {
			return $default;
		}
		$state = array_merge( $default, array_intersect_key( $stored, $default ) );
		if ( 'open' === $state['status'] && (int) $state['opened_until'] <= time() ) {
			$state['status'] = 'half_open';
		}
		return $state;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		$output = array();
		foreach ( self::$providers as $provider ) {
			$output[ $provider ] = self::state( $provider );
		}
		return $output;
	}

	public static function reset( $provider ) {
		$provider = self::provider( $provider );
		return '' !== $provider ? delete_transient( self::key( $provider ) ) : false;
	}

	public static function cleanup() {
		foreach ( self::$providers as $provider ) {
			$state = self::state( $provider );
			if ( ! empty( $state['updated_at'] ) && (int) $state['updated_at'] < time() - self::STATE_TTL ) {
				delete_transient( self::key( $provider ) );
			}
		}
	}

	private static function provider( $provider ) {
		$provider = sanitize_key( (string) $provider );
		return in_array( $provider, self::$providers, true ) ? $provider : '';
	}

	private static function key( $provider ) {
		return 'sauth_provider_health_' . sanitize_key( $provider );
	}
}
