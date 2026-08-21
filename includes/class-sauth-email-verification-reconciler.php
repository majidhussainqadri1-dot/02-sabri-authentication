<?php

defined( 'ABSPATH' ) || exit;

/**
 * Reconcile historical File 02 signed-link completion evidence into File 00.
 *
 * File 02 1.3.3 can contain a canonical local row that proves a signed email
 * link was consumed while an older File 00 provider stored only verified_at.
 * This reconciler is deliberately narrow: it runs only after File 00 itself
 * reports email as missing and only when File 02 still has the exact durable
 * completion shape produced by a successful signed-link flow.
 */
final class SAUTH_Email_Verification_Reconciler {
	/**
	 * Return a refreshed File 00 completion state when reconciliation succeeds.
	 * Otherwise preserve the original provider state without inventing truth.
	 *
	 * @param int                 $user_id WordPress user ID.
	 * @param array<string,mixed> $state   File 00 completion state.
	 * @return array<string,mixed>
	 */
	public static function reconcile_if_needed( $user_id, array $state ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || 'allow' !== (string) ( $state['result'] ?? '' ) ) {
			return $state;
		}

		$missing = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', (array) ( $state['missing_steps'] ?? array() ) )
				)
			)
		);
		if ( ! in_array( 'email', $missing, true ) ) {
			return $state;
		}
		if ( ! class_exists( 'SAUTH_Activator' ) || ! SAUTH_Account_Contract::provider_available() ) {
			return $state;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! is_email( (string) $user->user_email ) ) {
			return $state;
		}

		global $wpdb;
		$table = (string) SAUTH_Activator::table( 'email_verifications' );
		if ( '' === $table ) {
			return $state;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT email_hash,token_hash,status,attempts,verified_at FROM {$table} WHERE user_id=%d LIMIT 1",
				$user_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) || '' !== (string) $wpdb->last_error ) {
			return $state;
		}

		$verified_at = trim( (string) ( $row['verified_at'] ?? '' ) );
		$email_hash  = (string) ( $row['email_hash'] ?? '' );
		$token_hash  = (string) ( $row['token_hash'] ?? '' );
		$expected_email_hash = hash_hmac(
			'sha256',
			strtolower( trim( (string) $user->user_email ) ),
			wp_salt( 'secure_auth' )
		);

		/* This is the exact durable success shape written by
		 * SAUTH_Email_Verification after a signed link is claimed and the File 00
		 * handoff returns allow. Do not reconcile weaker historical signals. */
		if ( 'verified' !== (string) ( $row['status'] ?? '' )
			|| '' === $verified_at
			|| absint( $row['attempts'] ?? 0 ) < 1
			|| ! hash_equals( str_repeat( '0', 64 ), $token_hash )
			|| ! hash_equals( $expected_email_hash, $email_hash ) ) {
			return $state;
		}

		$idempotency_key = 'legacy-email-reconcile-' . $user_id . '-' . substr(
			hash( 'sha256', $user_id . '|' . $email_hash . '|' . $verified_at ),
			0,
			32
		);

		try {
			$provider = SAUTH_Account_Contract::mark_email_verified(
				$user_id,
				$user->user_email,
				array(
					'purpose'         => 'email_verification',
					'idempotency_key' => $idempotency_key,
				)
			);
		} catch ( Throwable $error ) {
			$provider = array( 'result' => 'unknown', 'reason_code' => 'provider_exception' );
		}

		if ( 'allow' !== (string) ( $provider['result'] ?? '' ) ) {
			SA_Membership_Adapter::audit(
				'email_verification_legacy_reconciliation_failed',
				$user_id,
				array( 'reason_code' => sanitize_key( (string) ( $provider['reason_code'] ?? 'provider_unknown' ) ) )
			);
			return $state;
		}

		$refreshed = SAUTH_Account_Contract::completion_state(
			$user_id,
			array( 'purpose' => 'post_authentication_completion' )
		);
		$refreshed_missing = is_array( $refreshed )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $refreshed['missing_steps'] ?? array() ) ) ) ) )
			: array( 'email' );

		if ( ! is_array( $refreshed )
			|| 'allow' !== (string) ( $refreshed['result'] ?? '' )
			|| in_array( 'email', $refreshed_missing, true ) ) {
			SA_Membership_Adapter::audit(
				'email_verification_legacy_reconciliation_failed',
				$user_id,
				array( 'reason_code' => 'completion_email_still_missing' )
			);
			return $state;
		}

		SA_Membership_Adapter::audit(
			'email_verification_legacy_reconciled',
			$user_id,
			array( 'evidence_kind' => 'file02_signed_link_completion' )
		);
		return $refreshed;
	}
}
