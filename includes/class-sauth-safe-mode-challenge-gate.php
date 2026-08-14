<?php

defined( 'ABSPATH' ) || exit;

/**
 * Reject WebAuthn challenges issued before the most recent Safe Mode entry.
 * This remains effective after Safe Mode exits, preventing a pre-containment
 * challenge from becoming usable again within its original transient TTL.
 */
final class SAUTH_Safe_Mode_Challenge_Gate {
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'reject_stale_passkey_challenge' ), 0 );
	}

	public static function reject_stale_passkey_challenge() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $action, array( 'sauth_passkey_finish_registration', 'sauth_passkey_finish_authentication' ), true ) ) {
			return;
		}
		$entered_at = class_exists( 'SAUTH_Operations' ) ? SAUTH_Operations::safe_mode_entered_at() : 0;
		if ( ! $entered_at ) {
			return;
		}
		$id = isset( $_REQUEST['challenge_id'] ) ? strtolower( sanitize_text_field( wp_unslash( $_REQUEST['challenge_id'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id ) ) {
			return;
		}
		$key = 'sauth_pk_ch_' . md5( $id );
		$record = get_transient( $key );
		if ( ! is_array( $record ) ) {
			return;
		}
		$created = absint( $record['created_at'] ?? 0 );
		if ( ! $created || $created <= $entered_at ) {
			delete_transient( $key );
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				wp_send_json_error( array( 'code' => 'challenge_invalidated_by_safe_mode', 'message' => 'This passkey challenge was invalidated by Safe Mode. Start again.' ), 409 );
			}
			wp_die( esc_html__( 'This passkey challenge was invalidated by Safe Mode. Start again.', 'sabri-authentication' ), esc_html__( 'Passkey challenge invalid', 'sabri-authentication' ), array( 'response' => 409 ) );
		}
	}
}

SAUTH_Safe_Mode_Challenge_Gate::init();
