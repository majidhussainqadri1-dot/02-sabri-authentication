<?php

defined( 'ABSPATH' ) || exit;

/**
 * Resolves File 00-owned account-completion requirements without redirect loops.
 */
final class SAUTH_Completion_Resolver {
	const LOOP_TTL          = 600;
	const MAX_REPEAT_VISITS = 2;

	/**
	 * @return array<string,mixed>
	 */
	public static function resolve( $user_id, $requested_destination = '', array $state = array() ) {
		$user_id = absint( $user_id );
		$requested_destination = SA_Security::safe_redirect( $requested_destination, home_url( '/' ) );
		if ( ! $user_id ) {
			return self::result( 'deny', 'subject_invalid', array(), '', $requested_destination );
		}

		if ( empty( $state ) ) {
			$state = SAUTH_Account_Contract::completion_state(
				$user_id,
				array( 'purpose' => 'post_authentication_completion' )
			);
		}
		if ( ! is_array( $state ) || 'allow' !== ( $state['result'] ?? '' ) ) {
			return self::result( 'unknown', sanitize_key( (string) ( $state['reason_code'] ?? 'provider_unavailable' ) ), array(), '', $requested_destination );
		}

		$missing = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $state['missing_steps'] ?? array() ) ) ) ) );
		if ( empty( $missing ) ) {
			self::clear_loop_state( $user_id );
			return self::result( 'allow', 'completion_not_required', array(), '', $requested_destination );
		}

		$route = self::canonical_completion_route( (string) ( $state['next_route'] ?? '' ) );
		if ( '' === $route ) {
			return self::result( 'unknown', 'completion_route_invalid', $missing, '', $requested_destination );
		}

		$loop = self::record_visit( $user_id, $route, $missing );
		if ( ! empty( $loop['blocked'] ) ) {
			$fallback = SA_Membership_Adapter::profile_url();
			return self::result( 'deny', 'completion_loop_prevented', $missing, $route, SA_Security::safe_redirect( $fallback, home_url( '/' ) ) );
		}

		return self::result( 'allow', 'completion_required', $missing, $route, $route );
	}

	public static function is_completion_step( $step ) {
		return in_array(
			sanitize_key( (string) $step ),
			array( 'email', 'email_verification', 'phone', 'mobile_verification', 'age', 'guardian', 'profile', 'identity', 'terms', 'privacy', 'two_factor', 'mfa', 'verification' ),
			true
		);
	}

	private static function canonical_completion_route( $route ) {
		$route = wp_validate_redirect( trim( (string) $route ), '' );
		if ( '' === $route ) {
			return '';
		}
		$home_host  = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$route_host = strtolower( (string) wp_parse_url( $route, PHP_URL_HOST ) );
		if ( '' === $home_host || '' === $route_host || ! hash_equals( $home_host, $route_host ) ) {
			return '';
		}

		$blocked = array(
			SA_Security::page_url( 'login' ),
			SA_Security::page_url( 'signup' ),
			SA_Security::page_url( 'forgot' ),
			SA_Security::page_url( 'reset' ),
			SA_Security::page_url( 'risk_challenge' ),
		);
		$route_key = untrailingslashit( strtok( $route, '?' ) );
		foreach ( $blocked as $blocked_url ) {
			if ( '' !== $blocked_url && hash_equals( untrailingslashit( strtok( $blocked_url, '?' ) ), $route_key ) ) {
				return '';
			}
		}
		return $route;
	}

	/**
	 * @return array{blocked:bool,count:int}
	 */
	private static function record_visit( $user_id, $route, array $missing ) {
		$key       = self::loop_key( $user_id );
		$signature = hash_hmac( 'sha256', untrailingslashit( strtok( $route, '?' ) ) . '|' . implode( ',', $missing ), wp_salt( 'nonce' ) );
		$state     = get_transient( $key );
		$state     = is_array( $state ) ? $state : array( 'signature' => '', 'count' => 0 );
		if ( ! empty( $state['signature'] ) && hash_equals( (string) $state['signature'], $signature ) ) {
			$state['count'] = absint( $state['count'] ?? 0 ) + 1;
		} else {
			$state = array( 'signature' => $signature, 'count' => 1, 'first_seen' => time() );
		}
		$state['last_seen'] = time();
		set_transient( $key, $state, self::LOOP_TTL );
		return array( 'blocked' => (int) $state['count'] > self::MAX_REPEAT_VISITS, 'count' => (int) $state['count'] );
	}

	private static function clear_loop_state( $user_id ) {
		delete_transient( self::loop_key( $user_id ) );
	}

	private static function loop_key( $user_id ) {
		return 'sauth_completion_loop_' . substr(
			hash_hmac( 'sha256', absint( $user_id ) . '|' . SA_Security::client_fingerprint(), wp_salt( 'nonce' ) ),
			0,
			40
		);
	}

	private static function result( $result, $reason, array $missing, $owner_route, $destination ) {
		return array(
			'contract'         => 'sauth.account-completion-resolver',
			'contract_version' => '1.0.0',
			'result'           => $result,
			'reason_code'      => sanitize_key( (string) $reason ),
			'missing_steps'    => $missing,
			'owner_route'      => (string) $owner_route,
			'destination'      => (string) $destination,
		);
	}
}
