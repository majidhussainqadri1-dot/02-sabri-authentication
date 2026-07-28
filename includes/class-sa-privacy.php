<?php

defined( 'ABSPATH' ) || exit;

final class SA_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy_content' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-authentication'] = array( 'exporter_friendly_name' => 'Sabri Account Profile', 'callback' => array( $this, 'export_data' ) );
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-authentication'] = array( 'eraser_friendly_name' => 'Sabri Account Profile', 'callback' => array( $this, 'erase_data' ) );
		return $erasers;
	}

	public function export_data( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$fields = array(
			'Account type'       => get_user_meta( $user->ID, '_sa_account_type', true ),
			'Phone'              => get_user_meta( $user->ID, '_sa_phone', true ),
			'Country'            => get_user_meta( $user->ID, '_sa_country', true ),
			'City'               => get_user_meta( $user->ID, '_sa_city', true ),
			'Preferred language' => get_user_meta( $user->ID, '_sa_preferred_language', true ),
			'Google linked'      => get_user_meta( $user->ID, '_sa_google_account', true ),
		);
		$data = array();
		foreach ( $fields as $name => $value ) { if ( '' !== $value ) { $data[] = array( 'name' => $name, 'value' => $value ); } }
		return array( 'data' => array( array( 'group_id' => 'sabri-account', 'group_label' => 'Sabri Account Profile', 'item_id' => 'sabri-account-' . $user->ID, 'data' => $data ) ), 'done' => true );
	}

	public function erase_data( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$keys = array( '_sa_phone','_sa_country','_sa_city','_sa_preferred_language','_sa_google_sub','_sa_google_picture','_sa_google_email_verified','_sa_google_account','_sa_terms_accepted_at','_sa_privacy_accepted_at' );
		foreach ( $keys as $key ) { delete_user_meta( $user->ID, $key ); }
		return array( 'items_removed' => true, 'items_retained' => true, 'messages' => array( 'The WordPress user account is retained until the administrator approves account deletion.' ), 'done' => true );
	}

	public function policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { return; }
		wp_add_privacy_policy_content( 'Sabri Authentication and Google Sign-In', '<p class="privacy-policy-tutorial">This module processes account name, email, Google unique account identifier, optional profile image, account type, phone, country and city to authenticate users and provide requested account services. Google access and refresh tokens are not retained. Google account data is not sold, used for advertising profiles, or used to train artificial intelligence models. Users may request export or erasure through the site privacy process.</p>' );
	}
}

