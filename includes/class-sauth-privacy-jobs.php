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
	const EPOCH_META = '_sauth_privacy_job_epoch';
	const ACTIVE_META = '_sauth_privacy_erasure_active';
	const JOB_INDEX_META = '_sauth_privacy_job_keys';
	const MAX_INDEXED_JOBS = 30;

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

	/** Record an opaque transient key so privacy erasure can delete queued work immediately. */
	public static function register_job( $user_id, $job_key ) {
		$user_id = absint( $user_id );
		$job_key = sanitize_key( (string) $job_key );
		if ( ! self::can_enqueue( $user_id ) || '' === $job_key ) {
			return false;
		}
		$keys = get_user_meta( $user_id, self::JOB_INDEX_META, true );
		$keys = is_array( $keys ) ? $keys : array();
		$keys[] = $job_key;
		$keys = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $keys ) ) ) ), -self::MAX_INDEXED_JOBS );
		update_user_meta( $user_id, self::JOB_INDEX_META, $keys );
		return $keys === get_user_meta( $user_id, self::JOB_INDEX_META, true );
	}

	public static function forget_job( $user_id, $job_key ) {
		$user_id = absint( $user_id );
		$job_key = sanitize_key( (string) $job_key );
		if ( ! $user_id || '' === $job_key ) {
			return;
		}
		$keys = get_user_meta( $user_id, self::JOB_INDEX_META, true );
		if ( ! is_array( $keys ) ) {
			return;
		}
		$keys = array_values( array_diff( array_map( 'sanitize_key', $keys ), array( $job_key ) ) );
		if ( empty( $keys ) ) {
			delete_user_meta( $user_id, self::JOB_INDEX_META );
		} else {
			update_user_meta( $user_id, self::JOB_INDEX_META, $keys );
		}
	}

	public static function purge_jobs( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		$keys = get_user_meta( $user_id, self::JOB_INDEX_META, true );
		$keys = is_array( $keys ) ? array_slice( array_values( array_unique( array_map( 'sanitize_key', $keys ) ) ), 0, self::MAX_INDEXED_JOBS ) : array();
		foreach ( $keys as $key ) {
			if ( '' !== $key ) {
				delete_transient( $key );
			}
		}
		delete_user_meta( $user_id, self::JOB_INDEX_META );
		return '' === (string) get_user_meta( $user_id, self::JOB_INDEX_META, true );
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
		if ( ! hash_equals( $epoch, (string) get_user_meta( $user_id, self::EPOCH_META, true ) ) ) {
			return false;
		}
		return self::purge_jobs( $user_id );
	}

	/** Clear the barrier only after every File 02 erasure postcondition passes. */
	public static function finish_erasure( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		delete_user_meta( $user_id, self::EPOCH_META );
		delete_user_meta( $user_id, self::JOB_INDEX_META );
		delete_user_meta( $user_id, self::ACTIVE_META );
		return '' === (string) get_user_meta( $user_id, self::ACTIVE_META, true )
			&& '' === (string) get_user_meta( $user_id, self::EPOCH_META, true );
	}
}
