<?php

defined( 'ABSPATH' ) || exit;

/** CAEP/RISC-style replay-safe security signal intake and emission. */
final class SAUTH_Shared_Signals {
	const CONTRACT_VERSION = '1.0.0';
	const MAX_AGE = 300;
	const RETENTION = 30 * DAY_IN_SECONDS;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
		add_action( 'sauth_shared_signals_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'sauth_shared_signals_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sauth_shared_signals_cleanup' );
		}
	}

	public static function register_rest() {
		register_rest_route( 'sabri-auth/v1', '/security-signals', array(
			'methods'  => 'POST',
			'callback' => array( __CLASS__, 'rest_ingest' ),
			'permission_callback' => array( __CLASS__, 'rest_authorize' ),
		) );
	}

	public static function rest_authorize( $request ) {
		/* External verification is provider-specific. Absence of an approved
		 * verifier is an intentional fail-closed state. */
		return true === apply_filters( 'sauth_shared_signal_authorize_v1', false, $request );
	}

	public static function rest_ingest( $request ) {
		$data = is_object( $request ) && is_callable( array( $request, 'get_json_params' ) ) ? $request->get_json_params() : array();
		$result = self::ingest( is_array( $data ) ? $data : array(), array( 'verified'=>true, 'source'=>'rest' ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return rest_ensure_response( array( 'accepted'=>true, 'event_id'=>$result ) );
	}

	public static function ingest( array $envelope, array $context = array() ) {
		if ( empty( $context['verified'] ) ) { return new WP_Error( 'sauth_signal_unverified', 'Security signal verification is required.' ); }
		$family   = sanitize_key( (string) ( $envelope['family'] ?? '' ) );
		$type     = sanitize_key( (string) ( $envelope['type'] ?? '' ) );
		$event_id = strtolower( sanitize_text_field( (string) ( $envelope['event_id'] ?? '' ) ) );
		$user_id  = absint( $envelope['user_id'] ?? 0 );
		$issued   = absint( $envelope['issued_at'] ?? 0 );
		if ( ! in_array( $family, array( 'caep','risc' ), true )
			|| ! preg_match( '/^[0-9a-f-]{36}$/', $event_id )
			|| ! $user_id
			|| ! self::allowed_type( $family, $type )
			|| ! $issued
			|| abs( time() - $issued ) > self::MAX_AGE ) {
			return new WP_Error( 'sauth_signal_invalid', 'Security signal envelope is invalid or stale.' );
		}
		$claims = self::safe_claims( isset( $envelope['claims'] ) && is_array( $envelope['claims'] ) ? $envelope['claims'] : array() );
		global $wpdb;
		$table = SAUTH_Activator::table( 'shared_signals' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$table} WHERE event_id=%s", $event_id ) );
		if ( '' !== (string) $wpdb->last_error ) { return new WP_Error( 'sauth_signal_store_unavailable', 'Signal storage unavailable.' ); }
		if ( $existing ) { return (string) $existing; }
		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$table,
			array(
				'event_id'=>$event_id, 'family'=>$family, 'signal_type'=>$type, 'user_id'=>$user_id,
				'source'=>sanitize_key( (string) ( $context['source'] ?? 'adapter' ) ),
				'claims_json'=>wp_json_encode( $claims ), 'status'=>'active',
				'issued_at'=>gmdate( 'Y-m-d H:i:s', $issued ), 'created_at'=>$now,
			),
			array( '%s','%s','%s','%d','%s','%s','%s','%s','%s' )
		);
		if ( 1 !== (int) $inserted ) { return new WP_Error( 'sauth_signal_store_failed', 'Signal could not be stored.' ); }
		do_action( 'sauth_shared_security_signal_v1', array( 'event_id'=>$event_id,'family'=>$family,'type'=>$type,'user_id'=>$user_id,'claims'=>$claims ) );
		$contain = in_array( $type, array( 'session_revoked','credential_compromise','account_disabled' ), true );
		if ( $contain ) {
			SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'shared_security_' . $type );
			if ( class_exists( 'SAUTH_Passkey_Runtime' ) && is_callable( array( 'SAUTH_Passkey_Runtime', 'invalidate_user_assurance' ) ) ) { SAUTH_Passkey_Runtime::invalidate_user_assurance( $user_id ); }
		}
		if ( class_exists( 'SAUTH_Security_Orchestrator' ) ) {
			if ( 'credential_compromise' === $type ) { update_user_meta( $user_id, SAUTH_Security_Orchestrator::COMPROMISE_META, array( 'started_at'=>time(), 'expires_at'=>time()+7*DAY_IN_SECONDS, 'reason'=>'risc_credential_compromise' ) ); }
			SAUTH_Security_Orchestrator::timeline_event( $user_id, 'shared_' . $family . '_' . $type, $contain ? 'high' : 'medium', array( 'source'=>sanitize_key( (string) ( $context['source'] ?? 'adapter' ) ) ) );
		}
		return $event_id;
	}

	public static function emit( $family, $type, $user_id, array $claims = array() ) {
		$family = sanitize_key( (string) $family );
		$type = sanitize_key( (string) $type );
		$user_id = absint( $user_id );
		if ( ! $user_id || ! self::allowed_type( $family, $type ) ) { return new WP_Error( 'sauth_signal_invalid', 'Unsupported signal.' ); }
		$event = array(
			'event_id'=>strtolower( wp_generate_uuid4() ), 'family'=>$family, 'type'=>$type,
			'user_id'=>$user_id, 'issued_at'=>time(), 'claims'=>self::safe_claims( $claims ),
		);
		$stored = self::ingest( $event, array( 'verified'=>true, 'source'=>'file02' ) );
		if ( is_wp_error( $stored ) ) { return $stored; }
		do_action( 'sauth_shared_signal_emit_v1', $event );
		return $stored;
	}

	public static function risk_score_for_user( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return 0; }
		$table = SAUTH_Activator::table( 'shared_signals' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT family,signal_type FROM {$table} WHERE user_id=%d AND status='active' AND issued_at>=%s ORDER BY created_at DESC LIMIT 20", $user_id, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ), ARRAY_A );
		if ( ! is_array( $rows ) ) { return 0; }
		$score = 0;
		foreach ( $rows as $row ) {
			$type = (string) ( $row['signal_type'] ?? '' );
			if ( in_array( $type, array( 'credential_compromise','session_revoked','account_purged' ), true ) ) { $score += 35; }
			elseif ( in_array( $type, array( 'risk_level_change','token_claims_change','verification_change' ), true ) ) { $score += 15; }
		}
		return min( 60, $score );
	}

	public static function cleanup() {
		global $wpdb;
		$table = SAUTH_Activator::table( 'shared_signals' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at<%s", gmdate( 'Y-m-d H:i:s', time() - self::RETENTION ) ) );
	}

	private static function allowed_type( $family, $type ) {
		$allowed = array(
			'caep'=>array( 'session_revoked','risk_level_change','token_claims_change','verification_change' ),
			'risc'=>array( 'credential_compromise','account_disabled','account_enabled','account_purged' ),
		);
		return isset( $allowed[ $family ] ) && in_array( $type, $allowed[ $family ], true );
	}

	private static function safe_claims( array $claims ) {
		$out = array();
		foreach ( array_slice( $claims, 0, 20, true ) as $key=>$value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || preg_match( '/password|token|secret|cookie|ip|email|phone|address/', $key ) ) { continue; }
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) { $out[$key]=$value; }
			elseif ( is_string( $value ) ) { $out[$key]=substr( sanitize_text_field( $value ), 0, 200 ); }
		}
		return $out;
	}
}
