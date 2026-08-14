<?php

defined( 'ABSPATH' ) || exit;

/** File 02 privacy export, erasure and policy disclosure. */
final class SA_Privacy {
	const EXPORT_LIMIT = 200;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'privacy_policy' ) );
	}

	public function register_exporter( array $exporters ) {
		$exporters['sabri-authentication'] = array( 'exporter_friendly_name' => __( 'Sabri Authentication and Accounts', 'sabri-authentication' ), 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function register_eraser( array $erasers ) {
		$erasers['sabri-authentication'] = array( 'eraser_friendly_name' => __( 'Sabri Authentication and Accounts', 'sabri-authentication' ), 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function export( $email_address, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User ) { return array( 'data' => array(), 'done' => true ); }
		$user_id = (int) $user->ID;
		$page = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * self::EXPORT_LIMIT;
		$data = array();
		$done = true;

		$google = 1 === $page ? $this->google_projection( $user_id ) : array();
		if ( ! empty( $google ) ) {
			$data[] = array( 'group_id' => 'sabri-authentication-google', 'group_label' => __( 'Google account authentication', 'sabri-authentication' ), 'item_id' => 'google-link-' . $user_id, 'data' => $this->export_pairs( $google ) );
		}

		$sessions = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,device_label,network_label,risk_level,status,last_seen_at,expires_at,revoked_at,revocation_reason FROM ' . SAUTH_Activator::table( 'auth_sessions' ) . ' WHERE user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
		if ( ! is_array( $sessions ) || '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
		$done = $done && count( $sessions ) < self::EXPORT_LIMIT;
		foreach ( $sessions as $row ) {
			$data[] = array( 'group_id' => 'sabri-authentication-sessions', 'group_label' => __( 'Authentication sessions', 'sabri-authentication' ), 'item_id' => 'session-' . sanitize_key( (string) $row['public_id'] ), 'data' => $this->export_pairs( $row ) );
		}

		$email_row = 1 === $page ? $wpdb->get_row( $wpdb->prepare( 'SELECT status,attempts,sent_at,expires_at,verified_at,created_at,updated_at FROM ' . SAUTH_Activator::table( 'email_verifications' ) . ' WHERE user_id=%d', $user_id ), ARRAY_A ) : null;
		if ( 1 === $page && '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
		if ( is_array( $email_row ) ) {
			$data[] = array( 'group_id' => 'sabri-authentication-email', 'group_label' => __( 'Email verification', 'sabri-authentication' ), 'item_id' => 'email-verification-' . $user_id, 'data' => $this->export_pairs( $email_row ) );
		}

		$attempts = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,result,reason_code,risk_score,created_at FROM ' . SAUTH_Activator::table( 'auth_attempts' ) . ' WHERE user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
		if ( ! is_array( $attempts ) || '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
		$done = $done && count( $attempts ) < self::EXPORT_LIMIT;
		foreach ( $attempts as $row ) {
			$data[] = array( 'group_id' => 'sabri-authentication-attempts', 'group_label' => __( 'Authentication security attempts', 'sabri-authentication' ), 'item_id' => 'attempt-' . sanitize_key( (string) $row['public_id'] ), 'data' => $this->export_pairs( $row ) );
		}

		$events = $wpdb->get_results( $wpdb->prepare( 'SELECT event_id,event_name,privacy_class,trace_id,status,created_at,published_at FROM ' . SAUTH_Activator::table( 'auth_outbox' ) . ' WHERE actor_user_id=%d OR subject_user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
		if ( ! is_array( $events ) || '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
		$done = $done && count( $events ) < self::EXPORT_LIMIT;
		foreach ( $events as $row ) {
			$data[] = array( 'group_id' => 'sabri-authentication-events', 'group_label' => __( 'Authentication event evidence', 'sabri-authentication' ), 'item_id' => 'event-' . sanitize_key( (string) $row['event_id'] ), 'data' => $this->export_pairs( $row ) );
		}

		if ( class_exists( 'SAUTH_Passkeys' ) && is_callable( array( 'SAUTH_Passkeys', 'privacy_export' ) ) ) {
			$passkeys = SAUTH_Passkeys::privacy_export( sanitize_email( $email_address ), $page );
			if ( is_array( $passkeys ) && ! empty( $passkeys['data'] ) && is_array( $passkeys['data'] ) ) { $data = array_merge( $data, $passkeys['data'] ); }
			$done = $done && is_array( $passkeys ) && ! empty( $passkeys['done'] );
		}
		return array( 'data' => $data, 'done' => $done );
	}

	/**
	 * Erase only File 02-owned personal data. File 00 identity/membership truth
	 * and File 03 profile fields are explicitly out of scope.
	 */
	public function erase( $email_address, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$user_id = (int) $user->ID;
		$removed = false;
		$messages = array();

		/* The barrier must be raised before any deletion. If it cannot be proved,
		 * fail closed and retain data rather than race queued recovery workers. */
		if ( ! SAUTH_Privacy_Jobs::begin_erasure( $user_id ) ) {
			return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( __( 'File 02 privacy erasure could not establish its asynchronous-job barrier. No destructive erasure was attempted.', 'sabri-authentication' ) ), 'done' => true );
		}

		$failed = false;
		$more_outbox = false;
		try {
			if ( ! SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'privacy_erasure' ) ) {
				$failed = true;
				$messages[] = __( 'WordPress session revocation could not be verified.', 'sabri-authentication' );
			}

			if ( class_exists( 'SAUTH_Passkey_Runtime' ) && ! SAUTH_Passkey_Runtime::invalidate_user_assurance( $user_id ) ) {
				/* invalidate_user_assurance contains failure by destroying every session,
				 * but erasure still reports the unverified epoch mutation. */
				$failed = true;
				$messages[] = __( 'Passkey assurance invalidation could not be verified.', 'sabri-authentication' );
			}

			foreach ( SA_Google_OAuth::google_meta_keys() as $key ) {
				if ( metadata_exists( 'user', $user_id, $key ) ) { $removed = true; }
				delete_user_meta( $user_id, $key );
				if ( metadata_exists( 'user', $user_id, $key ) ) { $failed = true; }
			}

			/* Durable passkey rows plus opaque WebAuthn user handle. */
			$passkey_table = $wpdb->prefix . 'sauth_passkeys';
			$passkey_count_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$passkey_table} WHERE user_id=%d", $user_id ) );
			if ( null === $passkey_count_raw || '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_passkey_count_failed' ); }
			$passkey_count = (int) $passkey_count_raw;
			if ( $passkey_count > 0 ) { $removed = true; }
			if ( class_exists( 'SAUTH_Passkeys' ) && is_callable( array( 'SAUTH_Passkeys', 'privacy_erase' ) ) ) {
				$passkey_erasure = SAUTH_Passkeys::privacy_erase( sanitize_email( $email_address ), $page );
				if ( ! is_array( $passkey_erasure ) || ! empty( $passkey_erasure['items_retained'] ) ) { $failed = true; }
			}
			$passkey_remaining = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$passkey_table} WHERE user_id=%d", $user_id ) );
			if ( null === $passkey_remaining || '' !== (string) $wpdb->last_error || 0 !== (int) $passkey_remaining ) { $failed = true; }
			delete_user_meta( $user_id, SAUTH_Passkeys::USER_HANDLE_META );
			delete_user_meta( $user_id, SAUTH_Passkey_Runtime::EPOCH_META );

			/* File 02 canonical and preserved legacy rows must both honor erasure. */
			$table_pairs = array(
				array( SAUTH_Activator::table( 'email_verifications' ), SAUTH_Activator::legacy_table( 'email_verifications' ) ),
				array( SAUTH_Activator::table( 'auth_sessions' ), SAUTH_Activator::legacy_table( 'auth_sessions' ) ),
				array( SAUTH_Activator::table( 'auth_devices' ), SAUTH_Activator::legacy_table( 'auth_devices' ) ),
				array( SAUTH_Activator::table( 'risk_challenges' ), SAUTH_Activator::legacy_table( 'risk_challenges' ) ),
				array( SAUTH_Activator::table( 'auth_attempts' ), SAUTH_Activator::legacy_table( 'auth_attempts' ) ),
			);
			foreach ( $table_pairs as $pair ) {
				foreach ( array_unique( array_filter( $pair ) ) as $table ) {
					if ( ! self::table_exists( $table ) ) { continue; }
					$count_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d", $user_id ) );
					if ( null === $count_raw || '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_table_count_failed' ); }
					if ( (int) $count_raw > 0 ) { $removed = true; }
					$result = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE user_id=%d", $user_id ) );
					$remaining_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d", $user_id ) );
					if ( false === $result || null === $remaining_raw || '' !== (string) $wpdb->last_error || 0 !== (int) $remaining_raw ) { $failed = true; }
				}
			}

			/* Authentication event evidence keeps the event but removes direct subject
			 * identifiers and identity-key payload fields. */
			foreach ( array_unique( array_filter( array( SAUTH_Activator::table( 'auth_outbox' ), SAUTH_Activator::legacy_table( 'auth_outbox' ) ) ) ) as $table ) {
				if ( ! self::table_exists( $table ) ) { continue; }
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,payload_json FROM `{$table}` WHERE actor_user_id=%d OR subject_user_id=%d LIMIT %d", $user_id, $user_id, 5000 ), ARRAY_A );
				if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_outbox_read_failed' ); }
				foreach ( $rows as $row ) {
					$payload = json_decode( (string) $row['payload_json'], true );
					$payload = is_array( $payload ) ? self::erase_identity_payload( $payload ) : array();
					$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
					$updated = $wpdb->update( $table, array( 'actor_user_id' => 0, 'subject_user_id' => 0, 'payload_json' => false === $payload_json ? '{}' : $payload_json, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $row['id'] ) ), array( '%d','%d','%s','%s' ), array( '%d' ) );
					if ( false === $updated ) { $failed = true; }
					else { $removed = true; }
				}
				$remaining_raw = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE actor_user_id=%d OR subject_user_id=%d", $user_id, $user_id ) );
				if ( null === $remaining_raw || '' !== (string) $wpdb->last_error ) { $failed = true; }
				elseif ( (int) $remaining_raw > 0 ) { $more_outbox = true; }
			}

			/* New asynchronous jobs are indexed and were purged by begin_erasure().
			 * Legacy unindexed transient receipts remain cryptographically invalid
			 * after session/epoch revocation and expire within their original short TTL. */
			$messages[] = __( 'Any pre-existing unindexed short-lived authentication receipt is invalidated immediately and expires under its original bounded transient lifetime.', 'sabri-authentication' );
		} catch ( Throwable $error ) {
			$failed = true;
			$messages[] = __( 'File 02 privacy erasure encountered an internal failure and remains fail-closed.', 'sabri-authentication' );
		}

		if ( $more_outbox && ! $failed ) {
			$messages[] = __( 'File 02 authentication-event anonymization is continuing in the next privacy-erasure page.', 'sabri-authentication' );
			SA_Membership_Adapter::audit( 'authentication_privacy_erasure_continuation', $user_id );
			return array( 'items_removed' => $removed, 'items_retained' => true, 'messages' => $messages, 'done' => false );
		}

		if ( $failed ) {
			/* Do not clear the erasure barrier; no recovery/resend worker may recreate
			 * File 02 state until an operator safely retries/completes erasure. */
			SA_Membership_Adapter::audit( 'authentication_privacy_erasure_incomplete', $user_id );
			return array( 'items_removed' => $removed, 'items_retained' => true, 'messages' => $messages, 'done' => true );
		}

		if ( ! SAUTH_Privacy_Jobs::finish_erasure( $user_id ) ) {
			$messages[] = __( 'File 02 data was erased but the privacy barrier cleanup could not be verified.', 'sabri-authentication' );
			return array( 'items_removed' => true, 'items_retained' => true, 'messages' => $messages, 'done' => true );
		}
		SA_Membership_Adapter::audit( 'authentication_privacy_erasure_completed', $user_id );
		return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => $messages, 'done' => true );
	}

	public function privacy_policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { return; }
		$content = '<p>' . esc_html__( 'File 02 processes password authentication state, verified Google-link projections, signed email-verification evidence, privacy-minimized session/device/risk projections, WebAuthn/passkey public-key credentials and short-lived asynchronous recovery/resend job state. Passwords, reset keys, verification tokens, OAuth tokens, passkey private keys and raw WordPress session tokens are not stored by File 02.', 'sabri-authentication' ) . '</p>';
		$content .= '<p>' . esc_html__( 'File 00 remains the canonical owner of membership, identity, account class, guardian, role and verification truth. File 02 does not erase those records. Privacy erasure revokes File 02 sessions and authentication assurance, deletes File 02-owned Google/passkey/session/verification/risk records, anonymizes direct user identifiers in authentication event evidence and invalidates queued recovery work before deletion.', 'sabri-authentication' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Sabri Authentication and Accounts', 'sabri-authentication' ), wp_kses_post( wpautop( $content ) ) );
	}

	private function google_projection( $user_id ) {
		$fields = array( 'sub','email','email_verified','account','linked_at','last_login_at','link_version','picture' );
		$out = array();
		foreach ( $fields as $field ) {
			$value = get_user_meta( $user_id, '_sauth_google_' . $field, true );
			if ( '' === (string) $value ) { $value = get_user_meta( $user_id, '_sa_google_' . $field, true ); }
			if ( '' !== (string) $value ) { $out[ $field ] = $value; }
		}
		return $out;
	}

	private function export_pairs( array $values ) {
		$out = array();
		foreach ( $values as $name => $value ) { $out[] = array( 'name' => ucwords( str_replace( '_', ' ', sanitize_key( (string) $name ) ) ), 'value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); }
		return $out;
	}

	private static function erase_identity_payload( array $payload, $depth = 0 ) {
		if ( $depth >= 4 ) { return array(); }
		$out = array();
		foreach ( $payload as $key => $value ) {
			$clean = sanitize_key( (string) $key );
			if ( in_array( $clean, array( 'user_id','actor_user_id','subject_user_id','owner_user_id','platform_uuid','subject_uuid' ), true ) ) { continue; }
			$out[ $clean ] = is_array( $value ) ? self::erase_identity_payload( $value, $depth + 1 ) : $value;
		}
		return $out;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$table = (string) $table;
		if ( '' === $table ) { return false; }
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_table_probe_failed' ); }
		return $table === (string) $found;
	}
}
