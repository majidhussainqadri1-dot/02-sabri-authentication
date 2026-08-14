<?php

defined( 'ABSPATH' ) || exit;

final class SA_Activator {
	private static $table_suffixes = array(
		'rate_limits'        => 'sauth_rate_limits',
		'auth_outbox'        => 'sauth_auth_outbox',
		'email_verifications'=> 'sauth_email_verifications',
		'auth_sessions'      => 'sauth_auth_sessions',
		'auth_devices'       => 'sauth_auth_devices',
		'risk_challenges'    => 'sauth_auth_risk_challenges',
		'auth_attempts'      => 'sauth_auth_attempts',
	);

	private static $legacy_table_suffixes = array(
		'rate_limits'         => 'sa_rate_limits',
		'auth_outbox'         => 'sa_auth_outbox',
		'email_verifications' => 'sa_email_verifications',
		'auth_sessions'       => 'sa_auth_sessions',
		'auth_devices'        => 'sa_auth_devices',
		'risk_challenges'     => 'sa_auth_risk_challenges',
		'auth_attempts'       => 'sa_auth_attempts',
	);

	public static function activate() {
		if ( ! SA_Membership_Adapter::plugin_active() ) {
			deactivate_plugins( plugin_basename( SAUTH_FILE ) );
			wp_die(
				esc_html__( 'Sabri Authentication requires File 00 — Sabri Membership Core 1.2.43 or later with its current database migration complete, Safe Mode clear, smc.authentication-account 1.1.0 and the current membership-assurance contract. No account, role, guardian or verification authority will be created independently.', 'sabri-authentication' ),
				esc_html__( 'Required dependency missing', 'sabri-authentication' ),
				array( 'back_link' => true )
			);
		}
		if ( ! self::repair() ) {
			update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false );
			deactivate_plugins( plugin_basename( SAUTH_FILE ) );
			wp_die(
				esc_html__( 'File 02 activation stopped because its database/page migration postconditions were not satisfied. Safe Mode was enabled and no successful version marker was published.', 'sabri-authentication' ),
				esc_html__( 'Authentication migration incomplete', 'sabri-authentication' ),
				array( 'back_link' => true )
			);
		}
		add_option( 'sauth_google_enabled', '0', '', false );
		add_option( 'sauth_google_client_id', '', '', false );
		add_option( SAUTH_Operations::SAFE_MODE_OPTION, '0', '', false );
		set_transient( 'sauth_activation_notice', '1', 120 );
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
		$stored_db = (string) get_option( 'sauth_db_version', get_option( 'sa_db_version', '' ) );
		$stored    = (string) get_option( 'sauth_version', get_option( 'sa_version', '' ) );
		if ( SAUTH_DB_VERSION !== $stored_db || SAUTH_VERSION !== $stored ) {
			if ( ! self::repair() ) {
				update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false );
			}
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
		$migration_ok = self::migrate_legacy_tables();
		self::create_pages();
		$google_secret_ok = self::migrate_google_secret();
		self::ensure_dummy_password_hash();
		$passkey_ok = class_exists( 'SAUTH_Passkeys' ) && SAUTH_Passkeys::maybe_install( true );

		/* Never publish a successful runtime/schema marker merely because dbDelta
		 * returned. Prove every required table and managed page exists first and
		 * preserve retryability on partial/failed deployment. */
		if ( ! $migration_ok || ! $google_secret_ok || ! $passkey_ok || ! self::storage_ready() ) {
			return false;
		}

