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
			'exporter_friendly_name' => 'Sabri Google Authentication Link',
			'callback'               => array( $this, 'export_data' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-authentication'] = array(
			'eraser_friendly_name' => 'Sabri Google Authentication Link and Legacy File 02 Data',
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
			'Google unique identifier' => get_user_meta( $user->ID, '_sa_google_sub', true ),
			'Google link version'      => get_user_meta( $user->ID, '_sa_google_link_version', true ),
			'Google linked'            => get_user_meta( $user->ID, '_sa_google_account', true ),
			'Google account email'     => get_user_meta( $user->ID, '_sa_google_email', true ),
			'Google email verified'    => get_user_meta( $user->ID, '_sa_google_email_verified', true ),
			'Google profile image URL' => get_user_meta( $user->ID, '_sa_google_picture', true ),
			'Google linked at'         => get_user_meta( $user->ID, '_sa_google_linked_at', true ),
			'Google last login at'     => get_user_meta( $user->ID, '_sa_google_last_login_at', true ),
			'Legacy account type'      => get_user_meta( $user->ID, '_sa_account_type', true ),
			'Legacy phone'             => get_user_meta( $user->ID, '_sa_phone', true ),
			'Legacy country'           => get_user_meta( $user->ID, '_sa_country', true ),
			'Legacy city'              => get_user_meta( $user->ID, '_sa_city', true ),
			'Legacy preferred language'=> get_user_meta( $user->ID, '_sa_preferred_language', true ),
			'Legacy profile complete'  => get_user_meta( $user->ID, '_sa_profile_complete', true ),
			'Legacy terms accepted at' => get_user_meta( $user->ID, '_sa_terms_accepted_at', true ),
			'Legacy privacy accepted at'=> get_user_meta( $user->ID, '_sa_privacy_accepted_at', true ),
			'Legacy WordPress biography' => (string) $user->description,
		);

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

		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array(
				'The WordPress account and Membership Core identity, role, verification, and institutional records are retained and must be handled through their respective privacy and deletion procedures.',
			),
			'done'           => true,
		);
	}

	public function policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Sabri Google Authentication',
			'<p class="privacy-policy-tutorial">This module may store a Google unique account identifier, the matching verified Google email address, an optional Google profile-image URL, and link/login timestamps. Google access and refresh tokens are not retained. New membership registration, identity documents, roles, verification status, and profile data remain under Sabri Membership Core. Legacy File 02 contact metadata may be exported or erased through the WordPress privacy tools.</p>'
		);
	}
}
