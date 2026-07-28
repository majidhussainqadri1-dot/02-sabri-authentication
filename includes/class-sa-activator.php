<?php

defined( 'ABSPATH' ) || exit;

final class SA_Activator {
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::register_roles();
		$page_map = array();
		foreach ( self::page_specs() as $key => $spec ) {
			$existing = get_page_by_path( $spec['slug'], OBJECT, 'page' );
			$tag = trim( $spec['shortcode'], '[]' );
			if ( $existing instanceof WP_Post && has_shortcode( $existing->post_content, $tag ) ) {
				$page_map[ $key ] = (int) $existing->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $spec['title'],
					'post_name'    => $spec['slug'],
					'post_content' => $spec['shortcode'],
					'meta_input'   => array( '_sa_managed_page' => '1' ),
				),
				true
			);
			if ( ! is_wp_error( $page_id ) ) {
				$page_map[ $key ] = (int) $page_id;
			}
		}

		update_option( 'sa_page_map', $page_map, false );
		add_option( 'sa_allow_registration', '1', '', false );
		add_option( 'sa_google_enabled', '0', '', false );
		update_option( 'sa_version', SA_VERSION, false );
		set_transient( 'sa_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function register_roles() {
		$roles = array(
			'sabri_member'         => 'General Member',
			'sabri_patient'        => 'Patient',
			'sabri_student'        => 'Student',
			'sabri_doctor_pending' => 'Doctor — Verification Pending',
		);
		foreach ( $roles as $slug => $label ) {
			if ( ! get_role( $slug ) ) {
				add_role( $slug, $label, array( 'read' => true ) );
			}
		}
	}

	public static function page_specs() {
		return array(
			'login'    => array( 'title' => 'Log In', 'slug' => 'account-login', 'shortcode' => '[sabri_auth_login]' ),
			'signup'   => array( 'title' => 'Create Account', 'slug' => 'create-account', 'shortcode' => '[sabri_auth_signup]' ),
			'complete' => array( 'title' => 'Complete Your Profile', 'slug' => 'complete-profile', 'shortcode' => '[sabri_auth_complete_profile]' ),
			'forgot'   => array( 'title' => 'Forgot Password', 'slug' => 'forgot-password', 'shortcode' => '[sabri_auth_forgot_password]' ),
			'access'   => array( 'title' => 'Account Access Required', 'slug' => 'account-access-required', 'shortcode' => '[sabri_auth_access_required]' ),
		);
	}
}