		update_option( 'sauth_version', SAUTH_VERSION, false );
		update_option( 'sauth_db_version', SAUTH_DB_VERSION, false );
		if ( SAUTH_VERSION !== (string) get_option( 'sauth_version', '' ) || SAUTH_DB_VERSION !== (string) get_option( 'sauth_db_version', '' ) ) {
			return false;
		}
		/* Compatibility mirrors are retained only for old integrations; they are
		 * not accepted as proof of a completed canonical migration. */
		update_option( 'sa_version', SAUTH_VERSION, false );
		update_option( 'sa_db_version', SAUTH_DB_VERSION, false );
		return true;
	}

	public static function table( $key ) {
		global $wpdb;
		$key = sanitize_key( (string) $key );
		return isset( self::$table_suffixes[ $key ] ) ? $wpdb->prefix . self::$table_suffixes[ $key ] : '';
	}

	public static function legacy_table( $key ) {
		global $wpdb;
		$key = sanitize_key( (string) $key );
		return isset( self::$legacy_table_suffixes[ $key ] ) ? $wpdb->prefix . self::$legacy_table_suffixes[ $key ] : '';
	}

	/**
	 * @return array<string,string>
	 */
	public static function required_tables() {
		return array(
			'rate limits'        => self::table( 'rate_limits' ),
			'event outbox'       => self::table( 'auth_outbox' ),
			'email verification' => self::table( 'email_verifications' ),
			'session registry'   => self::table( 'auth_sessions' ),
			'trusted devices'    => self::table( 'auth_devices' ),
			'risk challenges'    => self::table( 'risk_challenges' ),
			'auth attempts'      => self::table( 'auth_attempts' ),
		);
	}

	public static function create_rate_limit_table() {
		global $wpdb;
		$table = self::table( 'rate_limits' );
		self::dbdelta( "CREATE TABLE {$table} (
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
		$table = self::table( 'auth_outbox' );
		self::dbdelta( "CREATE TABLE {$table} (
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
		$table = self::table( 'email_verifications' );
		self::dbdelta( "CREATE TABLE {$table} (
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
		$table = self::table( 'auth_sessions' );
		self::dbdelta( "CREATE TABLE {$table} (
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
		$table = self::table( 'auth_devices' );
		self::dbdelta( "CREATE TABLE {$table} (
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
		$table = self::table( 'risk_challenges' );
		self::dbdelta( "CREATE TABLE {$table} (
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
		$table = self::table( 'auth_attempts' );
		self::dbdelta( "CREATE TABLE {$table} (
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

	/**
	 * Copy legacy `sa_*` storage into canonical `sauth_*` tables without
	 * deleting the legacy evidence. Every copy is idempotent and bounded by
	 * primary/unique keys in the destination.
	 */
	public static function migrate_legacy_tables() {
		global $wpdb;
		$ok = true;
		$columns = array(
			'rate_limits'         => 'bucket_hash,hits,window_started,expires_at,updated_at',
			'auth_outbox'         => 'id,event_id,event_name,schema_version,privacy_class,actor_user_id,subject_user_id,trace_id,payload_json,status,attempts,available_at,published_at,last_error,created_at,updated_at',
			'email_verifications' => 'user_id,email_hash,token_hash,status,attempts,sent_at,expires_at,verified_at,created_at,updated_at',
			'auth_sessions'       => 'id,public_id,user_id,token_hash,device_hash,device_label,network_label,risk_level,status,revocation_reason,created_at,last_seen_at,expires_at,revoked_at,updated_at',
			'auth_devices'        => 'id,public_id,user_id,fingerprint_hash,network_hash,device_label,network_label,status,risk_score,first_seen_at,last_seen_at,last_login_at,updated_at',
			'risk_challenges'     => 'id,public_id,token_hash,user_id,fingerprint_hash,risk_score,reason_code,remember_session,destination,completion_json,status,attempts,expires_at,consumed_at,created_at,updated_at',
			'auth_attempts'       => 'id,public_id,user_id,fingerprint_hash,network_hash,result,reason_code,risk_score,created_at',
		);
		foreach ( $columns as $key => $column_list ) {
			$legacy    = self::legacy_table( $key );
			$canonical = self::table( $key );
			if ( '' === $legacy || '' === $canonical || $legacy === $canonical ) {
				continue;
			}
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy ) ) );
			if ( $legacy !== $exists ) {
				continue;
			}
			$result = $wpdb->query( "INSERT IGNORE INTO {$canonical} ({$column_list}) SELECT {$column_list} FROM {$legacy}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( false === $result ) {
				$ok = false;
			}
		}
		if ( $ok ) {
			update_option( 'sauth_legacy_table_migration_version', SAUTH_DB_VERSION, false );
		}
		return $ok;
	}


	/**
	 * Canonical migration postcondition. Version markers are evidence only after
	 * every File 02 table and every managed core page is materially present.
	 */
	public static function storage_ready() {
		global $wpdb;
		foreach ( self::required_tables() as $table ) {
			if ( '' === $table ) {
				return false;
			}
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $table !== (string) $exists ) {
				return false;
			}
		}
		$map = (array) get_option( 'sauth_page_map', array() );
		foreach ( self::page_specs() as $key => $spec ) {
			$page_id = isset( $map[ $key ] ) ? absint( $map[ $key ] ) : 0;
			$page = $page_id ? get_post( $page_id ) : null;
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status || ! self::is_owned_page( $page ) || ! self::exact_shortcode_page( $page, $spec['shortcode'] ) ) {
				return false;
			}
		}
		if ( ! class_exists( 'SAUTH_Passkeys' ) || ! SAUTH_Passkeys::installation_ready() ) { return false; }
		return true;
	}

	public static function create_pages() {
		$known    = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) );
		$page_map = array();
		foreach ( self::page_specs() as $key => $spec ) {
			$page_map[ $key ] = self::ensure_owned_page( $spec, isset( $known[ $key ] ) ? absint( $known[ $key ] ) : 0 );
		}
		$page_map = array_filter( array_map( 'absint', $page_map ) );
		update_option( 'sauth_page_map', $page_map, false );
		update_option( 'sa_page_map', $page_map, false );
	}

	private static function exact_shortcode_page( $post, $shortcode ) {
		return $post instanceof WP_Post && trim( (string) $post->post_content ) === $shortcode;
	}

	private static function is_owned_page( WP_Post $page ) {
		return '1' === (string) get_post_meta( $page->ID, '_sauth_managed_page', true )
			|| '1' === (string) get_post_meta( $page->ID, '_sa_managed_page', true );
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
		update_post_meta( $page->ID, '_sauth_managed_page', '1' );
		update_post_meta( $page->ID, '_sauth_private_page', '1' );
		return (int) $page->ID;
	}

	private static function ensure_owned_page( array $spec, $known_id = 0 ) {
		if ( $known_id ) {
			$known = get_post( $known_id );
			if ( $known instanceof WP_Post && 'page' === $known->post_type && self::is_owned_page( $known ) ) {
				return self::update_owned_page( $known, $spec );
			}
		}

		$managed = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 40,
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => '_sauth_managed_page', 'value' => '1' ),
					array( 'key' => '_sa_managed_page', 'value' => '1' ),
				),
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $managed as $page ) {
			if ( self::exact_shortcode_page( $page, $spec['shortcode'] ) || has_shortcode( (string) $page->post_content, trim( $spec['shortcode'], '[]' ) ) ) {
				return self::update_owned_page( $page, $spec );
			}
		}

		foreach ( array( $spec['slug'], $spec['slug'] . '-sauth', $spec['slug'] . '-sa' ) as $candidate_slug ) {
			$existing = get_page_by_path( $candidate_slug, OBJECT, 'page' );
			if ( $existing instanceof WP_Post && ( self::is_owned_page( $existing ) || self::exact_shortcode_page( $existing, $spec['shortcode'] ) ) ) {
				return self::update_owned_page( $existing, $spec );
			}
		}

		$preferred = get_page_by_path( $spec['slug'], OBJECT, 'page' );
		$slug      = $preferred instanceof WP_Post ? $spec['slug'] . '-sauth' : $spec['slug'];
		$page      = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $spec['title'],
				'post_name'    => $slug,
				'post_content' => $spec['shortcode'],
				'meta_input'   => array( '_sauth_managed_page' => '1', '_sauth_private_page' => '1' ),
			),
			true
		);
		return is_wp_error( $page ) ? 0 : (int) $page;
	}

	private static function migrate_google_secret() {
		$cipher=(string)get_option('sauth_google_client_secret',get_option('sa_google_client_secret',''));
		if(''===$cipher){return true;} if(SA_Security::current_cipher_ready($cipher)){return true;} if(!SA_Security::master_key_ready()){return false;}
		$plain=SA_Security::decrypt($cipher); if(''===$plain){return false;} $encrypted=SA_Security::encrypt($plain); $plain=''; if(!SA_Security::current_cipher_ready($encrypted)){return false;}
		update_option('sauth_google_client_secret',$encrypted,false); update_option('sa_google_client_secret',$encrypted,false);
		$canonical=(string)get_option('sauth_google_client_secret',''); $mirror=(string)get_option('sa_google_client_secret','');
		return hash_equals($encrypted,$canonical) && hash_equals($encrypted,$mirror) && SA_Security::current_cipher_ready($canonical);
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
			/* Legacy compatibility page redirects to the canonical nested route. */
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
