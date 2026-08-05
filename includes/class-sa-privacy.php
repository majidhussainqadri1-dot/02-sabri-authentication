<?php

defined( 'ABSPATH' ) || exit;

final class SA_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy_content' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-authentication'] = array(
			'exporter_friendly_name' => 'Sabri Authentication, Sessions and Account Links',
			'callback'               => array( $this, 'export_data' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-authentication'] = array(
			'eraser_friendly_name' => 'Sabri Authentication Links and Projections',
			'callback'             => array( $this, 'erase_data' ),
		);
		return $erasers;
	}

	public function export_data( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$fields = array(
			'Google unique identifier'   => get_user_meta( $user->ID, '_sa_google_sub', true ),
			'Google link version'        => get_user_meta( $user->ID, '_sa_google_link_version', true ),
			'Google linked'              => get_user_meta( $user->ID, '_sa_google_account', true ),
			'Google account email'       => get_user_meta( $user->ID, '_sa_google_email', true ),
			'Google email verified'      => get_user_meta( $user->ID, '_sa_google_email_verified', true ),
			'Google profile image URL'   => get_user_meta( $user->ID, '_sa_google_picture', true ),
			'Google linked at'           => get_user_meta( $user->ID, '_sa_google_linked_at', true ),
			'Google last login at'       => get_user_meta( $user->ID, '_sa_google_last_login_at', true ),
			'Legacy account type'        => get_user_meta( $user->ID, '_sa_account_type', true ),
			'Legacy phone'               => get_user_meta( $user->ID, '_sa_phone', true ),
			'Legacy country'             => get_user_meta( $user->ID, '_sa_country', true ),
			'Legacy city'                => get_user_meta( $user->ID, '_sa_city', true ),
			'Legacy preferred language'  => get_user_meta( $user->ID, '_sa_preferred_language', true ),
			'Legacy profile complete'    => get_user_meta( $user->ID, '_sa_profile_complete', true ),
			'Legacy terms accepted at'   => get_user_meta( $user->ID, '_sa_terms_accepted_at', true ),
			'Legacy privacy accepted at' => get_user_meta( $user->ID, '_sa_privacy_accepted_at', true ),
			'Legacy WordPress biography' => (string) $user->description,
		);

		global $wpdb;
		$email_table = $wpdb->prefix . 'sa_email_verifications';
		if ( self::table_exists( $email_table ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, sent_at, expires_at, verified_at, created_at, updated_at FROM {$email_table} WHERE user_id = %d", $user->ID ), ARRAY_A );
			if ( is_array( $row ) ) {
				$fields['Local email-verification status']     = $row['status'];
				$fields['Verification email last sent at']     = $row['sent_at'];
				$fields['Verification challenge expires at']   = $row['expires_at'];
				$fields['Email locally verified at']           = $row['verified_at'];
				$fields['Verification challenge created at']   = $row['created_at'];
				$fields['Verification challenge last updated'] = $row['updated_at'];
			}
		}

		$data = array();
		foreach ( $fields as $name => $value ) {
			if ( '' !== (string) $value ) {
				$data[] = array( 'name' => $name, 'value' => $value );
			}
		}

		$items = array(
			array(
				'group_id'    => 'sabri-authentication',
				'group_label' => 'Sabri Authentication',
				'item_id'     => 'sabri-authentication-' . $user->ID,
				'data'        => $data,
			),
		);

		$session_table = $wpdb->prefix . 'sa_auth_sessions';
		if ( self::table_exists( $session_table ) ) {
			$sessions = $wpdb->get_results(
				$wpdb->prepare( "SELECT public_id, device_label, network_label, risk_level, status, created_at, last_seen_at, expires_at, revoked_at, revocation_reason FROM {$session_table} WHERE user_id = %d ORDER BY last_seen_at DESC LIMIT 100", $user->ID ),
				ARRAY_A
			);
			foreach ( is_array( $sessions ) ? $sessions : array() as $session ) {
				$items[] = array(
					'group_id'    => 'sabri-authentication-sessions',
					'group_label' => 'Sabri Authentication Sessions',
					'item_id'     => 'sauth-session-' . sanitize_text_field( $session['public_id'] ),
					'data'        => self::named_fields( $session ),
				);
			}
		}

		$device_table = $wpdb->prefix . 'sa_auth_devices';
		if ( self::table_exists( $device_table ) ) {
			$devices = $wpdb->get_results(
				$wpdb->prepare( "SELECT public_id, device_label, network_label, status, risk_score, first_seen_at, last_seen_at, last_login_at FROM {$device_table} WHERE user_id = %d ORDER BY last_seen_at DESC LIMIT 100", $user->ID ),
				ARRAY_A
			);
			foreach ( is_array( $devices ) ? $devices : array() as $device ) {
				$items[] = array(
					'group_id'    => 'sabri-authentication-devices',
					'group_label' => 'Sabri Authentication Trusted Devices',
					'item_id'     => 'sauth-device-' . sanitize_text_field( $device['public_id'] ),
					'data'        => self::named_fields( $device ),
				);
			}
		}

		return array( 'data' => $items, 'done' => true );
	}

	public function erase_data( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		$keys = array_merge(
			SA_Google_OAuth::google_meta_keys(),
			array( '_sa_phone', '_sa_country', '_sa_city', '_sa_account_type', '_sa_preferred_language', '_sa_profile_complete', '_sa_terms_accepted_at', '_sa_privacy_accepted_at' )
		);
		foreach ( array_unique( $keys ) as $key ) {
			delete_user_meta( $user->ID, $key );
		}
		if ( '' !== (string) $user->description ) {
			wp_update_user( array( 'ID' => $user->ID, 'description' => '' ) );
		}

		global $wpdb;
		foreach ( array( 'sa_email_verifications', 'sa_auth_sessions', 'sa_auth_devices', 'sa_auth_risk_challenges' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			if ( self::table_exists( $table ) ) {
				$wpdb->delete( $table, array( 'user_id' => $user->ID ), array( '%d' ) );
			}
		}

		$attempt_table = $wpdb->prefix . 'sa_auth_attempts';
		if ( self::table_exists( $attempt_table ) ) {
			$wpdb->update(
				$attempt_table,
				array( 'user_id' => 0, 'fingerprint_hash' => str_repeat( '0', 64 ), 'network_hash' => str_repeat( '0', 64 ), 'reason_code' => 'privacy_anonymized' ),
				array( 'user_id' => $user->ID ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		SAUTH_Session_Manager::revoke_user_sessions( $user->ID, 'privacy_erasure' );
		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array(
				'File 02 email challenges, Google link metadata, session projections, trusted-device projections, pending risk challenges and legacy File 02 fields were removed.',
				'Short-lived security-attempt records were anonymized rather than deleted so bounded abuse-defense evidence cannot be re-associated with the account.',
				'The WordPress account and Membership Core identity, roles, guardian, verification, institutional and audit records remain under their canonical privacy procedures.',
			),
			'done' => true,
		);
	}

	public function policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Sabri Authentication and Accounts',
			'<p class="privacy-policy-tutorial">This module may temporarily store one-way hashes for email challenges and session/device bindings, generalized device and network labels, bounded risk scores, expiry/status timestamps, Google account-link metadata and privacy-minimized authentication events. Raw verification tokens, passwords, reset keys, raw session tokens, full IP addresses, Google access/refresh tokens, TOTP secrets and recovery codes are not retained by File 02. Membership identity, guardian, roles and institutional verification remain under Sabri Membership Core. Session projections and inactive-device data are rotated, and privacy erasure deletes or anonymizes File 02-owned projections subject to bounded security-retention duties.</p>'
		);
	}

	private static function named_fields( array $row ) {
		$data = array();
		foreach ( $row as $name => $value ) {
			if ( '' !== (string) $value && null !== $value ) {
				$data[] = array( 'name' => ucwords( str_replace( '_', ' ', $name ) ), 'value' => (string) $value );
			}
		}
		return $data;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$like = $wpdb->esc_like( $table );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}
}
