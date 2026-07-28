<?php

defined( 'ABSPATH' ) || exit;

final class SA_Profile {
	public function hooks() {
		add_action( 'admin_post_sa_complete_profile', array( $this, 'save' ) );
	}

	public function save() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sa_complete_profile', 'sa_nonce' );

		$user_id  = get_current_user_id();
		$name     = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$country  = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$city     = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$type     = isset( $_POST['account_type'] ) ? sanitize_key( $_POST['account_type'] ) : 'member';
		$bio      = isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '';
		$language = isset( $_POST['preferred_language'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_language'] ) ) : 'English (US)';

		if ( '' === $name || '' === $phone || '' === $country || '' === $city || empty( $_POST['privacy'] ) ) {
			wp_safe_redirect( SA_Security::message_url( 'complete', 'error', 'Please complete all required fields and privacy consent.' ) );
			exit;
		}

		$role_map = array( 'member' => 'sabri_member', 'patient' => 'sabri_patient', 'student' => 'sabri_student', 'doctor' => 'sabri_doctor_pending' );
		$role = isset( $role_map[ $type ] ) ? $role_map[ $type ] : 'sabri_member';
		$user = new WP_User( $user_id );
		if ( ! user_can( $user, 'manage_options' ) ) {
			$user->set_role( $role );
		}

		wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'description' => $bio ) );
		update_user_meta( $user_id, '_sa_phone', $phone );
		update_user_meta( $user_id, '_sa_country', $country );
		update_user_meta( $user_id, '_sa_city', $city );
		update_user_meta( $user_id, '_sa_account_type', $type );
		update_user_meta( $user_id, '_sa_preferred_language', $language );
		update_user_meta( $user_id, '_sa_profile_complete', '1' );
		update_user_meta( $user_id, '_sa_privacy_accepted_at', current_time( 'mysql', true ) );

		wp_safe_redirect( SA_Security::message_url( 'complete', 'success', 'Your profile has been updated.' ) );
		exit;
	}
}

