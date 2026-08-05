<?php

defined( 'ABSPATH' ) || exit;

final class SA_Activator {
	public static function activate() {
		if ( ! SA_Membership_Adapter::plugin_active() ) {
			deactivate_plugins( plugin_basename( SA_FILE ) );
			wp_die(
				esc_html__( 'Sabri Authentication requires File 00 — Sabri Membership Core 1.2.7 or later with the approved assurance contract. No account, role, guardian or verification authority will be created independently.', 'sabri-authentication' ),
				esc_html__( 'Required dependency missing', 'sabri-authentication' ),
				array( 'back_link' => true )
			);
		}

		self::create_rate_limit_table();
		self::create_auth_outbox_table();
		self::create_pages();
		self::migrate_google_secret();

		add_option( 'sa_google_enabled', '0', '', false );
		add_option( 'sa_google_client_id', '', '', false );
		update_option( 'sa_version', SA_VERSION, false );
		update_option( 'sa_db_version', SA_DB_VERSION, false );
		set_transient( 'sa_activation_notice', '1', 120 );
		flush_rewrite_rules( false );
	}

	public static function deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) && class_exists( 'SAUTH_Event_Outbox' ) ) {
			wp_clear_scheduled_hook( SAUTH_Event_Outbox::CRON_HOOK );
		}
		flush_rewrite_rules( false );
	}

	public static function maybe_upgrade() {
		if ( SA_DB_VERSION !== (string) get_option( 'sa_db_version', '' ) ) {
			self::create_rate_limit_table();
			self::create_auth_outbox_table();
			self::create_pages();
			self::migrate_google_secret();
			update_option( 'sa_version', SA_VERSION, false );
			update_option( 'sa_db_version', SA_DB_VERSION, false );
		}
	}

	public static function create_rate_limit_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . 'sa_rate_limits';
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			bucket_hash char(64) NOT NULL,
			hits int unsigned NOT NULL DEFAULT 0,
			window_started datetime NOT NULL,
			expires_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (bucket_hash),
			KEY expires_at (expires_at)
		) {$collate};";
		dbDelta( $sql );
	}

	public static function create_auth_outbox_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $wpdb->prefix . 'sa_auth_outbox';
		$collate = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			event_id char(36) NOT NULL,
			event_name varchar(100) NOT NULL,
			schema_version varchar(20) NOT NULL,
			privacy_class varchar(20) NOT NULL DEFAULT 'restricted',
			actor_user_id bigint unsigned NOT NULL DEFAULT 0,
			subject_user_id bigint unsigned NOT NULL DEFAULT 0,
			trace_id varchar(64) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint unsigned NOT NULL DEFAULT 0,
			available_at datetime NOT NULL,
			published_at datetime NULL,
			last_error varchar(500) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY dispatch_due (status, available_at, id),
			KEY subject_event (subject_user_id, event_name, created_at),
			KEY trace_id (trace_id)
		) {$collate};";
		dbDelta( $sql );
	}

	private static function create_pages() {
		$known    = (array) get_option( 'sa_page_map', array() );
		$page_map = array();
		foreach ( self::page_specs() as $key => $spec ) {
			$page_map[ $key ] = self::ensure_owned_page( $spec, isset( $known[ $key ] ) ? absint( $known[ $key ] ) : 0 );
		}
		update_option( 'sa_page_map', array_filter( array_map( 'absint', $page_map ) ), false );
	}

	private static function exact_shortcode_page( $post, $shortcode ) {
		return $post instanceof WP_Post && trim( (string) $post->post_content ) === $shortcode;
	}

	private static function update_owned_page( WP_Post $page, array $spec ) {
		$result = wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_status'  => 'publish',
				'post_title'   => $spec['title'],
				'post_content' => $spec['shortcode'],
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return 0;
		}
		update_post_meta( $page->ID, '_sa_managed_page', '1' );
		update_post_meta( $page->ID, '_sa_private_page', '1' );
		return (int) $page->ID;
	}

	private static function ensure_owned_page( array $spec, $known_id = 0 ) {
		if ( $known_id ) {
			$known = get_post( $known_id );
			if ( $known instanceof WP_Post && 'page' === $known->post_type && '1' === (string) get_post_meta( $known->ID, '_sa_managed_page', true ) ) {
				return self::update_owned_page( $known, $spec );
			}
		}

		$managed = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 20,
				'meta_key'       => '_sa_managed_page',
				'meta_value'     => '1',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $managed as $page ) {
			if ( self::exact_shortcode_page( $page, $spec['shortcode'] ) || has_shortcode( (string) $page->post_content, trim( $spec['shortcode'], '[]' ) ) ) {
				return self::update_owned_page( $page, $spec );
			}
		}

		foreach ( array( $spec['slug'], $spec['slug'] . '-sa' ) as $candidate_slug ) {
			$existing = get_page_by_path( $candidate_slug, OBJECT, 'page' );
			if ( $existing instanceof WP_Post ) {
				$owned = '1' === (string) get_post_meta( $existing->ID, '_sa_managed_page', true );
				if ( $owned || self::exact_shortcode_page( $existing, $spec['shortcode'] ) ) {
					return self::update_owned_page( $existing, $spec );
				}
			}
		}

		$preferred = get_page_by_path( $spec['slug'], OBJECT, 'page' );
		$slug      = $preferred instanceof WP_Post ? $spec['slug'] . '-sa' : $spec['slug'];
		$page      = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $spec['title'],
				'post_name'    => $slug,
				'post_content' => $spec['shortcode'],
				'meta_input'   => array(
					'_sa_managed_page' => '1',
					'_sa_private_page' => '1',
				),
			),
			true
		);
		return is_wp_error( $page ) ? 0 : (int) $page;
	}

	private static function migrate_google_secret() {
		$cipher = (string) get_option( 'sa_google_client_secret', '' );
		if ( '' === $cipher || 0 === strpos( $cipher, 'v2:' ) ) {
			return;
		}
		$plain = SA_Security::decrypt( $cipher );
		if ( '' !== $plain ) {
			$encrypted = SA_Security::encrypt( $plain );
			if ( '' !== $encrypted ) {
				update_option( 'sa_google_client_secret', $encrypted, false );
			}
		}
	}

	/**
	 * Kept only for backward compatibility. File 00 is the exclusive role owner.
	 */
	public static function register_roles() {
		return false;
	}

	public static function page_specs() {
		return array(
			'login'          => array( 'title' => 'Secure Log In', 'slug' => 'login', 'shortcode' => '[sabri_auth_login]' ),
			'signup'         => array( 'title' => 'Create Verified Account', 'slug' => 'register', 'shortcode' => '[sabri_auth_signup]' ),
			'complete'       => array( 'title' => 'Complete Verified Profile', 'slug' => 'complete-profile', 'shortcode' => '[sabri_auth_complete_profile]' ),
			'forgot'         => array( 'title' => 'Forgot Password', 'slug' => 'forgot-password', 'shortcode' => '[sabri_auth_forgot_password]' ),
			'reset'          => array( 'title' => 'Reset Password', 'slug' => 'reset-password', 'shortcode' => '[sabri_auth_reset_password]' ),
			'sessions'       => array( 'title' => 'Account Sessions', 'slug' => 'account-sessions', 'shortcode' => '[sabri_auth_sessions]' ),
			'access'         => array( 'title' => 'Account Access Required', 'slug' => 'account-access-required', 'shortcode' => '[sabri_auth_access_required]' ),
			'google_account' => array( 'title' => 'Google Account Security', 'slug' => 'google-account-security', 'shortcode' => '[sabri_auth_google_account]' ),
			'google_verify'  => array( 'title' => 'Verify Google Sign-In', 'slug' => 'google-signin-verification', 'shortcode' => '[sabri_auth_google_verify]' ),
		);
	}
}
