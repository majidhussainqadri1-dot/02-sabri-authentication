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
		self::repair();
		add_option( 'sa_google_enabled', '0', '', false );
		add_option( 'sa_google_client_id', '', '', false );
		add_option( SAUTH_Operations::SAFE_MODE_OPTION, '0', '', false );
		set_transient( 'sa_activation_notice', '1', 120 );
		flush_rewrite_rules( false );
	}

	public static function deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			foreach ( array(
				SAUTH_Event_Outbox::CRON_HOOK,
				SAUTH_Email_Verification::CLEANUP_HOOK,
				SAUTH_Session_Manager::CLEANUP_HOOK,
				'sauth_login_risk_cleanup',
				'sauth_provider_health_cleanup',
			) as $hook ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
		flush_rewrite_rules( false );
	}

	public static function maybe_upgrade() {
		if ( SA_DB_VERSION !== (string) get_option( 'sa_db_version', '' ) || SA_VERSION !== (string) get_option( 'sa_version', '' ) ) {
			self::repair();
		}
	}

	/**
	 * Idempotent File 02-only repair/migration entry point.
	 */
	public static function repair() {
		self::create_rate_limit_table();
		self::create_auth_outbox_table();
		self::create_email_verification_table();
		self::create_session_table();
		self::create_device_table();
		self::create_risk_challenge_table();
		self::create_attempt_table();
		self::create_pages();
		self::migrate_google_secret();
		self::ensure_dummy_password_hash();
		update_option( 'sa_version', SA_VERSION, false );
		update_option( 'sa_db_version', SA_DB_VERSION, false );
		return true;
	}

	/**
	 * @return array<string,string>
	 */
	public static function required_tables() {
		global $wpdb;
		return array(
			'rate limits'        => $wpdb->prefix . 'sa_rate_limits',
			'event outbox'       => $wpdb->prefix . 'sa_auth_outbox',
			'email verification' => $wpdb->prefix . 'sa_email_verifications',
			'session registry'   => $wpdb->prefix . 'sa_auth_sessions',
			'trusted devices'    => $wpdb->prefix . 'sa_auth_devices',
			'risk challenges'    => $wpdb->prefix . 'sa_auth_risk_challenges',
			'auth attempts'      => $wpdb->prefix . 'sa_auth_attempts',
		);
	}

	public static function create_rate_limit_table() {
		global $wpdb;
		self::dbdelta( "CREATE TABLE {$wpdb->prefix}sa_rate_limits (
			bucket_hash char(64) NOT NULL,
			hits int unsigned NOT NULL DEFAULT 0,
			window_started datetime NOT NULL,
			expires_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (bucket_hash),
			KEY expires_at (expires_at)
		) " . $wpdb->get_charset_collate() . ';' );
	}

	public static function create_auth_outbox_table() {
		global $wpdb;
		self::dbdelta( "CREATE TABLE {$wpdb->prefix}sa_auth_outbox (
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
		) " . $wpdb->get_charset_collate() . ';' );
	}

	public static function create_email_verification_table() {
		global $wpdb;
		self::dbdelta( "CREATE TABLE {$wpdb->prefix}sa_email_verifications (
			user_id bigint unsigned NOT NULL,
			email_hash char(64) NOT NULL,
			token_hash char(64) NOT NULL,
			status varchar(24) NOT NULL DEFAULT 'pending',
			attempts smallint unsigned NOT NULL DEFAULT 0,
			sent_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			verified_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (user_id),
			KEY expiry_status (status, expires_at),
			KEY email_hash (email_hash)
		) " . $wpdb->get_charset_collate() . ';' );
	}

	public static function create_session_table() {
		global $wpdb;
		self::dbdelta( "CREATE TABLE {$wpdb->prefix}sa_auth_sessions (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			user_id bigint unsigned NOT NULL,
			token_hash char(64) NOT NULL,
			device_hash char(64) NOT NULL,
			device_label varchar(120) NOT NULL DEFAULT '',
			network_label varchar(80) NOT NULL DEFAULT 'Private',
			risk_level varchar(12) NOT NULL DEFAULT 'low',
			status varchar(16) NOT NULL DEFAULT 'active',
			revocation_reason varchar(50) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			revoked_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY user_token (user_id, token_hash),
			KEY user_status_seen (user_id, status, last_seen_at),
			KEY expiry_status (expires_at, status)
		) " . $wpdb->get_charset_collate() . ';' );
	}

	public static function create_device_table() {
		global $wpdb;
		self::dbdelta( "CREATE TABLE {$wpdb->prefix}sa_auth_devices (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			user_id bigint unsigned NOT NULL,
			fingerprint_hash char(64) NOT NULL,
			network_hash char(64) NOT NULL,
			device_label varchar(120) NOT NULL DEFAULT '',
			network_label varchar(80) NOT NULL DEFAULT 'Private',
			status varchar(16) NOT NULL DEFAULT 'trusted',
			risk_score tinyint unsigned NOT NULL DEFAULT 0,
			first_seen_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			last_login_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY user_fingerprint (user_id, fingerprint_hash),
			KEY user_network_status (user_id, network_hash, status),
			KEY last_seen_at (last_seen_at)
		) " . $wpdb->get_charset_collate() . ';' );
	}

	public static function create_risk_challenge_table() {
		global $wpdb;
		self::dbdelta( "CREATE TABLE {$wpdb->prefix}sa_auth_risk_challenges (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			token_hash char(64) NOT NULL,
			user_id bigint unsigned NOT NULL,
			fingerprint_hash char(64) NOT NULL,
			risk_score tinyint unsigned NOT NULL DEFAULT 0,
			reason_code varchar(80) NOT NULL DEFAULT '',
			remember_session tinyint(1) NOT NULL DEFAULT 0,
			destination text NOT NULL,
			completion_json longtext NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			attempts smallint unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			consumed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY token_hash (token_hash),
			KEY subject_status (user_id, status, expires_at),
			KEY expiry_status (expires_at, status)
		) " . $wpdb->get_charset_collate() . ';' );
	}

	public static function create_attempt_table() {
		global $wpdb;
		self::dbdelta( "CREATE TABLE {$wpdb->prefix}sa_auth_attempts (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			user_id bigint unsigned NOT NULL DEFAULT 0,
			fingerprint_hash char(64) NOT NULL,
			network_hash char(64) NOT NULL,
			result varchar(20) NOT NULL,
			reason_code varchar(80) NOT NULL DEFAULT '',
			risk_score tinyint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			KEY subject_time (user_id, created_at),
			KEY result_time (result, created_at)
		) " . $wpdb->get_charset_collate() . ';' );
	}

	public static function create_pages() {
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
				'posts_per_page' => 40,
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
				'meta_input'   => array( '_sa_managed_page' => '1', '_sa_private_page' => '1' ),
			),
			true
		);
		return is_wp_error( $page ) ? 0 : (int) $page;
	}

	public static function migrate_google_secret() {
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

	public static function ensure_dummy_password_hash() {
		$existing = (string) get_option( 'sauth_dummy_password_hash', '' );
		if ( '' === $existing && function_exists( 'wp_hash_password' ) ) {
			update_option( 'sauth_dummy_password_hash', wp_hash_password( SA_Security::random_token( 32 ) ), false );
		}
	}

	public static function register_roles() {
		return false;
	}

	public static function page_specs() {
		return array(
			'login'          => array( 'title' => 'Secure Log In', 'slug' => 'login', 'shortcode' => '[sabri_auth_login]' ),
			'signup'         => array( 'title' => 'Create Verified Account', 'slug' => 'register', 'shortcode' => '[sabri_auth_signup]' ),
			'email_verify'   => array( 'title' => 'Verify Email', 'slug' => 'verify-email', 'shortcode' => '[sabri_auth_verify_email]' ),
			'risk_challenge' => array( 'title' => 'Confirm Sign-In', 'slug' => 'confirm-sign-in', 'shortcode' => '[sabri_auth_risk_challenge]' ),
			'complete'       => array( 'title' => 'Complete Verified Profile', 'slug' => 'complete-profile', 'shortcode' => '[sabri_auth_complete_profile]' ),
			'forgot'         => array( 'title' => 'Forgot Password', 'slug' => 'forgot-password', 'shortcode' => '[sabri_auth_forgot_password]' ),
			'reset'          => array( 'title' => 'Reset Password', 'slug' => 'reset-password', 'shortcode' => '[sabri_auth_reset_password]' ),
			'sessions'       => array( 'title' => 'Account Sessions', 'slug' => 'account-sessions', 'shortcode' => '[sabri_auth_sessions]' ),
			'access'         => array( 'title' => 'Account Access Required', 'slug' => 'account-access-required', 'shortcode' => '[sabri_auth_access_required]' ),
			'google_account' => array( 'title' => 'Google Account Security', 'slug' => 'google-account-security', 'shortcode' => '[sabri_auth_google_account]' ),
			'google_verify'  => array( 'title' => 'Verify Google Sign-In', 'slug' => 'google-signin-verification', 'shortcode' => '[sabri_auth_google_verify]' ),
		);
	}

	private static function dbdelta( $sql ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
