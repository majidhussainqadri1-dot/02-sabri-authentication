<?php

defined( 'ABSPATH' ) || exit;

/**
 * Hardened runtime controller for File 02 passkey mutations and assurance.
 *
 * The historical SAUTH_Passkeys class remains the WebAuthn parser, renderer,
 * schema/privacy helper and compatibility surface. This controller owns the
 * mutable AJAX ceremony endpoints so replay, bounds, persistence postconditions
 * and all-session assurance invalidation have one auditable boundary.
 */
final class SAUTH_Passkey_Runtime {
	const MAX_PASSWORD_BYTES   = 4096;
	const MAX_CHALLENGE_ID     = 64;
	const MAX_CLIENT_DATA_B64  = 32768;
	const MAX_ATTESTATION_B64  = 2097152;
	const MAX_AUTH_DATA_B64    = 131072;
	const MAX_SIGNATURE_B64    = 16384;
	const MAX_RAW_ID_B64       = 4096;
	const MAX_USER_HANDLE_B64  = 512;
	const MAX_CREDENTIALS      = 10;
	const ASSURANCE_TTL        = 300;
	const EPOCH_META           = '_sauth_passkey_assurance_epoch_v1';
	private static $pending_receipt_id = '';
	private static $pending_receipt_user_id = 0;
	private static $pending_bound = false;
	private static $pending_session_token = '';

