<?php

defined( 'ABSPATH' ) || exit;

/** Privacy-minimized provider circuit breaker and health projection. */
final class SAUTH_Provider_Health {
	const FAILURE_THRESHOLD = 4;
	const OPEN_SECONDS      = 120;
	const HALF_OPEN_LEASE   = 30;
	const STATE_TTL         = 7 * DAY_IN_SECONDS;
	private static $providers = array( 'membership', 'email', 'google', 'notifications' );

	public static function init() {
		add_action( 'sauth_provider_health_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'sauth_provider_health_cleanup' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sauth_provider_health_cleanup' ); }
	}

	/**
	 * Runtime request gate. Once an open circuit cools down, exactly one active
	 * mutation/provider request receives the half-open probe lease. Passive UI
	 * rendering never consumes the lease and simply sees the provider unavailable.
	 */
	public static function allow_request( $provider ) {
		$provider = self::provider( $provider );
		if ( '' === $provider ) { return false; }
		$state = self::state( $provider );
		if ( 'open' === $state['status'] && (int) $state['opened_until'] > time() ) { return false; }
		if ( 'half_open' === $state['status'] ) {
			if ( ! self::active_provider_request() ) { return false; }
			return self::claim_probe( $provider );
		}
		return true;
	}

	/**
	 * Non-mutating projection for an interactive provider flow. A cooled-down
	 * circuit is visible again in half-open state, but only the actual outbound
	 * HTTP request may claim the single probe lease via allow_request().
	 */
	public static function available_for_ui( $provider ) {
		$status = (string) self::state( $provider )['status'];
		return 'open' !== $status;
	}

	public static function record_success( $provider, $latency_ms = 0 ) {
		$provider = self::provider( $provider );
		if ( '' === $provider ) { return false; }
		self::release_probe( $provider );
		$state = array( 'provider' => $provider, 'status' => 'healthy', 'consecutive_failures' => 0, 'opened_until' => 0, 'last_success' => time(), 'last_failure' => 0, 'last_reason' => '', 'latency_ms' => min( 60000, max( 0, absint( $latency_ms ) ) ), 'updated_at' => time() );
		return self::store_state( $provider, $state );
	}

	public static function record_failure( $provider, $reason = 'provider_failure', $latency_ms = 0 ) {
		$provider = self::provider( $provider );
		if ( '' === $provider ) { return false; }
		self::release_probe( $provider );
		$lock = self::claim_lock( $provider );
		if ( '' === $lock ) {
			/* Conservative containment on concurrent failure accounting: briefly open
			 * rather than lose a failure and create a thundering herd. */
			$state = self::state( $provider );
			$state['status'] = 'open'; $state['opened_until'] = time() + min( 30, self::OPEN_SECONDS ); $state['last_failure'] = time(); $state['last_reason'] = sanitize_key( (string) $reason ); $state['updated_at'] = time();
			return self::store_state( $provider, $state );
		}
		try {
			$state = self::state( $provider );
			$state['consecutive_failures'] = min( 100, absint( $state['consecutive_failures'] ?? 0 ) + 1 );
			$state['last_failure'] = time();
			$state['last_reason'] = sanitize_key( (string) $reason );
			$state['latency_ms'] = min( 60000, max( 0, absint( $latency_ms ) ) );
			$state['updated_at'] = time();
			if ( $state['consecutive_failures'] >= self::FAILURE_THRESHOLD ) { $state['status'] = 'open'; $state['opened_until'] = time() + self::OPEN_SECONDS; }
			else { $state['status'] = 'degraded'; $state['opened_until'] = 0; }
			return self::store_state( $provider, $state );
		} finally { self::release_lock( $provider, $lock ); }
	}

	public static function state( $provider ) {
		$provider = self::provider( $provider );
		$default = array( 'provider' => $provider, 'status' => 'unknown', 'consecutive_failures' => 0, 'opened_until' => 0, 'last_success' => 0, 'last_failure' => 0, 'last_reason' => '', 'latency_ms' => 0, 'updated_at' => 0 );
		if ( '' === $provider ) { return $default; }
		$stored = get_transient( self::key( $provider ) );
		if ( ! is_array( $stored ) ) { return $default; }
		$state = array_merge( $default, array_intersect_key( $stored, $default ) );
		if ( 'open' === $state['status'] && (int) $state['opened_until'] <= time() ) { $state['status'] = 'half_open'; }
		return $state;
	}

	public static function all() { $out = array(); foreach ( self::$providers as $provider ) { $out[ $provider ] = self::state( $provider ); } return $out; }

	public static function reset( $provider ) {
		$provider = self::provider( $provider );
		if ( '' === $provider ) { return false; }
		self::release_probe( $provider, true ); delete_option( self::lock_key( $provider ) ); delete_transient( self::key( $provider ) ); return true;
	}

	public static function cleanup() {
		foreach ( self::$providers as $provider ) {
			$state = self::state( $provider );
			if ( ! empty( $state['updated_at'] ) && (int) $state['updated_at'] < time() - self::STATE_TTL ) { delete_transient( self::key( $provider ) ); }
			self::release_probe( $provider, false, true );
			$current = get_option( self::lock_key( $provider ), array() );
			if ( is_array( $current ) && absint( $current['expires'] ?? 0 ) < time() ) { delete_option( self::lock_key( $provider ) ); }
		}
	}

	private static function store_state( $provider, array $state ) {
		set_transient( self::key( $provider ), $state, self::STATE_TTL );
		return get_transient( self::key( $provider ) ) === $state;
	}

	private static function claim_probe( $provider ) {
		$key = self::probe_key( $provider ); $token = SA_Security::random_token( 16 ); if ( '' === $token ) { return false; }
		$value = array( 'token' => $token, 'expires' => time() + self::HALF_OPEN_LEASE );
		if ( add_option( $key, $value, '', false ) ) { return true; }
		$current = get_option( $key, array() );
		if ( is_array( $current ) && absint( $current['expires'] ?? 0 ) < time() ) { delete_option( $key ); return add_option( $key, $value, '', false ); }
		return false;
	}

	private static function release_probe( $provider, $force = false, $expired_only = false ) {
		$key = self::probe_key( $provider ); $current = get_option( $key, array() );
		if ( $force || ( is_array( $current ) && ( ! $expired_only || absint( $current['expires'] ?? 0 ) < time() ) ) ) { delete_option( $key ); }
	}

	private static function claim_lock( $provider ) {
		$key = self::lock_key( $provider ); $token = SA_Security::random_token( 12 ); if ( '' === $token ) { return ''; }
		$value = array( 'token' => $token, 'expires' => time() + 5 );
		if ( add_option( $key, $value, '', false ) ) { return $token; }
		$current = get_option( $key, array() );
		if ( is_array( $current ) && absint( $current['expires'] ?? 0 ) < time() ) { delete_option( $key ); if ( add_option( $key, $value, '', false ) ) { return $token; } }
		return '';
	}
	private static function release_lock( $provider, $token ) { $key = self::lock_key( $provider ); $current = get_option( $key, array() ); if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) { delete_option( $key ); } }
	private static function active_provider_request() { if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) { return true; } $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; return '' !== $action && ( 0 === strpos( $action, 'sa_' ) || 0 === strpos( $action, 'sauth_' ) ); }
	private static function provider( $provider ) { $provider = sanitize_key( (string) $provider ); return in_array( $provider, self::$providers, true ) ? $provider : ''; }
	private static function key( $provider ) { return 'sauth_provider_health_' . sanitize_key( $provider ); }
	private static function probe_key( $provider ) { return 'sauth_provider_probe_' . sanitize_key( $provider ); }
	private static function lock_key( $provider ) { return 'sauth_provider_health_lock_' . sanitize_key( $provider ); }
}
