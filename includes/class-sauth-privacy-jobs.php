<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 privacy-lifecycle guard for short-lived asynchronous account jobs.
 *
 * Recovery/resend workers carry only the opaque epoch captured when queued.
 * Privacy erasure rotates the epoch before deleting File 02 data and raises a
 * fail-closed barrier, so a worker queued before erasure cannot recreate state
 * or send account-recovery material after erasure begins.
 */
final class SAUTH_Privacy_Jobs {
	const EPOCH_META  = '_sauth_privacy_job_epoch';
	const ACTIVE_META = '_sauth_privacy_erasure_active';

	public static function can_enqueue( $user_id ) {
		$user_id = absint( $user_id );
		return $user_id > 0 && '1' !== (string) get_user_meta( $user_id, self::ACTIVE_META, true );
	}

	public static function snapshot( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! self::can_enqueue( $user_id ) ) {
			return '';
		}
		$epoch = (string) get_user_meta( $user_id, self::EPOCH_META, true );
		if ( '' !== $epoch ) {
			return $epoch;
		}
		$epoch = SA_Security::random_token( 16 );
		if ( '' === $epoch ) {
			return '';
		}
		if ( ! add_user_meta( $user_id, self::EPOCH_META, $epoch, true ) ) {
			$epoch = (string) get_user_meta( $user_id, self::EPOCH_META, true );
		}
		return '' !== $epoch ? $epoch : '';
	}

	public static function valid_snapshot( $user_id, $epoch ) {
		$user_id = absint( $user_id );
		$epoch   = (string) $epoch;
		if ( ! self::can_enqueue( $user_id ) || '' === $epoch ) {
			return false;
		}
		$current = (string) get_user_meta( $user_id, self::EPOCH_META, true );
		return '' !== $current && hash_equals( $current, $epoch );
	}

	/** Start erasure before any File 02 deletion. Leaves the barrier raised on failure. */
	public static function begin_erasure( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		update_user_meta( $user_id, self::ACTIVE_META, '1' );
		if ( '1' !== (string) get_user_meta( $user_id, self::ACTIVE_META, true ) ) {
			return false;
		}
		$epoch = SA_Security::random_token( 16 );
		if ( '' === $epoch ) {
			return false;
		}
		update_user_meta( $user_id, self::EPOCH_META, $epoch );
		return hash_equals( $epoch, (string) get_user_meta( $user_id, self::EPOCH_META, true ) );
	}

	/** Clear the barrier only after every File 02 erasure postcondition passes. */
	public static function finish_erasure( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		delete_user_meta( $user_id, self::ACTIVE_META );
		return '' === (string) get_user_meta( $user_id, self::ACTIVE_META, true );
	}
}
