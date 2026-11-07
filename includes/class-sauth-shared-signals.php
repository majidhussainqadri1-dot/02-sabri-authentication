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
		$user_raw = $envelope['user_id'] ?? null;
		// The canonical subject must be a positive integer, not abs(-id).
		$user_id = is_int( $user_raw ) && $user_raw > 0 ? $user_raw : 0;
		$issued_raw = $envelope['issued_at'] ?? null;
		// Preserve signed time semantics: absint(-timestamp) is unsafe.
		$issued = is_int( $issued_raw ) && $issued_raw > 0 ? $issued_raw : 0;
		if ( ! in_array( $family, array( 'caep','risc' ), true )
			|| ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $event_id )
			|| ! $user_id
			|| ! self::allowed_type( $family, $type )
			|| ! $issued
			|| abs( time() - $issued ) > self::MAX_AGE ) {
			return new WP_Error( 'sauth_signal_invalid', 'Security signal envelope is invalid or stale.' );
		}
		$claims = self::safe_claims( isset( $envelope['claims'] ) && is_array( $envelope['claims'] ) ? $envelope['claims'] : array() );
		$contain = in_array( $type, array( 'session_revoked','credential_compromise','account_disabled' ), true );
		global $wpdb;
		$table = SAUTH_Activator::table( 'shared_signals' );
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT event_id,family,signal_type,user_id,claims_json,status FROM {$table} WHERE event_id=%s",
			$event_id
		), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) { return new WP_Error( 'sauth_signal_store_unavailable', 'Signal storage unavailable.' ); }
		if ( is_array( $existing ) ) {
			if ( ! self::same_signal_identity( $existing, $event_id, $family, $type, $user_id, $claims ) ) {
				return new WP_Error( 'sauth_signal_event_id_conflict', 'An event identifier was already bound to another security signal.' );
			}
			return 'active' === (string) ( $existing['status'] ?? '' )
				? $event_id
				: new WP_Error( 'sauth_signal_containment_unverified', 'Verified security containment remains pending operator reconciliation.' );
		}
		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$table,
			array(
				'event_id'=>$event_id, 'family'=>$family, 'signal_type'=>$type, 'user_id'=>$user_id,
				'source'=>sanitize_key( (string) ( $context['source'] ?? 'adapter' ) ),
				'claims_json'=>wp_json_encode( $claims ), 'status'=>$contain ? 'pending_containment' : 'active',
				'issued_at'=>gmdate( 'Y-m-d H:i:s', $issued ), 'created_at'=>$now,
			),
			array( '%s','%s','%s','%d','%s','%s','%s','%s','%s' )
		);
		if ( 1 !== (int) $inserted ) {
			/* A competing verified worker may have won the UNIQUE event_id
			 * insertion. Re-read immutable identity rather than falsely
			 * acknowledging an unrelated subject or signal type. */
			$winner = $wpdb->get_row( $wpdb->prepare(
				"SELECT event_id,family,signal_type,user_id,claims_json,status FROM {$table} WHERE event_id=%s",
				$event_id
			), ARRAY_A );
			if ( is_array( $winner ) ) {
				if ( ! self::same_signal_identity( $winner, $event_id, $family, $type, $user_id, $claims ) ) {
					return new WP_Error( 'sauth_signal_event_id_conflict', 'An event identifier was already bound to another security signal.' );
				}
				return 'active' === (string) ( $winner['status'] ?? '' )
					? $event_id
					: new WP_Error( 'sauth_signal_containment_unverified', 'Verified security containment remains pending operator reconciliation.' );
			}
			return new WP_Error( 'sauth_signal_store_failed', 'Signal could not be stored.' );
		}
		if ( $contain ) {
			/* The event is a once-only pending claim until BOTH containment
			 * effects and the exact persisted status can be verified. An
			 * uncertain owner result is NEVER replayed by a duplicate event. */
			$revoked = true === SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'shared_security_' . $type );
			$assurance_invalidated = true;
			if ( class_exists( 'SAUTH_Passkey_Runtime' ) && is_callable( array( 'SAUTH_Passkey_Runtime', 'invalidate_user_assurance' ) ) ) {
				$assurance_invalidated = true === SAUTH_Passkey_Runtime::invalidate_user_assurance( $user_id );
			}
			if ( ! $revoked || ! $assurance_invalidated ) {
				$wpdb->update( $table, array( 'status'=>'needs_reconciliation' ),
					array( 'event_id'=>$event_id, 'status'=>'pending_containment' ),
					array( '%s' ), array( '%s','%s' ) );
				return new WP_Error( 'sauth_signal_containment_unverified', 'Session or passkey containment could not be verified; operator reconciliation required.' );
			}
			$promoted = $wpdb->update( $table, array( 'status'=>'active' ),
				array( 'event_id'=>$event_id, 'status'=>'pending_containment' ),
				array( '%s' ), array( '%s','%s' ) );
			$observed = $wpdb->get_var( $wpdb->prepare(
				"SELECT status FROM {$table} WHERE event_id=%s", $event_id
			) );
			if ( 1 !== (int) $promoted || 'active' !== (string) $observed || '' !== (string) $wpdb->last_error ) {
				$wpdb->update( $table, array( 'status'=>'needs_reconciliation' ),
					array( 'event_id'=>$event_id, 'status'=>'pending_containment' ),
					array( '%s' ), array( '%s','%s' ) );
				return new WP_Error( 'sauth_signal_containment_unverified', 'Security containment completion is not durably verified; operator reconciliation required.' );
			}
		}
		do_action( 'sauth_shared_security_signal_v1', array( 'event_id'=>$event_id,'family'=>$family,'type'=>$type,'user_id'=>$user_id,'claims'=>$claims ) );
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
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT family,signal_type FROM {$table} WHERE user_id=%d AND status IN ('active','needs_reconciliation','pending_containment') AND issued_at>=%s ORDER BY created_at DESC LIMIT 20", $user_id, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ), ARRAY_A );
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
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status='active' AND created_at<%s", gmdate( 'Y-m-d H:i:s', time() - self::RETENTION ) ) );
	}

	/** Event identity is immutable across retries and concurrent issuers. */
	private static function same_signal_identity( array $row, $event_id, $family, $type, $user_id, array $claims ) {
		$previous = json_decode( (string) ( $row['claims_json'] ?? '' ), true );
		if ( ! is_array( $previous ) ) { return false; }
		ksort( $previous, SORT_STRING );
		ksort( $claims, SORT_STRING );
		return hash_equals( (string) ( $row['event_id'] ?? '' ), (string) $event_id )
			&& hash_equals( (string) ( $row['family'] ?? '' ), (string) $family )
			&& hash_equals( (string) ( $row['signal_type'] ?? '' ), (string) $type )
			&& absint( $row['user_id'] ?? 0 ) === absint( $user_id )
			&& $previous === $claims;
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
		/* CAEP/RISC family, type, source and subject already occupy typed
		 * columns. Never persist arbitrary provider free text: a harmless
		 * claim key can conceal names, email, bearer proof or patient data. */
		$coded_fields = array( 'reason','reason_code','risk_level','method',
			'status','session_state','credential_state','auth_context','source_code' );
		foreach ( array_slice( $claims, 0, 20, true ) as $key=>$value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || preg_match(
				'/password|token|secret|cookie|ip|email|phone|address|contact|identity|subject|user|person|member|account|birth|card|bank|name/',
				$key
			) ) { continue; }
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_string( $value ) && in_array( $key, $coded_fields, true ) ) {
				// Codes only, not descriptive provider prose or arbitrary IDs.
				$code = trim( $value );
				if ( preg_match( '/^[a-z][a-z0-9_-]{0,63}$/D', $code ) ) {
					$out[ $key ] = $code;
				}
			}
		}
		return $out;
	}
}
