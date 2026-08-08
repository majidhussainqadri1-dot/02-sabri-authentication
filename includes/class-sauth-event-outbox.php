<?php

defined( 'ABSPATH' ) || exit;

/**
 * Reliable, privacy-minimized authentication event outbox.
 *
 * Events are past-tense facts. They never grant authorization and contain no
 * password, reset token, OAuth token, TOTP, recovery code, passkey credential
 * identifier or raw session token.
 */
final class SAUTH_Event_Outbox {
	const SCHEMA_VERSION = '1.0.0';
	const CRON_HOOK      = 'sauth_dispatch_auth_outbox';
	const BATCH_SIZE     = 25;
	const MAX_ATTEMPTS   = 8;

	private static $allowed_events = array(
		'AccountAuthenticationSucceeded.v1',
		'AccountAuthenticationFailed.v1',
		'EmailVerified.v1',
		'PasswordResetCompleted.v1',
		'AuthSessionRevoked.v1',
		'GoogleAccountLinked.v1',
		'GoogleAccountUnlinked.v1',
		'PasskeyRegistered.v1',
		'PasskeyAuthenticated.v1',
		'PasskeyRevoked.v1',
	);

	private static $sensitive_key_fragments = array(
		'password',
		'passwd',
		'secret',
		'token',
		'totp',
		'recovery_code',
		'second_factor',
		'authorization_code',
		'cookie',
		'session_verifier',
		'credential',
	);

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'dispatch_due' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function event_names() {
		return self::$allowed_events;
	}

	public static function build_envelope( $event_name, $actor_user_id, $subject_user_id, array $payload = array(), $privacy_class = 'restricted', $trace_id = '' ) {
		$event_name = (string) $event_name;
		if ( ! in_array( $event_name, self::$allowed_events, true ) ) {
			return new WP_Error( 'sauth_event_name_invalid', 'Unsupported authentication event.' );
		}
		$privacy_class = sanitize_key( (string) $privacy_class );
		if ( ! in_array( $privacy_class, array( 'public', 'internal', 'restricted', 'security' ), true ) ) {
			$privacy_class = 'restricted';
		}
		return array(
			'event_id'         => self::uuid(),
			'event_name'       => $event_name,
			'schema_version'   => self::SCHEMA_VERSION,
			'producer'         => 'file-02-authentication',
			'producer_version' => defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : '',
			'actor_user_id'    => absint( $actor_user_id ),
			'subject_user_id'  => absint( $subject_user_id ),
			'privacy_class'    => $privacy_class,
			'trace_id'         => self::trace_id( $trace_id ),
			'occurred_at'      => gmdate( 'c' ),
			'payload'          => self::sanitize_payload( $payload ),
		);
	}

