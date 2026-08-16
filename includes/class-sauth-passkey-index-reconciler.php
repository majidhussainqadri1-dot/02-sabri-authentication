<?php

defined( 'ABSPATH' ) || exit;

/**
 * Narrow, idempotent repair for passkey indexes that older File 02 builds left
 * in a shape that dbDelta does not contract back to the canonical definition.
 *
 * This class is intentionally bounded to exact, previously deployed shapes.
 * Unknown index layouts fail closed instead of being rewritten heuristically.
 */
final class SAUTH_Passkey_Index_Reconciler {
	const TABLE_SUFFIX = 'sauth_passkeys';

	/**
	 * Repair the exact stale live `user_status(user_id,status,updated_at)` index.
	 *
	 * Fresh installs (table absent) and already-canonical installs are no-ops.
	 * Any unexpected shape is rejected so activation/upgrade remains retryable.
	 */
	public static function repair() {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE_SUFFIX;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $table !== (string) $exists ) {
			return '' === (string) $wpdb->last_error;
		}

		$indexes = self::read_indexes( $table );
		if ( false === $indexes ) {
			return false;
		}

		/* If the key does not exist, the owning passkey installer/dbDelta may
		 * create the canonical key. This reconciler only contracts a known stale
		 * shape; it does not become a second schema owner. */
		if ( ! isset( $indexes['user_status'] ) ) {
			return true;
		}

		$current = $indexes['user_status'];
		if ( 1 === (int) $current['non_unique'] && array( 'user_id', 'status' ) === $current['columns'] ) {
			return true;
		}

		$known_stale = 1 === (int) $current['non_unique']
			&& array( 'user_id', 'status', 'updated_at' ) === $current['columns'];
		if ( ! $known_stale ) {
			return false;
		}

		$sql = "ALTER TABLE `{$table}` DROP INDEX `user_status`, ADD KEY `user_status` (`user_id`,`status`)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $wpdb->query( $sql ) ) {
			return false;
		}

		$verified = self::read_indexes( $table );
		if ( false === $verified || ! isset( $verified['user_status'] ) ) {
			return false;
		}
		$canonical = $verified['user_status'];
		return 1 === (int) $canonical['non_unique']
			&& array( 'user_id', 'status' ) === $canonical['columns'];
	}

	/**
	 * @return array<string,array{non_unique:int,columns:array<int,string>}>|false
	 */
	private static function read_indexes( $table ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return false;
		}

		$indexes = array();
		foreach ( $rows as $row ) {
			$name = (string) ( $row['Key_name'] ?? '' );
			$seq  = absint( $row['Seq_in_index'] ?? 0 );
			if ( '' === $name || $seq < 1 ) {
				continue;
			}
			if ( ! isset( $indexes[ $name ] ) ) {
				$indexes[ $name ] = array(
					'non_unique' => (int) ( $row['Non_unique'] ?? 1 ),
					'columns'    => array(),
				);
			}
			$indexes[ $name ]['columns'][ $seq ] = (string) ( $row['Column_name'] ?? '' );
		}
		foreach ( $indexes as &$index ) {
			ksort( $index['columns'] );
			$index['columns'] = array_values( $index['columns'] );
		}
		unset( $index );
		return $indexes;
	}
}
