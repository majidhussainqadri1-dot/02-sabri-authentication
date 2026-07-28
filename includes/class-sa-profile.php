<?php

defined( 'ABSPATH' ) || exit;

/**
 * Legacy profile route. File 00 exclusively owns identity and role changes.
 */
final class SA_Profile {
	public function hooks() {
		add_action( 'admin_post_sa_complete_profile', array( $this, 'save' ) );
	}

	public function save() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sa_complete_profile', 'sa_nonce' );
		wp_safe_redirect( SA_Membership_Adapter::profile_url() );
		exit;
	}
}