	public static function emit( $event_name, $actor_user_id, $subject_user_id, array $payload = array(), $privacy_class = 'restricted', $trace_id = '' ) {
		global $wpdb;
		$event = self::build_envelope( $event_name, $actor_user_id, $subject_user_id, $payload, $privacy_class, $trace_id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$payload_json = wp_json_encode( $event['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $payload_json ) {
			return new WP_Error( 'sauth_event_encoding_failed', 'Authentication event could not be encoded.' );
		}
		$table = SAUTH_Activator::table( 'auth_outbox' );
		$stored = $wpdb->insert(
			$table,
			array(
				'event_id'        => $event['event_id'],
				'event_name'      => $event['event_name'],
				'schema_version'  => $event['schema_version'],
				'privacy_class'   => $event['privacy_class'],
				'actor_user_id'   => $event['actor_user_id'],
				'subject_user_id' => $event['subject_user_id'],
				'trace_id'        => $event['trace_id'],
				'payload_json'    => $payload_json,
				'status'          => 'pending',
				'attempts'        => 0,
				'available_at'    => current_time( 'mysql', true ),
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $stored ) {
			return new WP_Error( 'sauth_event_persist_failed', 'Authentication event could not be persisted.' );
		}
		do_action( 'sauth_event_recorded', $event );
		return (string) $event['event_id'];
	}

	public static function dispatch_due() {
		global $wpdb;
		$table = SAUTH_Activator::table( 'auth_outbox' );
		$now   = current_time( 'mysql', true );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status IN ('pending','retry') AND attempts < %d AND available_at <= %s ORDER BY id ASC LIMIT %d",
				self::MAX_ATTEMPTS,
				$now,
				self::BATCH_SIZE
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return;
		}
		foreach ( $rows as $row ) {
			self::dispatch_row( $row );
		}
	}

	private static function dispatch_row( array $row ) {
		global $wpdb;
		$table = SAUTH_Activator::table( 'auth_outbox' );
		$id    = absint( $row['id'] ?? 0 );
		if ( ! $id ) {
			return;
		}
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status='dispatching', attempts=attempts+1, updated_at=%s WHERE id=%d AND status IN ('pending','retry')",
				current_time( 'mysql', true ),
				$id
			)
		);
		if ( 1 !== (int) $claimed ) {
			return;
		}
		$decoded = json_decode( (string) $row['payload_json'], true );
		$event   = array(
			'event_id'         => (string) $row['event_id'],
			'event_name'       => (string) $row['event_name'],
			'schema_version'   => (string) $row['schema_version'],
			'producer'         => 'file-02-authentication',
			'producer_version' => defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : '',
			'actor_user_id'    => absint( $row['actor_user_id'] ),
			'subject_user_id'  => absint( $row['subject_user_id'] ),
			'privacy_class'    => sanitize_key( (string) $row['privacy_class'] ),
			'trace_id'         => sanitize_text_field( (string) $row['trace_id'] ),
			'payload'          => is_array( $decoded ) ? $decoded : array(),
		);
		try {
			do_action( 'sauth_event', $event );
			do_action( 'sauth_event_' . sanitize_key( str_replace( '.', '_', $event['event_name'] ) ), $event );
			$wpdb->update(
				$table,
				array(
					'status'       => 'published',
					'published_at' => current_time( 'mysql', true ),
					'last_error'   => '',
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} catch ( Throwable $error ) {
			$attempts = absint( $row['attempts'] ?? 0 ) + 1;
			$status   = $attempts >= self::MAX_ATTEMPTS ? 'dead_letter' : 'retry';
			$delay    = min( HOUR_IN_SECONDS, (int) pow( 2, min( $attempts, 10 ) ) * MINUTE_IN_SECONDS );
			$wpdb->update(
				$table,
				array(
					'status'       => $status,
					'available_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
					'last_error'   => substr( sanitize_text_field( $error->getMessage() ), 0, 500 ),
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	private static function sanitize_payload( array $payload ) {
		$output = array();
		foreach ( $payload as $key => $value ) {
			$clean_key = sanitize_key( (string) $key );
			if ( '' === $clean_key || self::is_sensitive_key( $clean_key ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$output[ $clean_key ] = self::sanitize_payload( $value );
			} elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$output[ $clean_key ] = $value;
			} elseif ( null === $value ) {
				$output[ $clean_key ] = null;
			} else {
				$output[ $clean_key ] = substr( sanitize_text_field( (string) $value ), 0, 1000 );
			}
		}
		return $output;
	}

	private static function is_sensitive_key( $key ) {
		$key = sanitize_key( (string) $key );
		foreach ( self::$sensitive_key_fragments as $fragment ) {
			if ( false !== strpos( $key, $fragment ) ) {
				return true;
			}
		}
		return false;
	}

	private static function trace_id( $candidate ) {
		$candidate = strtolower( preg_replace( '/[^a-f0-9-]/i', '', (string) $candidate ) );
		return strlen( $candidate ) >= 16 && strlen( $candidate ) <= 64 ? $candidate : self::uuid();
	}

	private static function uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		try {
			$data = random_bytes( 16 );
			$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
			$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
			return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
		} catch ( Exception $error ) {
			$entropy = hash( 'sha256', uniqid( 'sauth-', true ) . '|' . microtime( true ) );
			return substr( $entropy, 0, 8 ) . '-' . substr( $entropy, 8, 4 ) . '-4' . substr( $entropy, 13, 3 ) . '-8' . substr( $entropy, 17, 3 ) . '-' . substr( $entropy, 20, 12 );
		}
	}
}
