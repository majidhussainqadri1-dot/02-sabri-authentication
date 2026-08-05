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
			'exporter_friendly_name' => 'Sabri Authentication and Account Links',
			'callback'               => array( $this, 'export_data' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-authentication'] = array(
			'eraser_friendly_name' => 'Sabri Authentication Links and Legacy File 02 Data',
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
		$table = $wpdb->prefix . 'sa_email_verifications';
		if ( self::table_exists( $table ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT status, sent_at, expires_at, verified_at, created_at, updated_at FROM {$table} WHERE user_id = %d", $user->ID ),
				ARRAY_A
			);
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

		return array(
			'data' => array(
				array(
					'group_id'    => 'sabri-authentication',
					'group_label' => 'Sabri Authentication',
					'item_id'     => 'sabri-authentication-' . $user->ID,
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	public function erase_data( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		$keys = array_merge(
			SA_Google_OAuth::google_meta_keys(),
			array(
				'_sa_phone',
				'_sa_country',
				'_sa_city',
				'_sa_account_type',
				'_sa_preferred_language',
				'_sa_profile_complete',
				'_sa_terms_accepted_at',
				'_sa_privacy_accepted_at',
			)
		);
		foreach ( array_unique( $keys ) as $key ) {
			delete_user_meta( $user->ID, $key );
		}
		if ( '' !== (string) $user->description ) {
			wp_update_user( array( 'ID' => $user->ID, 'description' => '' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'sa_email_verifications';
		if ( self::table_exists( $table ) ) {
			$wpdb->delete( $table, array( 'user_id' => $user->ID ), array( '%d' ) );
		}

		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array(
				'The local File 02 verification challenge, Google link metadata and legacy File 02 fields were removed.',
				'The WordPress account and Membership Core identity, role, verification, guardian, institutional and audit records are retained and must be handled through their respective privacy and deletion procedures.',
			),
			'done'           => true,
		);
	}

	public function policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Sabri Authentication and Accounts',
			'<p class="privacy-policy-tutorial">This module may temporarily store a one-way hash of a one-time email-verification token, an HMAC of the target email, delivery and expiry timestamps, verification status, a Google unique account identifier, the matching verified Google email address, an optional Google profile-image URL, and link/login timestamps. Raw verification tokens, passwords, Google access tokens, Google refresh tokens, TOTP secrets and recovery codes are not retained by File 02. Membership identity, guardian, roles and institutional verification remain under Sabri Membership Core.</p>'
		);
	}

	private static function table_exists( $table ) {
		global $wpdb;
		$like = $wpdb->esc_like( $table );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}
}