	public static function init() {
		/* Replace only mutable ceremony endpoints; the original class remains the
		 * parser/renderer/schema/privacy implementation. */
		foreach ( array( 'wp_ajax_sauth_passkey_begin_registration', 'wp_ajax_sauth_passkey_finish_registration', 'wp_ajax_sauth_passkey_revoke', 'wp_ajax_nopriv_sauth_passkey_begin_authentication', 'wp_ajax_sauth_passkey_begin_authentication', 'wp_ajax_nopriv_sauth_passkey_finish_authentication', 'wp_ajax_sauth_passkey_finish_authentication' ) as $hook ) {
			$method = str_replace( array( 'wp_ajax_nopriv_sauth_passkey_', 'wp_ajax_sauth_passkey_' ), '', $hook );
			remove_action( $hook, array( 'SAUTH_Passkeys', $method ) );
		}
		add_action( 'wp_ajax_sauth_passkey_begin_registration', array( __CLASS__, 'begin_registration' ) );
		add_action( 'wp_ajax_sauth_passkey_finish_registration', array( __CLASS__, 'finish_registration' ) );
		add_action( 'wp_ajax_sauth_passkey_revoke', array( __CLASS__, 'revoke' ) );
		add_action( 'wp_ajax_nopriv_sauth_passkey_begin_authentication', array( __CLASS__, 'begin_authentication' ) );
		add_action( 'wp_ajax_sauth_passkey_begin_authentication', array( __CLASS__, 'begin_authentication' ) );
		add_action( 'wp_ajax_nopriv_sauth_passkey_finish_authentication', array( __CLASS__, 'finish_authentication' ) );
		add_action( 'wp_ajax_sauth_passkey_finish_authentication', array( __CLASS__, 'finish_authentication' ) );

		remove_action( 'set_logged_in_cookie', array( 'SAUTH_Passkeys', 'bind_pending_assurance_to_session' ), 40 );
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'bind_pending_assurance_to_session' ), 40, 6 );
		remove_filter( 'smc_file02_authentication_assurance_v1', array( 'SAUTH_Passkeys', 'file00_assurance' ), 20 );
		add_filter( 'smc_file02_authentication_assurance_v1', array( __CLASS__, 'assurance_filter' ), 20, 2 );
	}

	public static function begin_registration() {
		self::require_authenticated_ajax();
		$password = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		$step_up  = isset( $_POST['step_up_code'] ) ? (string) wp_unslash( $_POST['step_up_code'] ) : '';
		if ( strlen( $password ) > self::MAX_PASSWORD_BYTES || strlen( $step_up ) > 128 ) {
			self::json_error( 'fresh_reauthentication_required' );
		}
		/* Legacy step-up codes are retired; never forward them as authority. */
		$_POST['step_up_code'] = '';
		SAUTH_Passkeys::begin_registration();
	}

	public static function begin_authentication() {
		if ( is_user_logged_in() ) {
			self::json_error( 'already_authenticated', 409 );
		}
		$redirect = isset( $_POST['redirect_to'] ) ? (string) wp_unslash( $_POST['redirect_to'] ) : '';
		if ( strlen( $redirect ) > 2048 ) {
			self::json_error( 'redirect_invalid' );
		}
		SAUTH_Passkeys::begin_authentication();
	}

	public static function finish_registration() {
		self::require_authenticated_ajax();
		if ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) { self::json_error( 'passkeys_unavailable', 503 ); }
		$user_id = get_current_user_id();
		$challenge_id = self::post_text( 'challenge_id', self::MAX_CHALLENGE_ID );
		$challenge = self::consume_challenge( $challenge_id, 'register', $user_id );
		if ( empty( $challenge ) ) {
			self::json_error( 'challenge_invalid_expired_or_replayed' );
		}
		$client_data = self::decode_post_b64( 'client_data_json', self::MAX_CLIENT_DATA_B64 );
		$attestation_raw = self::decode_post_b64( 'attestation_object', self::MAX_ATTESTATION_B64 );
		$raw_id = self::decode_post_b64( 'raw_id', self::MAX_RAW_ID_B64 );
		if ( '' === $client_data || '' === $attestation_raw || '' === $raw_id || strlen( $raw_id ) > 1024 ) {
			self::json_error( 'registration_attestation_invalid' );
		}
		$attachment = self::post_text( 'attachment', 32 );
		$attachment = in_array( $attachment, array( 'platform', 'cross-platform' ), true ) ? $attachment : '';
		$nickname = self::post_text( 'nickname', 240 );
		$nickname = function_exists( 'mb_substr' ) ? mb_substr( $nickname, 0, 80 ) : substr( $nickname, 0, 80 );
		$transports = self::bounded_transports( $_POST['transports'] ?? array() );

		$client = SAUTH_Passkeys::validate_client_data( $client_data, 'webauthn.create', (string) $challenge['challenge'] );
		$attestation = SAUTH_Passkeys::parse_attestation_object( $attestation_raw );
		if ( is_wp_error( $client ) || is_wp_error( $attestation ) || 'none' !== (string) ( $attestation['fmt'] ?? '' ) || ! empty( $attestation['att_stmt'] ) ) {
			self::json_error( 'registration_attestation_invalid' );
		}
		$parsed = SAUTH_Passkeys::parse_authenticator_data( (string) $attestation['auth_data'], true );
		if ( is_wp_error( $parsed ) || ! hash_equals( self::rp_id_hash(), (string) ( $parsed['rp_id_hash'] ?? '' ) ) || empty( $parsed['user_present'] ) || empty( $parsed['user_verified'] ) || empty( $parsed['attested'] ) ) {
			self::json_error( 'authenticator_binding_invalid' );
		}
		if ( ! isset( $parsed['credential_id'] ) || ! hash_equals( $raw_id, (string) $parsed['credential_id'] ) ) {
			self::json_error( 'credential_id_mismatch' );
		}
		$key = SAUTH_Passkeys::cose_public_key_to_pem( $parsed['credential_public_key'] ?? array() );
		if ( is_wp_error( $key ) ) {
			self::json_error( 'credential_public_key_invalid' );
		}

		$lock = self::acquire_user_lock( $user_id, 'register' );
		if ( empty( $lock ) ) {
			self::json_error( 'credential_registration_busy', 409 );
		}
		$lock_released = false;
		$store_error = '';
		try {
			global $wpdb;
			$count_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id=%d AND status='active'", $user_id ) );
			if ( null === $count_raw || '' !== (string) $wpdb->last_error ) {
				$store_error = 'credential_store_unavailable';
			} elseif ( (int) $count_raw >= self::MAX_CREDENTIALS ) {
				$store_error = 'credential_limit_reached';
			} else {
				$credential_hash = hash( 'sha256', $raw_id );
				$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE credential_lookup_hash=%s LIMIT 1', $credential_hash ) );
				if ( '' !== (string) $wpdb->last_error ) {
					$store_error = 'credential_store_unavailable';
				} elseif ( null !== $exists ) {
					$store_error = 'credential_already_registered';
				} else {
					$cipher = SA_Security::encrypt( SAUTH_Passkeys::base64url_encode( $raw_id ) );
					if ( '' === $cipher ) {
						$store_error = 'credential_encryption_failed';
					} else {
						$now = current_time( 'mysql', true );
						$public_id = strtolower( wp_generate_uuid4() );
						$inserted = $wpdb->insert(
							self::table(),
							array(
								'public_id' => $public_id,
								'user_id' => $user_id,
								'credential_lookup_hash' => $credential_hash,
								'credential_id_ciphertext' => $cipher,
								'public_key_pem' => (string) $key['pem'],
								'algorithm' => intval( $key['algorithm'] ),
								'transports' => $transports,
								'attachment' => $attachment,
								'discoverable' => 1,
								'backup_eligible' => ! empty( $parsed['backup_eligible'] ) ? 1 : 0,
								'backup_state' => ! empty( $parsed['backup_state'] ) ? 1 : 0,
								'hardware_backed' => 0,
								'sign_count' => absint( $parsed['sign_count'] ?? 0 ),
								'nickname' => $nickname,
								'status' => 'active',
								'created_at' => $now,
								'updated_at' => $now,
							),
							array( '%s','%d','%s','%s','%s','%d','%s','%s','%d','%d','%d','%d','%s','%s','%s','%s' )
						);
						$check = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id,credential_lookup_hash,public_key_pem,algorithm,backup_eligible,backup_state,sign_count,status FROM ' . self::table() . ' WHERE public_id=%s', $public_id ), ARRAY_A );
						if ( 1 !== (int) $inserted
							|| ! is_array( $check )
							|| '' !== (string) $wpdb->last_error
							|| absint( $check['user_id'] ?? 0 ) !== $user_id
							|| 'active' !== (string) ( $check['status'] ?? '' )
							|| ! hash_equals( $credential_hash, (string) ( $check['credential_lookup_hash'] ?? '' ) )
							|| ! hash_equals( (string) $key['pem'], (string) ( $check['public_key_pem'] ?? '' ) )
							|| intval( $check['algorithm'] ?? 0 ) !== intval( $key['algorithm'] )
							|| absint( $check['backup_eligible'] ?? 0 ) !== ( ! empty( $parsed['backup_eligible'] ) ? 1 : 0 )
							|| absint( $check['backup_state'] ?? 0 ) !== ( ! empty( $parsed['backup_state'] ) ? 1 : 0 )
							|| absint( $check['sign_count'] ?? 0 ) !== absint( $parsed['sign_count'] ?? 0 ) ) {
							$deleted = $wpdb->delete( self::table(), array( 'public_id' => $public_id, 'user_id' => $user_id ), array( '%s', '%d' ) );
							$remaining = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE public_id=%s AND user_id=%d', $public_id, $user_id ) );
							if ( false === $deleted || null === $remaining || '' !== (string) $wpdb->last_error || 0 !== (int) $remaining ) { SAUTH_Operations::enter_safe_mode(); }
							$store_error = 'credential_store_postcondition_failed';
						}
					}
				}
			}
		} finally {
			$lock_released = self::release_user_lock( $lock );
			if ( ! $lock_released ) { SAUTH_Operations::enter_safe_mode(); }
		}
		if ( ! $lock_released ) { self::json_error( 'credential_registration_lock_release_failed', 503 ); }
		if ( '' !== $store_error ) { self::json_error( $store_error ); }
		SAUTH_Event_Outbox::emit( 'PasskeyRegistered.v1', $user_id, $user_id, array( 'method' => 'webauthn', 'backup_eligible' => ! empty( $parsed['backup_eligible'] ) ? 1 : 0 ), 'security' );
		SA_Membership_Adapter::audit( 'passkey_registered', $user_id, array( 'backup_eligible' => ! empty( $parsed['backup_eligible'] ) ? 1 : 0 ) );
		wp_send_json_success( array( 'message' => 'Passkey added successfully.', 'reload' => true ) );
	}

	public static function finish_authentication() {
		if ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) { self::json_error( 'passkeys_unavailable', 503 ); }
		if ( is_user_logged_in() ) {
			self::json_error( 'already_authenticated', 409 );
		}
		$challenge_id = self::post_text( 'challenge_id', self::MAX_CHALLENGE_ID );
		$challenge = self::consume_challenge( $challenge_id, 'authenticate', 0 );
		if ( empty( $challenge ) ) {
			self::json_error( 'challenge_invalid_expired_or_replayed' );
		}
		$client_data = self::decode_post_b64( 'client_data_json', self::MAX_CLIENT_DATA_B64 );
		$auth_data   = self::decode_post_b64( 'authenticator_data', self::MAX_AUTH_DATA_B64 );
		$signature   = self::decode_post_b64( 'signature', self::MAX_SIGNATURE_B64 );
		$raw_id      = self::decode_post_b64( 'raw_id', self::MAX_RAW_ID_B64 );
		$user_handle = self::decode_post_b64( 'user_handle', self::MAX_USER_HANDLE_B64, true );
		if ( '' === $client_data || '' === $auth_data || '' === $signature || '' === $raw_id || strlen( $raw_id ) > 1024 ) {
			self::authentication_failure( 0, 'assertion_fields_invalid' );
		}
		$client = SAUTH_Passkeys::validate_client_data( $client_data, 'webauthn.get', (string) $challenge['challenge'] );
		$parsed = SAUTH_Passkeys::parse_authenticator_data( $auth_data, false );
		if ( is_wp_error( $client ) || is_wp_error( $parsed ) || ! hash_equals( self::rp_id_hash(), (string) ( $parsed['rp_id_hash'] ?? '' ) ) || empty( $parsed['user_present'] ) || empty( $parsed['user_verified'] ) ) {
			self::authentication_failure( 0, 'assertion_binding_invalid' );
		}
		global $wpdb;
		$credential_hash = hash( 'sha256', $raw_id );
		$credential = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE credential_lookup_hash=%s LIMIT 1', $credential_hash ), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) { self::authentication_failure( 0, 'credential_store_unavailable' ); }
		if ( ! is_array( $credential ) || 'active' !== (string) $credential['status'] ) {
			self::authentication_failure( 0, 'credential_unknown' );
		}
		$user_id = absint( $credential['user_id'] );
		if ( absint( $credential['backup_eligible'] ?? 0 ) !== ( ! empty( $parsed['backup_eligible'] ) ? 1 : 0 ) ) {
			self::quarantine_credential( $credential, $user_id, 'backup_eligibility_changed' );
			self::authentication_failure( $user_id, 'backup_eligibility_changed' );
		}
		if ( $user_handle !== '' ) {
			$encoded_handle = (string) get_user_meta( $user_id, SAUTH_Passkeys::USER_HANDLE_META, true );
			$expected_handle = SAUTH_Passkeys::base64url_decode( $encoded_handle );
			if ( false === $expected_handle || ! hash_equals( (string) $expected_handle, (string) $user_handle ) ) {
				self::authentication_failure( $user_id, 'user_handle_mismatch' );
			}
		}
		$signed = $auth_data . hash( 'sha256', $client_data, true );
		if ( ! SAUTH_Passkeys::verify_signature( $signed, $signature, (string) $credential['public_key_pem'], intval( $credential['algorithm'] ) ) ) {
			self::authentication_failure( $user_id, 'signature_invalid' );
		}
		$stored_count = absint( $credential['sign_count'] );
		$new_count = absint( $parsed['sign_count'] ?? 0 );
		if ( ( $stored_count > 0 || $new_count > 0 ) && $new_count <= $stored_count ) {
			if ( ! self::quarantine_credential( $credential, $user_id, 'signature_counter_regression' ) ) { self::authentication_failure( $user_id, 'credential_quarantine_failed' ); }
			self::authentication_failure( $user_id, 'signature_counter_regression' );
		}
		$completion = SAUTH_Account_Contract::completion_state( $user_id, array( 'purpose' => 'passkey_sign_in' ) );
		$membership = SA_Membership_Adapter::membership_assertion( $user_id, 'clinical_identity_link', 'passkey_sign_in' );
		if ( ! self::sign_in_allowed( $membership, $completion ) ) {
			self::authentication_failure( $user_id, 'membership_not_eligible' );
		}
		$next_count = max( $stored_count, $new_count );
		$next_backup_state = ! empty( $parsed['backup_state'] ) ? 1 : 0;
		$used_at = current_time( 'mysql', true );
		$changed = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . " SET sign_count=%d,backup_state=%d,last_used_at=%s,updated_at=%s WHERE id=%d AND status='active' AND sign_count=%d AND backup_eligible=%d",
				$next_count,
				$next_backup_state,
				$used_at,
				$used_at,
				absint( $credential['id'] ),
				$stored_count,
				! empty( $parsed['backup_eligible'] ) ? 1 : 0
			)
		);
		$post = $wpdb->get_row( $wpdb->prepare( 'SELECT sign_count,backup_eligible,backup_state,status,last_used_at FROM ' . self::table() . ' WHERE id=%d', absint( $credential['id'] ) ), ARRAY_A );
		if ( false === $changed
			|| ( ( $stored_count > 0 || $new_count > 0 ) && 1 !== (int) $changed )
			|| ! is_array( $post )
			|| '' !== (string) $wpdb->last_error
			|| 'active' !== (string) ( $post['status'] ?? '' )
			|| absint( $post['sign_count'] ?? 0 ) !== $next_count
			|| absint( $post['backup_eligible'] ?? 0 ) !== ( ! empty( $parsed['backup_eligible'] ) ? 1 : 0 )
			|| absint( $post['backup_state'] ?? 0 ) !== $next_backup_state
			|| ! hash_equals( $used_at, (string) ( $post['last_used_at'] ?? '' ) ) ) {
			self::authentication_failure( $user_id, 'credential_update_postcondition_failed' );
		}
		if ( '' === self::store_pending_assurance( $user_id, ! empty( $credential['hardware_backed'] ) ) ) {
			self::authentication_failure( $user_id, 'assurance_store_failed' );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, ! empty( $_POST['remember'] ), is_ssl() );
		$session_persisted = self::$pending_bound
			&& '' !== self::$pending_session_token
			&& SAUTH_Session_Manager::session_binding_ready( $user_id, self::$pending_session_token );
		if ( ! self::$pending_bound || ! $session_persisted ) {
			if ( '' !== self::$pending_session_token ) {
				delete_transient( self::session_assurance_key( $user_id, self::$pending_session_token ) );
			}
			if ( class_exists( 'WP_Session_Tokens' ) && '' !== self::$pending_session_token ) {
				WP_Session_Tokens::get_instance( $user_id )->destroy( self::$pending_session_token );
			} else {
				SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'passkey_session_binding_failed' );
			}
			wp_clear_auth_cookie();
			self::reset_pending_state();
			self::authentication_failure( $user_id, 'session_assurance_binding_failed' );
		}
		self::reset_pending_state();
		if ( ! SAUTH_Login_Risk::record_successful_login( $user_id, 'passkey', 0 ) ) {
			SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'passkey_risk_evidence_store_failed' );
			wp_clear_auth_cookie();
			self::authentication_failure( $user_id, 'risk_evidence_store_failed' );
		}
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationSucceeded.v1', $user_id, $user_id, array( 'method' => 'passkey', 'risk' => 'strong_authentication' ), 'security' );
		SAUTH_Event_Outbox::emit( 'PasskeyAuthenticated.v1', $user_id, $user_id, array( 'backup_state' => ! empty( $parsed['backup_state'] ) ? 1 : 0 ), 'security' );
		SA_Membership_Adapter::audit( 'passkey_authentication_succeeded', $user_id );
		SA_Security::clear_rate_limit( 'passkey_login_ip' );
		$destination = isset( $challenge['redirect'] ) ? SA_Security::safe_redirect( $challenge['redirect'], home_url( '/' ) ) : home_url( '/' );
		if ( 'allow' === ( $completion['result'] ?? '' ) && ! empty( $completion['missing_steps'] ) && ! empty( $completion['next_route'] ) ) {
			$destination = SA_Security::safe_redirect( (string) $completion['next_route'], $destination );
		}
		wp_send_json_success( array( 'redirect' => $destination ) );
	}

	public static function revoke() {
		self::require_authenticated_ajax();
		$user_id = get_current_user_id();
		$public_id = self::post_text( 'credential_id', self::MAX_CHALLENGE_ID );
		if ( ! self::valid_uuid( $public_id ) ) { self::json_error( 'credential_not_found' ); }
		$password = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		if ( strlen( $password ) > self::MAX_PASSWORD_BYTES ) { $password = ''; }
		$current = self::current_assurance( $user_id );
		if ( empty( $current['passkey_asserted'] ) ) {
			$user = get_userdata( $user_id );
			if ( '' === $password || ! $user instanceof WP_User || ! wp_check_password( $password, (string) $user->user_pass, $user_id ) ) {
				$password = '';
				self::json_error( 'fresh_reauthentication_required' );
			}
		}
		$password = '';
		unset( $_POST['current_password'], $_POST['step_up_code'] );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE public_id=%s AND user_id=%d', $public_id, $user_id ), ARRAY_A );
		if ( ! is_array( $row ) ) { self::json_error( 'credential_not_found' ); }
		if ( 'active' !== (string) $row['status'] ) { wp_send_json_success( array( 'message' => 'This passkey was already inactive.', 'reload' => true ) ); }
		$changed = $wpdb->update( self::table(), array( 'status' => 'revoked', 'revoked_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $row['id'] ), 'user_id' => $user_id, 'status' => 'active' ), array( '%s','%s','%s' ), array( '%d','%d','%s' ) );
		if ( 1 !== (int) $changed ) { self::json_error( 'credential_revoke_failed' ); }
		$status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE id=%d AND user_id=%d', absint( $row['id'] ), $user_id ) );
		if ( 'revoked' !== $status ) { self::json_error( 'credential_revoke_postcondition_failed' ); }
		if ( ! self::invalidate_user_assurance( $user_id ) ) { self::json_error( 'assurance_invalidation_failed' ); }
		SAUTH_Event_Outbox::emit( 'PasskeyRevoked.v1', $user_id, $user_id, array( 'reason' => 'user_request' ), 'security' );
		SA_Membership_Adapter::audit( 'passkey_revoked', $user_id );
		wp_send_json_success( array( 'message' => 'Passkey revoked.', 'reload' => true ) );
	}

	public static function bind_pending_assurance_to_session( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		$user_id = absint( $user_id );
		$token = (string) $token;
		$receipt_id = self::$pending_receipt_id;
		if ( ! $user_id || 'logged_in' !== $scheme || '' === $token || '' === $receipt_id || self::$pending_receipt_user_id !== $user_id ) { return; }
		$key = self::pending_assurance_key( $user_id, $receipt_id );
		$receipt = get_transient( $key );
		delete_transient( $key );
		self::$pending_receipt_id = '';
		self::$pending_receipt_user_id = 0;
		self::$pending_session_token = $token;
		if ( false !== get_transient( $key ) || ! self::valid_pending_receipt( $receipt, $user_id, $receipt_id ) ) { return; }
		$receipt['session_binding'] = self::session_binding( $token );
		$ttl = max( 1, min( self::ASSURANCE_TTL, absint( $receipt['expires_at'] ) - time() ) );
		$session_key = self::session_assurance_key( $user_id, $token );
		set_transient( $session_key, $receipt, $ttl );
		$stored = get_transient( $session_key );
		self::$pending_bound = is_array( $stored ) && $stored === $receipt;
		if ( ! self::$pending_bound ) {
			delete_transient( $session_key );
			if ( class_exists( 'WP_Session_Tokens' ) ) { WP_Session_Tokens::get_instance( $user_id )->destroy( $token ); }
		}
	}

	public static function assurance_filter( $baseline, $user_id ) {
		$assurance = self::current_assurance( $user_id );
		return ! empty( $assurance['passkey_asserted'] ) ? $assurance : ( is_array( $baseline ) && empty( $baseline['passkey_asserted'] ) ? $baseline : array() );
	}

	public static function current_assurance( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! is_user_logged_in() || get_current_user_id() !== $user_id ) { return array(); }
		$token = (string) wp_get_session_token();
		if ( '' === $token ) { return array(); }
		$receipt = get_transient( self::session_assurance_key( $user_id, $token ) );
		if ( ! self::valid_session_receipt( $receipt, $user_id, $token ) ) { return array(); }
		$membership = SA_Membership_Adapter::membership_assertion( $user_id, 'clinical_identity_link', 'authentication_assurance' );
		if ( 'allow' !== ( $membership['result'] ?? '' ) || empty( $membership['membership']['active'] ) || ! empty( $membership['membership']['suspended'] ) ) {
			delete_transient( self::session_assurance_key( $user_id, $token ) );
			return array();
		}
		return array(
			'contract_version' => SAUTH_Passkeys::CONTRACT_VERSION,
			'owner' => 'file02',
			'level' => 3,
			'method' => 'webauthn_passkey',
			'passkey_asserted' => true,
			'hardware_backed' => ! empty( $receipt['hardware_backed'] ),
			'verified_at' => absint( $receipt['verified_at'] ),
		);
	}

	public static function invalidate_user_assurance( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return false; }
		$new_epoch = SA_Security::random_token( 16 );
		if ( '' === $new_epoch ) { return self::contain_invalidation_failure( $user_id ); }
		update_user_meta( $user_id, self::EPOCH_META, $new_epoch );
		if ( ! hash_equals( $new_epoch, (string) get_user_meta( $user_id, self::EPOCH_META, true ) ) ) {
			return self::contain_invalidation_failure( $user_id );
		}
		if ( ! SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'passkey_assurance_invalidated' ) ) {
			return self::contain_invalidation_failure( $user_id );
		}
		if ( self::$pending_receipt_user_id === $user_id && '' !== self::$pending_receipt_id ) {
			delete_transient( self::pending_assurance_key( $user_id, self::$pending_receipt_id ) );
			self::reset_pending_state();
		}
		$token = (string) wp_get_session_token();
		if ( '' !== $token ) { delete_transient( self::session_assurance_key( $user_id, $token ) ); }
		return true;
	}

	private static function contain_invalidation_failure( $user_id ) {
		self::contain_security_state_failure( $user_id, 'passkey_assurance_invalidation' );
		return false;
	}

	private static function contain_security_state_failure( $user_id, $reason ) {
		$user_id = absint( $user_id );
		$revoked = $user_id && class_exists( 'SAUTH_Session_Manager' ) ? SAUTH_Session_Manager::revoke_user_sessions( $user_id, sanitize_key( (string) $reason ) ) : false;
		$safe = class_exists( 'SAUTH_Operations' ) ? SAUTH_Operations::enter_safe_mode() : false;
		SA_Membership_Adapter::audit( 'passkey_security_state_contained', $user_id, array( 'reason' => sanitize_key( (string) $reason ), 'sessions_revoked_verified' => (bool) $revoked, 'safe_mode_verified' => (bool) $safe ) );
		return $revoked && $safe;
	}

	private static function store_pending_assurance( $user_id, $hardware_backed ) {
		$epoch = self::current_epoch( $user_id, true );
		$raw_id = SA_Security::random_token( 24 );
		if ( '' === $epoch || '' === $raw_id ) { return ''; }
		$receipt_id = $raw_id;
		$receipt = array(
			'contract_version' => SAUTH_Passkeys::CONTRACT_VERSION,
			'owner' => 'file02',
			'user_id' => absint( $user_id ),
			'receipt_id' => $receipt_id,
			'method' => 'webauthn_passkey',
			'hardware_backed' => (bool) $hardware_backed,
			'verified_at' => time(),
			'expires_at' => time() + self::ASSURANCE_TTL,
			'fingerprint' => SA_Security::client_fingerprint(),
			'session_binding' => '',
			'assurance_epoch' => $epoch,
		);
		$key = self::pending_assurance_key( $user_id, $receipt_id );
		set_transient( $key, $receipt, self::ASSURANCE_TTL );
		if ( get_transient( $key ) !== $receipt ) {
			delete_transient( $key );
			return '';
		}
		self::$pending_receipt_id = $receipt_id;
		self::$pending_receipt_user_id = absint( $user_id );
		self::$pending_bound = false;
		self::$pending_session_token = '';
		return $receipt_id;
	}

	private static function valid_pending_receipt( $receipt, $user_id, $receipt_id = '' ) {
		return is_array( $receipt )
			&& SAUTH_Passkeys::CONTRACT_VERSION === (string) ( $receipt['contract_version'] ?? '' )
			&& absint( $receipt['user_id'] ?? 0 ) === absint( $user_id )
			&& 'file02' === (string) ( $receipt['owner'] ?? '' )
			&& 'webauthn_passkey' === (string) ( $receipt['method'] ?? '' )
			&& ( '' === (string) $receipt_id || hash_equals( (string) $receipt_id, (string) ( $receipt['receipt_id'] ?? '' ) ) )
			&& absint( $receipt['verified_at'] ?? 0 ) <= time() + 60
			&& absint( $receipt['verified_at'] ?? 0 ) >= time() - self::ASSURANCE_TTL - 60
			&& absint( $receipt['expires_at'] ?? 0 ) > time()
			&& hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )
			&& hash_equals( self::current_epoch( $user_id, false ), (string) ( $receipt['assurance_epoch'] ?? '' ) );
	}

	private static function valid_session_receipt( $receipt, $user_id, $token ) {
		return self::valid_pending_receipt( $receipt, $user_id )
			&& hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) );
	}

	private static function current_epoch( $user_id, $create ) {
		$user_id = absint( $user_id );
		$epoch = $user_id ? (string) get_user_meta( $user_id, self::EPOCH_META, true ) : '';
		if ( '' !== $epoch || ! $create || ! $user_id ) { return $epoch; }
		$generated = SA_Security::random_token( 16 );
		if ( '' === $generated ) { return ''; }
		add_user_meta( $user_id, self::EPOCH_META, $generated, true );
		$epoch = (string) get_user_meta( $user_id, self::EPOCH_META, true );
		if ( '' === $epoch ) {
			update_user_meta( $user_id, self::EPOCH_META, $generated );
			$epoch = (string) get_user_meta( $user_id, self::EPOCH_META, true );
		}
		return $epoch;
	}

	private static function consume_challenge( $id, $purpose, $user_id ) {
		$id = strtolower( trim( (string) $id ) );
		if ( ! self::valid_uuid( $id ) ) { return array(); }
		$key = self::challenge_key( $id );
		$record = get_transient( $key );
		if ( ! is_array( $record )
			|| ! hash_equals( $id, strtolower( (string) ( $record['id'] ?? '' ) ) )
			|| sanitize_key( $record['purpose'] ?? '' ) !== sanitize_key( $purpose )
			|| absint( $record['user_id'] ?? 0 ) !== absint( $user_id )
			|| absint( $record['expires_at'] ?? 0 ) <= time()
			|| ! hash_equals( (string) ( $record['fingerprint'] ?? '' ), SA_Security::client_fingerprint() ) ) {
			return array();
		}
		$ctx = self::rp_context();
		if ( ! hash_equals( (string) ( $record['origin'] ?? '' ), $ctx['origin'] ) || ! hash_equals( (string) ( $record['rp_id'] ?? '' ), $ctx['rp_id'] ) ) { return array(); }
		/* Only a challenge proven to exist may allocate a durable replay claim. */
		if ( ! add_option( self::challenge_claim_key( $id ), (string) time(), '', false ) ) { return array(); }
		delete_transient( $key );
		return $record;
	}

	private static function decode_post_b64( $field, $max_encoded, $optional = false ) {
		$value = isset( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : '';
		if ( '' === $value && $optional ) { return ''; }
		if ( '' === $value || strlen( $value ) > absint( $max_encoded ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) { return ''; }
		$decoded = SAUTH_Passkeys::base64url_decode( $value );
		return false === $decoded ? '' : $decoded;
	}

	private static function post_text( $field, $max ) {
		$value = isset( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : '';
		if ( strlen( $value ) > absint( $max ) ) { return ''; }
		return sanitize_text_field( $value );
	}

	private static function bounded_transports( $input ) {
		$items = is_array( $input ) ? array_slice( $input, 0, 10 ) : explode( ',', substr( (string) $input, 0, 256 ) );
		$allowed = array( 'usb', 'nfc', 'ble', 'internal', 'hybrid' );
		$out = array();
		foreach ( $items as $item ) { $item = sanitize_key( (string) $item ); if ( in_array( $item, $allowed, true ) ) { $out[] = $item; } }
		return implode( ',', array_values( array_unique( $out ) ) );
	}

	private static function sign_in_allowed( array $assertion, array $completion ) {
		if ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || ! empty( $assertion['membership']['suspended'] ) ) {
			return false;
		}
		if ( 'allow' === ( $assertion['result'] ?? '' ) ) {
			return true;
		}
		$active = true === ( $assertion['membership']['active'] ?? false );
		return $active
			&& 'allow' === ( $completion['result'] ?? '' )
			&& ! empty( $completion['missing_steps'] )
			&& ! empty( $completion['next_route'] );
	}

	private static function authentication_failure( $user_id, $reason ) {
		$user_id = absint( $user_id );
		SAUTH_Login_Risk::record_failure( $user_id, $reason, 100 );
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user_id, $user_id, array( 'method' => 'passkey', 'reason' => sanitize_key( $reason ) ), 'security' );
		if ( $user_id ) { SA_Membership_Adapter::audit( 'passkey_authentication_failed', $user_id, array( 'reason' => sanitize_key( $reason ) ) ); }
		self::json_error( 'passkey_not_accepted' );
	}

	private static function quarantine_credential( array $credential, $user_id, $reason ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$changed = $wpdb->update( self::table(), array( 'status' => 'compromised', 'revoked_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $credential['id'] ), 'user_id' => $user_id, 'status' => 'active' ), array( '%s','%s','%s' ), array( '%d','%d','%s' ) );
		$status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE id=%d AND user_id=%d', absint( $credential['id'] ), $user_id ) );
		$invalidated = self::invalidate_user_assurance( $user_id );
		$contained = false !== $changed && 'compromised' === $status && '' === (string) $wpdb->last_error && $invalidated;
		if ( ! $contained ) { self::contain_security_state_failure( $user_id, 'passkey_quarantine' ); }
		SA_Membership_Adapter::audit( 'passkey_credential_quarantined', $user_id, array( 'reason' => sanitize_key( (string) $reason ), 'contained' => $contained ) );
		return $contained;
	}

	private static function acquire_user_lock( $user_id, $operation ) {
		global $wpdb;
		$operation = sanitize_key( (string) $operation );
		$purpose = 'register' === $operation ? 'passkey-enrollment' : 'passkey-' . $operation;
		$name = 'sauth:' . substr( hash( 'sha256', $purpose . '|' . absint( $user_id ) ), 0, 50 );
		$connection_id = absint( $wpdb->get_var( 'SELECT CONNECTION_ID()' ) );
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $name, 0 ) );
		$owner = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $name ) ) );
		if ( ! $connection_id || 1 !== (int) $acquired || $owner !== $connection_id || '' !== (string) $wpdb->last_error ) {
			if ( 1 === (int) $acquired ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); }
			return array();
		}
		return array( 'name' => $name, 'connection_id' => $connection_id );
	}

	private static function release_user_lock( array $lock ) {
		global $wpdb;
		if ( empty( $lock['name'] ) || empty( $lock['connection_id'] ) ) { return false; }
		$connection_id = absint( $wpdb->get_var( 'SELECT CONNECTION_ID()' ) );
		$owner = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', (string) $lock['name'] ) ) );
		if ( $connection_id !== absint( $lock['connection_id'] ) || $owner !== $connection_id || '' !== (string) $wpdb->last_error ) { return false; }
		$released = $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', (string) $lock['name'] ) );
		return 1 === (int) $released && '' === (string) $wpdb->last_error;
	}

	private static function reset_pending_state() { self::$pending_receipt_id = ''; self::$pending_receipt_user_id = 0; self::$pending_bound = false; self::$pending_session_token = ''; }
	private static function challenge_key( $id ) { return 'sauth_pk_ch_' . md5( (string) $id ); }
	private static function challenge_claim_key( $id ) { return 'sauth_pk_claim_' . md5( (string) $id ); }
	private static function pending_assurance_key( $user_id, $receipt_id ) { return 'sauth_pk_pending_' . absint( $user_id ) . '_' . substr( hash( 'sha256', (string) $receipt_id ), 0, 32 ); }
	private static function session_assurance_key( $user_id, $token ) { return 'sauth_pk_session_' . absint( $user_id ) . '_' . substr( hash_hmac( 'sha256', (string) $token, wp_salt( 'logged_in' ) ), 0, 32 ); }
	private static function session_binding( $token ) { return hash_hmac( 'sha256', 'sauth-passkey-session|' . (string) $token, wp_salt( 'logged_in' ) ); }
	private static function table() { global $wpdb; return $wpdb->prefix . 'sauth_passkeys'; }
	private static function rp_id_hash() { return hash( 'sha256', self::rp_context()['rp_id'], true ); }
	private static function rp_context() {
		$parts = wp_parse_url( home_url( '/' ) );
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host = isset( $parts['host'] ) ? strtolower( rtrim( (string) $parts['host'], '.' ) ) : '';
		$port = isset( $parts['port'] ) ? absint( $parts['port'] ) : 0;
		$origin = $scheme . '://' . $host;
		if ( $port && ! ( 'https' === $scheme && 443 === $port ) && ! ( 'http' === $scheme && 80 === $port ) ) { $origin .= ':' . $port; }
		return array( 'scheme' => $scheme, 'rp_id' => $host, 'origin' => $origin );
	}
	private static function valid_uuid( $value ) { return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value ); }
	private static function require_authenticated_ajax() { if ( ! is_user_logged_in() ) { self::json_error( 'authentication_required', 401 ); } check_ajax_referer( 'sauth_passkeys', 'nonce' ); }
	private static function json_error( $code, $status = 400 ) { wp_send_json_error( array( 'code' => sanitize_key( (string) $code ), 'message' => 'The passkey operation could not be completed safely.' ), absint( $status ) ); }
}
