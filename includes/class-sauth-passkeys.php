<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 WebAuthn / passkey ceremony.
 *
 * File 02 owns authentication ceremonies and passkey credentials. File 00 owns
 * membership, identity, authorization and MFA policy and consumes only a fresh,
 * session-bound versioned assurance claim from this class. File 24 may increase
 * risk requirements but never becomes a parallel credential owner.
 *
 * Privacy: no biometric template, attestation blob, raw session token, full IP
 * address or provider secret is retained. Credential IDs are encrypted for
 * presentation/exclusion and indexed by stable SHA-256 because WebAuthn
 * credential IDs are random opaque identifiers, not secrets. Attestation is
 * requested as "none" and only the COSE public key from authenticatorData is
 * accepted; a client-supplied public key can never establish registration.
 */
final class SAUTH_Passkeys {
	const CONTRACT_VERSION      = '1.0.0';
	const SCHEMA_VERSION        = '1.0.1';
	const CHALLENGE_TTL         = 300;
	const ASSURANCE_TTL         = 300;
	const MAX_CREDENTIALS       = 10;
	const PUBLIC_KEY_TIMEOUT_MS = 60000;
	const OPTION_SCHEMA_VERSION = 'sauth_passkey_schema_version';
	const CLEANUP_HOOK          = 'sauth_passkey_cleanup';
	const USER_HANDLE_META      = '_sauth_passkey_user_handle_v1';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'localize_assets' ), 30 );
		add_shortcode( 'sabri_auth_passkeys', array( __CLASS__, 'render_manager' ) );

		add_action( 'wp_ajax_sauth_passkey_begin_registration', array( __CLASS__, 'begin_registration' ) );
		add_action( 'wp_ajax_sauth_passkey_finish_registration', array( __CLASS__, 'finish_registration' ) );
		add_action( 'wp_ajax_sauth_passkey_revoke', array( __CLASS__, 'revoke' ) );
		add_action( 'wp_ajax_nopriv_sauth_passkey_begin_authentication', array( __CLASS__, 'begin_authentication' ) );
		add_action( 'wp_ajax_sauth_passkey_begin_authentication', array( __CLASS__, 'begin_authentication' ) );
		add_action( 'wp_ajax_nopriv_sauth_passkey_finish_authentication', array( __CLASS__, 'finish_authentication' ) );
		add_action( 'wp_ajax_sauth_passkey_finish_authentication', array( __CLASS__, 'finish_authentication' ) );

		add_action( 'set_logged_in_cookie', array( __CLASS__, 'bind_pending_assurance_to_session' ), 40, 6 );
		add_filter( 'smc_file02_authentication_assurance_v1', array( __CLASS__, 'file00_assurance' ), 20, 2 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
	}

	public static function deactivate() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CLEANUP_HOOK );
		}
	}

	public static function maybe_install( $force = false ) {
		if ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) { return false; }
		if ( ! $force && self::installation_ready() ) { return true; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table(); $charset = $wpdb->get_charset_collate();
		if ( ! self::prepare_legacy_credential_columns( $table ) ) { delete_option( self::OPTION_SCHEMA_VERSION ); return false; }
		$sql = "CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			user_id bigint unsigned NOT NULL,
			credential_lookup_hash char(64) NOT NULL,
			credential_id_ciphertext longtext NOT NULL,
			public_key_pem longtext NOT NULL,
			algorithm varchar(20) NOT NULL DEFAULT 'ES256',
			sign_count bigint unsigned NOT NULL DEFAULT 0,
			nickname varchar(120) NOT NULL DEFAULT '',
			attachment varchar(32) NOT NULL DEFAULT '',
			transports text NOT NULL,
			discoverable tinyint(1) NOT NULL DEFAULT 1,
			backup_eligible tinyint(1) NOT NULL DEFAULT 0,
			backup_state tinyint(1) NOT NULL DEFAULT 0,
			hardware_backed tinyint(1) NOT NULL DEFAULT 0,
			status varchar(24) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			last_used_at datetime DEFAULT NULL,
			revoked_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY credential_lookup_hash (credential_lookup_hash),
			KEY user_status (user_id,status),
			KEY revoked_at (revoked_at)
		) {$charset};";
		dbDelta( $sql ); self::ensure_manager_page();
		$table_ready = self::table_schema_ready();
		if ( ! $table_ready || ! self::manager_page_ready() ) { delete_option( self::OPTION_SCHEMA_VERSION ); return false; }
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
		if ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) { return false; }
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
			if ( false === $scheduled || is_wp_error( $scheduled ) ) { return false; }
		}
		return self::installation_ready();
	}

	public static function installation_ready() {
		if ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) || ! self::manager_page_ready() ) { return false; }
		$cron_ready = function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( self::CLEANUP_HOOK );
		return self::table_schema_ready() && $cron_ready;
	}

	private static function table_schema_ready() {
		global $wpdb; $table = self::table();
		$exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists || '' !== (string) $wpdb->last_error ) { return false; }
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$required = array( 'id','public_id','user_id','credential_lookup_hash','credential_id_ciphertext','public_key_pem','algorithm','sign_count','nickname','attachment','transports','discoverable','backup_eligible','backup_state','hardware_backed','status','created_at','last_used_at','revoked_at','updated_at' );
		if ( ! is_array( $columns ) || '' !== (string) $wpdb->last_error || array_diff( $required, array_map( 'strval', $columns ) ) ) { return false; }
		$required_indexes = array(
			'PRIMARY'                => array( 0, array( 'id' ) ),
			'public_id'              => array( 0, array( 'public_id' ) ),
			'credential_lookup_hash' => array( 0, array( 'credential_lookup_hash' ) ),
			'user_status'            => array( 1, array( 'user_id','status' ) ),
			'revoked_at'             => array( 1, array( 'revoked_at' ) ),
		);
		$rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) { return false; }
		$actual = array();
		foreach ( $rows as $row ) {
			$name = (string) ( $row['Key_name'] ?? '' ); $seq = absint( $row['Seq_in_index'] ?? 0 );
			if ( '' === $name || $seq < 1 ) { continue; }
			if ( ! isset( $actual[ $name ] ) ) { $actual[ $name ] = array( 'non_unique'=>(int)( $row['Non_unique'] ?? 1 ), 'columns'=>array() ); }
			$actual[ $name ]['columns'][ $seq ] = (string) ( $row['Column_name'] ?? '' );
		}
		foreach ( $required_indexes as $name => $spec ) {
			if ( ! isset( $actual[ $name ] ) || (int) $actual[ $name ]['non_unique'] !== (int) $spec[0] ) { return false; }
			ksort( $actual[ $name ]['columns'] );
			if ( array_values( $actual[ $name ]['columns'] ) !== $spec[1] ) { return false; }
		}
		return true;
	}

	private static function prepare_legacy_credential_columns( $table ) {
		global $wpdb;
		$exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) { return '' === (string) $wpdb->last_error; }
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $columns ) || '' !== (string) $wpdb->last_error ) { return false; }
		$has_legacy_hash = in_array( 'credential_hash', $columns, true );
		$has_legacy_cipher = in_array( 'credential_cipher', $columns, true );
		if ( $has_legacy_hash && ! in_array( 'credential_lookup_hash', $columns, true ) ) {
			if ( false === $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN credential_lookup_hash char(64) NOT NULL" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $has_legacy_cipher && ! in_array( 'credential_id_ciphertext', $columns, true ) ) {
			if ( false === $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN credential_id_ciphertext longtext NOT NULL" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $has_legacy_hash ) {
			if ( false === $wpdb->query( "UPDATE `{$table}` SET credential_lookup_hash=credential_hash WHERE credential_lookup_hash='' AND credential_hash<>''" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $has_legacy_cipher ) {
			if ( false === $wpdb->query( "UPDATE `{$table}` SET credential_id_ciphertext=credential_cipher WHERE credential_id_ciphertext='' AND credential_cipher<>''" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$unmigrated = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE credential_lookup_hash='' OR credential_id_ciphertext=''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return '' === (string) $wpdb->last_error && 0 === $unmigrated;
	}

	private static function ensure_manager_page() {
		$map = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) );
		$page_id = isset( $map['passkeys'] ) ? absint( $map['passkeys'] ) : 0;
		$page = $page_id ? get_post( $page_id ) : null;
		if ( self::is_manager_page( $page ) ) { return $page_id; }
		$candidates = get_posts( array( 'post_type'=>'page', 'post_status'=>array('publish','draft','private','pending'), 'posts_per_page'=>50, 'orderby'=>'ID', 'order'=>'ASC', 'meta_query'=>array('relation'=>'OR', array('key'=>'_sauth_managed_page','value'=>'1'), array('key'=>'_sa_private_page','value'=>'1'), array('key'=>'_sauth_private_page','value'=>'1')) ) );
		foreach ( is_array( $candidates ) ? $candidates : array() as $candidate ) {
			if ( self::is_manager_page( $candidate, true ) ) { $page_id=absint($candidate->ID); self::mark_manager_page($page_id); $map['passkeys']=$page_id; update_option('sauth_page_map',$map,false); return $page_id; }
		}
		$page_id = wp_insert_post( array( 'post_title'=>'Passkeys & Security Keys', 'post_name'=>'account-passkeys', 'post_content'=>'[sabri_auth_passkeys]', 'post_status'=>'publish', 'post_type'=>'page', 'meta_input'=>array('_sauth_managed_page'=>'1','_sauth_private_page'=>'1') ), true );
		if ( is_wp_error($page_id) || !absint($page_id) ) { return 0; }
		$page_id=absint($page_id); self::mark_manager_page($page_id); $map['passkeys']=$page_id; update_option('sauth_page_map',$map,false); return $page_id;
	}
	private static function is_manager_page( $page, $legacy_allowed = false ) {
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status || '[sabri_auth_passkeys]' !== trim((string)$page->post_content) ) { return false; }
		$canonical='1'===(string)get_post_meta($page->ID,'_sauth_managed_page',true);
		$legacy='1'===(string)get_post_meta($page->ID,'_sa_private_page',true) || '1'===(string)get_post_meta($page->ID,'_sauth_private_page',true);
		return $canonical || ($legacy_allowed && $legacy);
	}
	private static function mark_manager_page( $page_id ) { $page_id=absint($page_id); if(!$page_id){return;} update_post_meta($page_id,'_sauth_managed_page','1'); update_post_meta($page_id,'_sauth_private_page','1'); delete_post_meta($page_id,'_sa_private_page'); }
	private static function manager_page_ready() { $map=(array)get_option('sauth_page_map',array()); $page_id=isset($map['passkeys'])?absint($map['passkeys']):0; return $page_id>0 && self::is_manager_page(get_post($page_id)); }

	public static function manager_url() {
		$map = (array) get_option( 'sauth_page_map', array() );
		$page_id = isset( $map['passkeys'] ) ? absint( $map['passkeys'] ) : 0;
		return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/account-passkeys/' );
	}

	public static function localize_assets() {
		if ( ! class_exists( 'SA_Access_Control' ) || ! SA_Access_Control::is_file02_page() ) {
			return;
		}
		if ( ! wp_script_is( 'sauth-authentication', 'enqueued' ) ) {
			wp_enqueue_script( 'sauth-authentication', SAUTH_URL . 'assets/js/authentication.js', array(), SAUTH_VERSION, true );
		}
		wp_localize_script(
			'sauth-authentication',
			'SabriAuthPasskeys',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => is_user_logged_in() ? wp_create_nonce( 'sauth_passkeys' ) : '',
				'loggedIn'     => is_user_logged_in(),
				'managerUrl'   => self::manager_url(),
				'available'    => self::environment_ready(),
				'unsupported'  => __( 'Passkeys require a current browser and a secure HTTPS connection.', 'sabri-authentication' ),
				'genericError' => __( 'The passkey operation could not be completed. No account authority was changed.', 'sabri-authentication' ),
			)
		);
	}

	public static function render_manager() {
		if ( ! is_user_logged_in() ) {
			return '<main class="sa-auth-shell"><section class="sa-auth-card"><h1>Account access required</h1><p>Sign in before managing passkeys.</p><a class="sa-primary-button" href="' . esc_url( SA_Membership_Adapter::login_url( self::manager_url() ) ) . '">Log In</a></section></main>';
		}
		$user_id = get_current_user_id();
		$credentials = self::credentials_for_user( $user_id );
		ob_start();
		?>
		<main class="sa-auth-shell">
			<section class="sa-auth-card sa-auth-wide" aria-labelledby="sauth-passkeys-title">
				<span class="sa-kicker">Account security</span>
				<h1 id="sauth-passkeys-title">Passkeys &amp; Security Keys</h1>
				<p class="sa-intro">Passkeys use WebAuthn public-key cryptography. Your authenticator keeps the private key; Sabri stores only the public key and privacy-minimized credential metadata. File 00 remains the identity and membership authority.</p>
				<div class="sa-notice" data-sauth-passkey-status role="status" aria-live="polite"></div>
				<?php if ( ! self::environment_ready() ) : ?>
					<div class="sa-notice sa-notice-error" role="status">Passkey management is unavailable until this page is served from an approved secure HTTPS origin and OpenSSL is available.</div>
				<?php else : ?>
					<div class="sa-form">
						<label for="sauth-passkey-name">Passkey name <span class="screen-reader-text">optional</span></label>
						<input id="sauth-passkey-name" type="text" maxlength="80" autocomplete="off" placeholder="For example: My phone">
						<label for="sauth-passkey-password">Current password <span class="screen-reader-text">used when this session does not already have a fresh File 02 passkey assurance</span></label>
						<input id="sauth-passkey-password" type="password" autocomplete="current-password" maxlength="4096">
						<button type="button" class="sa-primary-button" data-sauth-passkey-register>Add a Passkey</button>
					</div>
				<?php endif; ?>

				<h2>Your passkeys</h2>
				<div class="sa-session-list" data-sauth-passkey-list>
				<?php if ( empty( $credentials ) ) : ?>
					<p>No active passkeys are registered.</p>
				<?php endif; ?>
				<?php foreach ( $credentials as $credential ) : ?>
					<article class="sa-session-card">
						<h3><?php echo esc_html( $credential['nickname'] ? $credential['nickname'] : 'Passkey' ); ?></h3>
						<dl class="sa-definition">
							<div><dt>Created</dt><dd><?php echo esc_html( self::display_time( $credential['created_at'] ) ); ?></dd></div>
							<div><dt>Last used</dt><dd><?php echo esc_html( $credential['last_used_at'] ? self::display_time( $credential['last_used_at'] ) : 'Not yet used' ); ?></dd></div>
							<div><dt>Type</dt><dd><?php echo esc_html( 'cross-platform' === $credential['attachment'] ? 'Security key / cross-platform authenticator' : 'Passkey' ); ?></dd></div>
							<div><dt>Sync capable</dt><dd><?php echo ! empty( $credential['backup_eligible'] ) ? 'Yes' : 'No'; ?></dd></div>
						</dl>
						<button type="button" class="sa-secondary-button" data-sauth-passkey-revoke="<?php echo esc_attr( $credential['public_id'] ); ?>">Revoke</button>
					</article>
				<?php endforeach; ?>
				</div>
				<p class="sa-data-note">If a device is lost, revoke its passkey and review Active Sessions. Support must never ask for your password, passkey private key, biometric data or recovery material.</p>
				<a class="sa-secondary-button" href="<?php echo esc_url( SA_Security::page_url( 'sessions' ) ); ?>">Review Active Sessions</a>
			</section>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	public static function begin_registration() {
		self::require_authenticated_ajax();
		if ( ! self::environment_ready() || ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) ) {
			self::json_error( 'passkeys_unavailable' );
		}
		$user_id = get_current_user_id();
		if ( SA_Security::rate_limited( 'passkey_register', 6, HOUR_IN_SECONDS, (string) $user_id ) ) {
			self::json_error( 'rate_limited' );
		}
		if ( count( self::credentials_for_user( $user_id ) ) >= self::MAX_CREDENTIALS ) {
			self::json_error( 'credential_limit_reached' );
		}
		$password = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		$step_up = ''; // Retired File 00 factor material is never accepted as File 02 authority.
		if ( ! self::reauthenticate_for_management( $user_id, $password, $step_up, 'passkey_enrollment' ) ) {
			$password = '';
			$step_up = '';
			self::json_error( 'fresh_reauthentication_required' );
		}
		$password = '';
		$step_up = '';
		unset( $_POST['current_password'], $_POST['step_up_code'] );

		$challenge = self::new_challenge( 'register', $user_id, '' );
		if ( empty( $challenge ) ) {
			self::json_error( 'challenge_store_failed' );
		}
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			self::json_error( 'subject_invalid' );
		}
		$user_handle = self::user_handle( $user_id, true );
		if ( '' === $user_handle ) {
			self::json_error( 'user_handle_store_failed' );
		}
		$exclude = array();
		foreach ( self::credentials_for_user( $user_id ) as $credential ) {
			$encrypted_id = SA_Security::decrypt( (string) $credential['credential_id_ciphertext'] );
			if ( false !== self::base64url_decode( $encrypted_id ) ) {
				$exclude[] = array(
					'type'       => 'public-key',
					'id'         => $encrypted_id,
					'transports' => self::transports_array( $credential['transports'] ),
				);
			}
		}
		$ctx = self::rp_context();
		wp_send_json_success(
			array(
				'challengeId' => $challenge['id'],
				'publicKey' => array(
					'challenge' => $challenge['challenge'],
					'rp' => array( 'name' => 'Sabri Social Homeopathy Platform', 'id' => $ctx['rp_id'] ),
					'user' => array(
						'id' => $user_handle,
						'name' => (string) $user->user_email,
						'displayName' => (string) ( $user->display_name ? $user->display_name : $user->user_login ),
					),
					'pubKeyCredParams' => array(
						array( 'type' => 'public-key', 'alg' => -7 ),
						array( 'type' => 'public-key', 'alg' => -257 ),
					),
					'timeout' => self::PUBLIC_KEY_TIMEOUT_MS,
					'attestation' => 'none',
					'authenticatorSelection' => array(
						'residentKey' => 'required',
						'requireResidentKey' => true,
						'userVerification' => 'required',
					),
					'excludeCredentials' => $exclude,
				),
			)
		);
	}

	public static function finish_registration() {
		self::require_authenticated_ajax();
		$user_id = get_current_user_id();
		$challenge_id = isset( $_POST['challenge_id'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge_id'] ) ) : '';
		$challenge = self::consume_challenge( $challenge_id, 'register', $user_id );
		if ( empty( $challenge ) ) {
			self::json_error( 'challenge_invalid_expired_or_replayed' );
		}
		$client_data = self::decode_b64url_field( 'client_data_json' );
		$attestation_raw = self::decode_b64url_field( 'attestation_object' );
		$raw_id = self::decode_b64url_field( 'raw_id' );
		$attachment = isset( $_POST['attachment'] ) ? sanitize_key( wp_unslash( $_POST['attachment'] ) ) : '';
		$transports = isset( $_POST['transports'] ) ? self::sanitize_transports( wp_unslash( $_POST['transports'] ) ) : '';
		$nickname = isset( $_POST['nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['nickname'] ) ) : '';
		$nickname = function_exists( 'mb_substr' ) ? mb_substr( $nickname, 0, 80 ) : substr( $nickname, 0, 80 );

		$client = self::validate_client_data( $client_data, 'webauthn.create', $challenge['challenge'] );
		$attestation = self::parse_attestation_object( $attestation_raw );
		if ( is_wp_error( $client ) || is_wp_error( $attestation ) || '' === $raw_id ) {
			self::json_error( 'registration_attestation_invalid' );
		}
		if ( 'none' !== (string) $attestation['fmt'] || ! empty( $attestation['att_stmt'] ) ) {
			self::json_error( 'attestation_format_not_allowed' );
		}
		$parsed = self::parse_authenticator_data( (string) $attestation['auth_data'], true );
		if ( is_wp_error( $parsed ) || ! hash_equals( self::rp_id_hash(), (string) $parsed['rp_id_hash'] ) || empty( $parsed['user_present'] ) || empty( $parsed['user_verified'] ) || empty( $parsed['attested'] ) ) {
			self::json_error( 'authenticator_binding_invalid' );
		}
		if ( ! isset( $parsed['credential_id'] ) || ! hash_equals( $raw_id, (string) $parsed['credential_id'] ) ) {
			self::json_error( 'credential_id_mismatch' );
		}
		$key = self::cose_public_key_to_pem( $parsed['credential_public_key'] ?? array() );
		if ( is_wp_error( $key ) ) {
			self::json_error( 'credential_public_key_invalid' );
		}
		$credential_hash = self::credential_hash( $raw_id );
		if ( self::credential_exists( $credential_hash ) ) {
			self::json_error( 'credential_already_registered' );
		}
		$cipher = SA_Security::encrypt( self::base64url_encode( $raw_id ) );
		if ( '' === $cipher ) {
			self::json_error( 'credential_encryption_failed' );
		}
		$backup_eligible = ! empty( $parsed['backup_eligible'] );
		/* With attestation=none, authenticator hardware provenance is unknown. */
		$hardware_backed = false;
		$now = current_time( 'mysql', true );
		global $wpdb;
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'public_id' => strtolower( wp_generate_uuid4() ),
				'user_id' => $user_id,
				'credential_lookup_hash' => $credential_hash,
				'credential_id_ciphertext' => $cipher,
				'public_key_pem' => (string) $key['pem'],
				'algorithm' => intval( $key['algorithm'] ),
				'transports' => $transports,
				'attachment' => in_array( $attachment, array( 'platform', 'cross-platform' ), true ) ? $attachment : '',
				'discoverable' => 1,
				'backup_eligible' => $backup_eligible ? 1 : 0,
				'backup_state' => ! empty( $parsed['backup_state'] ) ? 1 : 0,
				'hardware_backed' => 0,
				'sign_count' => absint( $parsed['sign_count'] ),
				'nickname' => $nickname,
				'status' => 'active',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s','%d','%s','%s','%s','%d','%s','%s','%d','%d','%d','%d','%s','%s','%s','%s' )
		);
		if ( 1 !== (int) $inserted ) {
			self::json_error( 'credential_store_failed' );
		}
		SAUTH_Event_Outbox::emit( 'PasskeyRegistered.v1', $user_id, $user_id, array( 'method' => 'webauthn', 'backup_eligible' => $backup_eligible ? 1 : 0 ), 'security' );
		SA_Membership_Adapter::audit( 'passkey_registered', $user_id, array( 'backup_eligible' => $backup_eligible ? 1 : 0 ) );
		wp_send_json_success( array( 'message' => 'Passkey added successfully.', 'reload' => true ) );
	}

	public static function begin_authentication() {
		if ( ! self::environment_ready() || ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) ) {
			self::json_error( 'passkeys_unavailable' );
		}
		if ( SA_Security::rate_limited( 'passkey_login_ip', 20, 900 ) ) {
			self::json_error( 'rate_limited' );
		}
		$redirect = isset( $_POST['redirect_to'] ) ? SA_Security::safe_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), home_url( '/' ) ) : home_url( '/' );
		$challenge = self::new_challenge( 'authenticate', 0, $redirect );
		if ( empty( $challenge ) ) {
			self::json_error( 'challenge_store_failed' );
		}
		$ctx = self::rp_context();
		wp_send_json_success(
			array(
				'challengeId' => $challenge['id'],
				'publicKey' => array(
					'challenge' => $challenge['challenge'],
					'rpId' => $ctx['rp_id'],
					'timeout' => self::PUBLIC_KEY_TIMEOUT_MS,
					'userVerification' => 'required',
					'allowCredentials' => array(),
				),
			)
		);
	}

	public static function finish_authentication() {
		$challenge_id = isset( $_POST['challenge_id'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge_id'] ) ) : '';
		$challenge = self::consume_challenge( $challenge_id, 'authenticate', 0 );
		if ( empty( $challenge ) ) {
			self::json_error( 'challenge_invalid_expired_or_replayed' );
		}
		$client_data = self::decode_b64url_field( 'client_data_json' );
		$auth_data = self::decode_b64url_field( 'authenticator_data' );
		$signature = self::decode_b64url_field( 'signature' );
		$raw_id = self::decode_b64url_field( 'raw_id' );
		$user_handle = self::decode_b64url_field( 'user_handle', true );
		if ( '' === $client_data || '' === $auth_data || '' === $signature || '' === $raw_id ) {
			self::authentication_failure( 0, 'assertion_fields_invalid' );
		}
		$client = self::validate_client_data( $client_data, 'webauthn.get', $challenge['challenge'] );
		$parsed = self::parse_authenticator_data( $auth_data, false );
		if ( is_wp_error( $client ) || is_wp_error( $parsed ) || ! hash_equals( self::rp_id_hash(), (string) $parsed['rp_id_hash'] ) || empty( $parsed['user_present'] ) || empty( $parsed['user_verified'] ) ) {
			self::authentication_failure( 0, 'assertion_binding_invalid' );
		}
		$credential = self::credential_by_hash( self::credential_hash( $raw_id ) );
		if ( empty( $credential ) || 'active' !== (string) $credential['status'] ) {
			self::authentication_failure( 0, 'credential_unknown' );
		}
		$user_id = absint( $credential['user_id'] );
		$expected_handle = self::user_handle( $user_id, false );
		if ( '' !== $user_handle && ( '' === $expected_handle || ! hash_equals( self::base64url_decode( $expected_handle ), $user_handle ) ) ) {
			self::authentication_failure( $user_id, 'user_handle_mismatch' );
		}
		$signed = $auth_data . hash( 'sha256', $client_data, true );
		if ( ! self::verify_signature( $signed, $signature, (string) $credential['public_key_pem'], intval( $credential['algorithm'] ) ) ) {
			self::authentication_failure( $user_id, 'signature_invalid' );
		}
		$stored_count = absint( $credential['sign_count'] );
		$new_count = absint( $parsed['sign_count'] );
		if ( $stored_count > 0 && $new_count > 0 && $new_count <= $stored_count ) {
			self::mark_credential_compromised( $credential );
			self::authentication_failure( $user_id, 'signature_counter_regression' );
		}

		$completion = SAUTH_Account_Contract::completion_state( $user_id, array( 'purpose' => 'passkey_sign_in' ) );
		$membership = SA_Membership_Adapter::membership_assertion( $user_id, 'authentication_sign_in', 'authentication' );
		if ( ! self::sign_in_allowed( $membership, $completion ) ) {
			self::authentication_failure( $user_id, 'membership_not_eligible' );
		}

		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'sign_count' => max( $stored_count, $new_count ),
				'backup_state' => ! empty( $parsed['backup_state'] ) ? 1 : 0,
				'last_used_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $credential['id'] ), 'status' => 'active' ),
			array( '%d','%d','%s','%s' ),
			array( '%d','%s' )
		);
		self::store_pending_assurance( $user_id, ! empty( $credential['hardware_backed'] ) );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, ! empty( $_POST['remember'] ), is_ssl() );
		SAUTH_Login_Risk::record_successful_login( $user_id, 'passkey', 0 );
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationSucceeded.v1', $user_id, $user_id, array( 'method' => 'passkey', 'risk' => 'strong_authentication' ), 'security' );
		SAUTH_Event_Outbox::emit( 'PasskeyAuthenticated.v1', $user_id, $user_id, array( 'backup_state' => ! empty( $parsed['backup_state'] ) ? 1 : 0 ), 'security' );
		SA_Membership_Adapter::audit( 'passkey_authentication_succeeded', $user_id );
		SA_Security::clear_rate_limit( 'passkey_login_ip' );

		$destination = isset( $challenge['redirect'] ) ? SA_Security::safe_redirect( $challenge['redirect'], home_url( '/' ) ) : home_url( '/' );
		if ( 'allow' === ( $completion['result'] ?? '' ) && ! empty( $completion['missing_steps'] ) && ! empty( $completion['next_route'] ) ) {
			$destination = SA_Security::safe_redirect( (string) $completion['next_route'], $destination );
		}
		wp_send_json_success( array( 'redirect' => $destination ) );
	}

	public static function revoke() {
		self::require_authenticated_ajax();
		$user_id = get_current_user_id();
		$public_id = isset( $_POST['credential_id'] ) ? sanitize_text_field( wp_unslash( $_POST['credential_id'] ) ) : '';
		$password = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
		$step_up = ''; // Retired File 00 factor material is never accepted as File 02 authority.
		if ( ! self::reauthenticate_for_management( $user_id, $password, $step_up, 'passkey_revocation' ) ) {
			$password = '';
			$step_up = '';
			self::json_error( 'fresh_reauthentication_required' );
		}
		$password = '';
		$step_up = '';
		unset( $_POST['current_password'], $_POST['step_up_code'] );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE public_id=%s AND user_id=%d", $public_id, $user_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			self::json_error( 'credential_not_found' );
		}
		if ( 'active' !== (string) $row['status'] ) {
			wp_send_json_success( array( 'message' => 'This passkey was already inactive.', 'reload' => true ) );
		}
		$changed = $wpdb->update(
			self::table(),
			array( 'status' => 'revoked', 'revoked_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $row['id'] ), 'user_id' => $user_id, 'status' => 'active' ),
			array( '%s','%s','%s' ),
			array( '%d','%d','%s' )
		);
		if ( 1 !== (int) $changed ) {
			self::json_error( 'credential_revoke_failed' );
		}
		self::clear_assurance_for_user( $user_id );
		SAUTH_Event_Outbox::emit( 'PasskeyRevoked.v1', $user_id, $user_id, array( 'reason' => 'user_request' ), 'security' );
		SA_Membership_Adapter::audit( 'passkey_revoked', $user_id );
		wp_send_json_success( array( 'message' => 'Passkey revoked.', 'reload' => true ) );
	}

	public static function file00_assurance( $baseline, $user_id ) {
		$baseline = is_array( $baseline ) ? $baseline : array();
		$user_id = absint( $user_id );
		if ( ! $user_id || get_current_user_id() !== $user_id ) {
			return $baseline;
		}
		$token = (string) wp_get_session_token();
		if ( '' === $token ) {
			return $baseline;
		}
		$receipt = get_transient( self::session_assurance_key( $user_id, $token ) );
		if ( ! self::valid_assurance_receipt( $receipt, $user_id, $token ) ) {
			return $baseline;
		}
		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'owner' => 'file02',
			'level' => 3,
			'method' => 'webauthn_passkey',
			'passkey_asserted' => true,
			'hardware_backed' => ! empty( $receipt['hardware_backed'] ),
			'verified_at' => absint( $receipt['verified_at'] ),
		);
	}

	public static function bind_pending_assurance_to_session( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		$user_id = absint( $user_id );
		$token = (string) $token;
		if ( ! $user_id || 'logged_in' !== $scheme || '' === $token ) {
			return;
		}
		$key = self::pending_assurance_key( $user_id );
		$receipt = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $receipt ) || absint( $receipt['user_id'] ?? 0 ) !== $user_id || ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() ) || absint( $receipt['expires_at'] ?? 0 ) <= time() ) {
			return;
		}
		$receipt['session_binding'] = self::session_binding( $token );
		$ttl = max( 1, min( self::ASSURANCE_TTL, absint( $receipt['expires_at'] ) - time() ) );
		set_transient( self::session_assurance_key( $user_id, $token ), $receipt, $ttl );
	}

	private static function store_pending_assurance( $user_id, $hardware_backed ) {
		$receipt = array(
			'contract_version' => self::CONTRACT_VERSION,
			'owner' => 'file02',
			'user_id' => absint( $user_id ),
			'method' => 'webauthn_passkey',
			'hardware_backed' => (bool) $hardware_backed,
			'verified_at' => time(),
			'expires_at' => time() + self::ASSURANCE_TTL,
			'fingerprint' => SA_Security::client_fingerprint(),
			'session_binding' => '',
		);
		set_transient( self::pending_assurance_key( $user_id ), $receipt, self::ASSURANCE_TTL );
	}

	private static function valid_assurance_receipt( $receipt, $user_id, $token ) {
		if ( ! is_array( $receipt ) || self::CONTRACT_VERSION !== (string) ( $receipt['contract_version'] ?? '' ) || 'file02' !== (string) ( $receipt['owner'] ?? '' ) ) {
			return false;
		}
		if ( absint( $receipt['user_id'] ?? 0 ) !== absint( $user_id ) || absint( $receipt['expires_at'] ?? 0 ) <= time() || absint( $receipt['verified_at'] ?? 0 ) > time() + 60 ) {
			return false;
		}
		return hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )
			&& hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) );
	}

	private static function reauthenticate_for_management( $user_id, $password, $step_up, $scope ) {
		$user_id = absint( $user_id );
		$step_up = ''; // Compatibility argument only; File 00 TOTP/recovery codes are retired.
		$scope = '';
		if ( ! $user_id ) {
			return false;
		}
		$current = self::file00_assurance( array(), $user_id );
		if ( 'file02' === ( $current['owner'] ?? '' ) && ! empty( $current['passkey_asserted'] ) ) {
			return true;
		}
		if ( '' !== (string) $password ) {
			$user = get_userdata( $user_id );
			return $user instanceof WP_User && function_exists( 'wp_check_password' ) && wp_check_password( (string) $password, (string) $user->user_pass, $user_id );
		}
		return false;
	}

	private static function require_authenticated_ajax() {
		if ( ! is_user_logged_in() ) {
			self::json_error( 'authentication_required', 401 );
		}
		check_ajax_referer( 'sauth_passkeys', 'nonce' );
	}

	private static function sign_in_allowed( array $assertion, array $completion ) {
		if ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || ! empty( $assertion['membership']['suspended'] ) ) {
			return false;
		}
		if ( 'allow' === ( $assertion['result'] ?? '' ) ) {
			return true;
		}
		$active = true === ( $assertion['membership']['active'] ?? false );
		return $active
			&& 'allow' === ( $completion['result'] ?? '' )
			&& ! empty( $completion['missing_steps'] )
			&& ! empty( $completion['next_route'] );
	}

	private static function new_challenge( $purpose, $user_id, $redirect ) {
		$raw = self::secure_random( 32 );
		if ( '' === $raw ) {
			return array();
		}
		$id = strtolower( wp_generate_uuid4() );
		$ctx = self::rp_context();
		$record = array(
			'id' => $id,
			'purpose' => sanitize_key( $purpose ),
			'user_id' => absint( $user_id ),
			'challenge' => self::base64url_encode( $raw ),
			'fingerprint' => SA_Security::client_fingerprint(),
			'origin' => $ctx['origin'],
			'rp_id' => $ctx['rp_id'],
			'redirect' => SA_Security::safe_redirect( $redirect, home_url( '/' ) ),
			'created_at' => time(),
			'expires_at' => time() + self::CHALLENGE_TTL,
		);
		set_transient( self::challenge_key( $id ), $record, self::CHALLENGE_TTL );
		$stored = get_transient( self::challenge_key( $id ) );
		return is_array( $stored ) && $stored === $record ? $record : array();
	}

	private static function consume_challenge( $id, $purpose, $user_id ) {
		$id = strtolower( sanitize_text_field( (string) $id ) );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id ) ) {
			return array();
		}
		/* add_option is an atomic unique-key replay claim even with object cache. */
		if ( ! add_option( self::challenge_claim_key( $id ), (string) time(), '', false ) ) {
			return array();
		}
		$key = self::challenge_key( $id );
		$record = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $record ) || sanitize_key( $record['purpose'] ?? '' ) !== sanitize_key( $purpose ) || absint( $record['expires_at'] ?? 0 ) <= time() ) {
			return array();
		}
		if ( absint( $record['user_id'] ?? 0 ) !== absint( $user_id ) || ! hash_equals( (string) ( $record['fingerprint'] ?? '' ), SA_Security::client_fingerprint() ) ) {
			return array();
		}
		$ctx = self::rp_context();
		if ( ! hash_equals( (string) ( $record['origin'] ?? '' ), $ctx['origin'] ) || ! hash_equals( (string) ( $record['rp_id'] ?? '' ), $ctx['rp_id'] ) ) {
			return array();
		}
		return $record;
	}

	/** Pure validation helper exposed for no-network CI. */
	public static function validate_client_data( $raw, $expected_type, $expected_challenge ) {
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['type'], $data['challenge'], $data['origin'] ) ) {
			return new WP_Error( 'sauth_webauthn_client_data', 'Client data is malformed.' );
		}
		if ( ! hash_equals( (string) $expected_type, (string) $data['type'] ) || ! hash_equals( (string) $expected_challenge, (string) $data['challenge'] ) ) {
			return new WP_Error( 'sauth_webauthn_challenge', 'WebAuthn challenge mismatch.' );
		}
		$ctx = self::rp_context();
		if ( ! hash_equals( $ctx['origin'], (string) $data['origin'] ) ) {
			return new WP_Error( 'sauth_webauthn_origin', 'WebAuthn origin mismatch.' );
		}
		if ( ! empty( $data['crossOrigin'] ) ) {
			return new WP_Error( 'sauth_webauthn_cross_origin', 'Cross-origin WebAuthn response rejected.' );
		}
		return $data;
	}

	/** Decode the attestationObject and require complete canonical CBOR input. */
	public static function parse_attestation_object( $raw ) {
		$offset = 0;
		$value = self::cbor_decode_item( (string) $raw, $offset, 0 );
		if ( is_wp_error( $value ) || ! is_array( $value ) || $offset !== strlen( (string) $raw ) ) {
			return new WP_Error( 'sauth_webauthn_attestation_cbor', 'Attestation object is malformed.' );
		}
		if ( ! isset( $value['fmt'], $value['authData'], $value['attStmt'] ) || ! is_string( $value['fmt'] ) || ! is_string( $value['authData'] ) || ! is_array( $value['attStmt'] ) ) {
			return new WP_Error( 'sauth_webauthn_attestation_shape', 'Attestation object fields are invalid.' );
		}
		return array( 'fmt' => $value['fmt'], 'auth_data' => $value['authData'], 'att_stmt' => $value['attStmt'] );
	}

	/** Parse authenticatorData and, during creation, its server-trusted COSE key. */
	public static function parse_authenticator_data( $raw, $expect_attested = false ) {
		$raw = (string) $raw;
		if ( strlen( $raw ) < 37 ) {
			return new WP_Error( 'sauth_webauthn_authdata', 'Authenticator data is too short.' );
		}
		$flags = ord( $raw[32] );
		$count = unpack( 'Ncounter', substr( $raw, 33, 4 ) );
		$result = array(
			'rp_id_hash' => substr( $raw, 0, 32 ),
			'user_present' => (bool) ( $flags & 0x01 ),
			'user_verified' => (bool) ( $flags & 0x04 ),
			'backup_eligible' => (bool) ( $flags & 0x08 ),
			'backup_state' => (bool) ( $flags & 0x10 ),
			'attested' => (bool) ( $flags & 0x40 ),
			'extensions' => (bool) ( $flags & 0x80 ),
			'sign_count' => isset( $count['counter'] ) ? (int) sprintf( '%u', $count['counter'] ) : 0,
		);
		if ( $result['backup_state'] && ! $result['backup_eligible'] ) {
			return new WP_Error( 'sauth_webauthn_backup_flags', 'Invalid backup flag combination.' );
		}
		if ( $expect_attested ) {
			if ( ! $result['attested'] || strlen( $raw ) < 55 ) {
				return new WP_Error( 'sauth_webauthn_attested_missing', 'Attested credential data missing.' );
			}
			$length = unpack( 'nlength', substr( $raw, 53, 2 ) );
			$credential_length = isset( $length['length'] ) ? absint( $length['length'] ) : 0;
			if ( $credential_length < 16 || $credential_length > 1024 || strlen( $raw ) < 55 + $credential_length + 1 ) {
				return new WP_Error( 'sauth_webauthn_credential_length', 'Credential ID length is invalid.' );
			}
			$result['credential_id'] = substr( $raw, 55, $credential_length );
			$offset = 55 + $credential_length;
			$cose = self::cbor_decode_item( $raw, $offset, 0 );
			if ( is_wp_error( $cose ) || ! is_array( $cose ) ) {
				return new WP_Error( 'sauth_webauthn_cose_key', 'Credential public key is malformed.' );
			}
			$result['credential_public_key'] = $cose;
		}
		return $result;
	}

	/** Convert server-parsed COSE EC2/RSA public key to an OpenSSL SPKI PEM. */
	public static function cose_public_key_to_pem( $cose ) {
		if ( ! is_array( $cose ) || ! isset( $cose[1], $cose[3] ) ) {
			return new WP_Error( 'sauth_cose_shape', 'COSE key is incomplete.' );
		}
		$kty = intval( $cose[1] );
		$alg = intval( $cose[3] );
		if ( 2 === $kty && -7 === $alg ) {
			if ( intval( $cose[-1] ?? 0 ) !== 1 || ! isset( $cose[-2], $cose[-3] ) || ! is_string( $cose[-2] ) || ! is_string( $cose[-3] ) || 32 !== strlen( $cose[-2] ) || 32 !== strlen( $cose[-3] ) ) {
				return new WP_Error( 'sauth_cose_ec2', 'Unsupported EC2 key.' );
			}
			$algorithm_identifier = hex2bin( '301306072a8648ce3d020106082a8648ce3d030107' );
			$point = "\x04" . $cose[-2] . $cose[-3];
			$der = self::asn1( 0x30, $algorithm_identifier . self::asn1( 0x03, "\x00" . $point ) );
			$pem = self::pem_from_der( $der );
			return self::public_key_matches_algorithm( $pem, -7 ) ? array( 'pem' => $pem, 'algorithm' => -7 ) : new WP_Error( 'sauth_cose_ec2_pem', 'EC2 public key is invalid.' );
		}
		if ( 3 === $kty && -257 === $alg ) {
			if ( ! isset( $cose[-1], $cose[-2] ) || ! is_string( $cose[-1] ) || ! is_string( $cose[-2] ) || strlen( $cose[-1] ) < 128 || strlen( $cose[-1] ) > 1024 || strlen( $cose[-2] ) < 1 || strlen( $cose[-2] ) > 8 ) {
				return new WP_Error( 'sauth_cose_rsa', 'Unsupported RSA key.' );
			}
			$rsa = self::asn1( 0x30, self::asn1_integer( $cose[-1] ) . self::asn1_integer( $cose[-2] ) );
			$algorithm_identifier = hex2bin( '300d06092a864886f70d0101010500' );
			$der = self::asn1( 0x30, $algorithm_identifier . self::asn1( 0x03, "\x00" . $rsa ) );
			$pem = self::pem_from_der( $der );
			return self::public_key_matches_algorithm( $pem, -257 ) ? array( 'pem' => $pem, 'algorithm' => -257 ) : new WP_Error( 'sauth_cose_rsa_pem', 'RSA public key is invalid.' );
		}
		return new WP_Error( 'sauth_cose_algorithm', 'Only ES256 and RS256 passkeys are accepted.' );
	}

	/** Pure signature helper exposed for CI. */
	public static function verify_signature( $signed_data, $signature, $public_key_pem, $algorithm ) {
		if ( ! function_exists( 'openssl_verify' ) || ! in_array( intval( $algorithm ), array( -7, -257 ), true ) ) {
			return false;
		}
		$key = openssl_pkey_get_public( (string) $public_key_pem );
		if ( false === $key ) {
			return false;
		}
		$result = openssl_verify( (string) $signed_data, (string) $signature, $key, OPENSSL_ALGO_SHA256 );
		if ( is_resource( $key ) ) {
			openssl_free_key( $key );
		}
		return 1 === $result;
	}

	private static function public_key_matches_algorithm( $pem, $algorithm ) {
		if ( ! function_exists( 'openssl_pkey_get_details' ) ) {
			return false;
		}
		$key = openssl_pkey_get_public( (string) $pem );
		if ( false === $key ) {
			return false;
		}
		$details = openssl_pkey_get_details( $key );
		if ( is_resource( $key ) ) {
			openssl_free_key( $key );
		}
		if ( ! is_array( $details ) || ! isset( $details['type'] ) ) {
			return false;
		}
		return ( -7 === intval( $algorithm ) && defined( 'OPENSSL_KEYTYPE_EC' ) && OPENSSL_KEYTYPE_EC === $details['type'] )
			|| ( -257 === intval( $algorithm ) && OPENSSL_KEYTYPE_RSA === $details['type'] );
	}

	private static function cbor_decode_item( $data, &$offset, $depth ) {
		$data = (string) $data;
		if ( $depth > 12 || $offset >= strlen( $data ) ) {
			return new WP_Error( 'sauth_cbor_bounds', 'CBOR bounds exceeded.' );
		}
		$initial = ord( $data[ $offset++ ] );
		$major = $initial >> 5;
		$additional = $initial & 0x1f;
		$length = self::cbor_length( $data, $offset, $additional );
		if ( is_wp_error( $length ) ) {
			return $length;
		}
		if ( 0 === $major ) {
			return $length;
		}
		if ( 1 === $major ) {
			return -1 - $length;
		}
		if ( 2 === $major || 3 === $major ) {
			if ( $length > 1048576 || $offset + $length > strlen( $data ) ) {
				return new WP_Error( 'sauth_cbor_string_bounds', 'CBOR string is invalid.' );
			}
			$value = substr( $data, $offset, $length );
			$offset += $length;
			return $value;
		}
		if ( 4 === $major ) {
			if ( $length > 256 ) {
				return new WP_Error( 'sauth_cbor_array_size', 'CBOR array too large.' );
			}
			$out = array();
			for ( $i = 0; $i < $length; $i++ ) {
				$item = self::cbor_decode_item( $data, $offset, $depth + 1 );
				if ( is_wp_error( $item ) ) {
					return $item;
				}
				$out[] = $item;
			}
			return $out;
		}
		if ( 5 === $major ) {
			if ( $length > 256 ) {
				return new WP_Error( 'sauth_cbor_map_size', 'CBOR map too large.' );
			}
			$out = array();
			for ( $i = 0; $i < $length; $i++ ) {
				$key = self::cbor_decode_item( $data, $offset, $depth + 1 );
				$value = self::cbor_decode_item( $data, $offset, $depth + 1 );
				if ( is_wp_error( $key ) || is_wp_error( $value ) || ! ( is_int( $key ) || is_string( $key ) ) ) {
					return new WP_Error( 'sauth_cbor_map_key', 'CBOR map key is invalid.' );
				}
				if ( array_key_exists( $key, $out ) ) {
					return new WP_Error( 'sauth_cbor_duplicate_key', 'Duplicate CBOR map key rejected.' );
				}
				$out[ $key ] = $value;
			}
			return $out;
		}
		if ( 7 === $major && $additional >= 20 && $additional <= 22 ) {
			return 20 === $additional ? false : ( 21 === $additional ? true : null );
		}
		return new WP_Error( 'sauth_cbor_type', 'Unsupported CBOR type.' );
	}

	private static function cbor_length( $data, &$offset, $additional ) {
		if ( $additional < 24 ) {
			return $additional;
		}
		$bytes = 24 === $additional ? 1 : ( 25 === $additional ? 2 : ( 26 === $additional ? 4 : ( 27 === $additional ? 8 : 0 ) ) );
		if ( 0 === $bytes || $offset + $bytes > strlen( $data ) ) {
			return new WP_Error( 'sauth_cbor_length', 'Indefinite or invalid CBOR length rejected.' );
		}
		$chunk = substr( $data, $offset, $bytes );
		$offset += $bytes;
		if ( 1 === $bytes ) {
			return ord( $chunk );
		}
		if ( 2 === $bytes ) {
			$value = unpack( 'nvalue', $chunk );
			return (int) $value['value'];
		}
		if ( 4 === $bytes ) {
			$value = unpack( 'Nvalue', $chunk );
			return (int) sprintf( '%u', $value['value'] );
		}
		$value = unpack( 'Nhigh/Nlow', $chunk );
		$number = (float) $value['high'] * 4294967296.0 + (float) $value['low'];
		if ( $number > 1048576 ) {
			return new WP_Error( 'sauth_cbor_length_large', 'CBOR length exceeds bounded parser limit.' );
		}
		return (int) $number;
	}

	private static function asn1( $tag, $body ) {
		return chr( $tag ) . self::asn1_length( strlen( $body ) ) . $body;
	}

	private static function asn1_length( $length ) {
		$length = absint( $length );
		if ( $length < 128 ) {
			return chr( $length );
		}
		$bytes = ltrim( pack( 'N', $length ), "\x00" );
		return chr( 0x80 | strlen( $bytes ) ) . $bytes;
	}

	private static function asn1_integer( $bytes ) {
		$bytes = ltrim( (string) $bytes, "\x00" );
		if ( '' === $bytes ) {
			$bytes = "\x00";
		}
		if ( ord( $bytes[0] ) & 0x80 ) {
			$bytes = "\x00" . $bytes;
		}
		return self::asn1( 0x02, $bytes );
	}

	private static function pem_from_der( $der ) {
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private static function rp_context() {
		$parts = wp_parse_url( home_url( '/' ) );
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host = isset( $parts['host'] ) ? strtolower( rtrim( (string) $parts['host'], '.' ) ) : '';
		$port = isset( $parts['port'] ) ? absint( $parts['port'] ) : 0;
		$origin = $scheme . '://' . $host;
		if ( $port && ! ( 'https' === $scheme && 443 === $port ) && ! ( 'http' === $scheme && 80 === $port ) ) {
			$origin .= ':' . $port;
		}
		return array( 'scheme' => $scheme, 'rp_id' => $host, 'origin' => $origin );
	}

	public static function authentication_ready() {
		return self::environment_ready();
	}

	private static function environment_ready() {
		$ctx = self::rp_context();
		$local = in_array( $ctx['rp_id'], array( 'localhost', '127.0.0.1', '::1' ), true );
		$schema_ready = self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' );
		$table_ready = $schema_ready && self::table_schema_ready();
		return '' !== $ctx['rp_id']
			&& ( 'https' === $ctx['scheme'] || ( $local && 'http' === $ctx['scheme'] ) )
			&& function_exists( 'openssl_verify' )
			&& $schema_ready
			&& $table_ready
			&& SA_Membership_Adapter::available()
			&& SAUTH_Account_Contract::provider_available();
	}

	private static function rp_id_hash() {
		return hash( 'sha256', self::rp_context()['rp_id'], true );
	}

	private static function user_handle( $user_id, $create ) {
		$user_id = absint( $user_id );
		$stored = $user_id ? (string) get_user_meta( $user_id, self::USER_HANDLE_META, true ) : '';
		$decoded = self::base64url_decode( $stored );
		if ( false !== $decoded && strlen( $decoded ) >= 16 && strlen( $decoded ) <= 64 ) {
			return $stored;
		}
		if ( ! $create || ! $user_id ) {
			return '';
		}
		$raw = self::secure_random( 32 );
		if ( '' === $raw ) {
			return '';
		}
		$encoded = self::base64url_encode( $raw );
		if ( false === update_user_meta( $user_id, self::USER_HANDLE_META, $encoded ) ) {
			return '';
		}
		return hash_equals( $encoded, (string) get_user_meta( $user_id, self::USER_HANDLE_META, true ) ) ? $encoded : '';
	}

	private static function credential_hash( $raw_id ) {
		return hash( 'sha256', (string) $raw_id );
	}

	private static function credential_exists( $hash ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::table() . " WHERE credential_lookup_hash=%s LIMIT 1", $hash ) );
	}

	private static function credential_by_hash( $hash ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE credential_lookup_hash=%s LIMIT 1", $hash ), ARRAY_A );
		return is_array( $row ) ? $row : array();
	}

	private static function credentials_for_user( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE user_id=%d AND status='active' ORDER BY last_used_at DESC, created_at DESC LIMIT %d", absint( $user_id ), self::MAX_CREDENTIALS ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	private static function mark_credential_compromised( array $credential ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array( 'status' => 'compromised', 'revoked_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => absint( $credential['id'] ), 'status' => 'active' ),
			array( '%s','%s','%s' ),
			array( '%d','%s' )
		);
		self::clear_assurance_for_user( absint( $credential['user_id'] ) );
		SA_Membership_Adapter::audit( 'passkey_counter_regression', absint( $credential['user_id'] ) );
	}

	private static function authentication_failure( $user_id, $reason ) {
		$user_id = absint( $user_id );
		SAUTH_Login_Risk::record_failure( $user_id, $reason, 100 );
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user_id, $user_id, array( 'method' => 'passkey', 'reason' => sanitize_key( $reason ) ), 'security' );
		if ( $user_id ) {
			SA_Membership_Adapter::audit( 'passkey_authentication_failed', $user_id, array( 'reason' => sanitize_key( $reason ) ) );
		}
		self::json_error( 'passkey_not_accepted' );
	}

	private static function decode_b64url_field( $field, $optional = false ) {
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		if ( '' === $value && $optional ) {
			return '';
		}
		$decoded = self::base64url_decode( $value );
		return false === $decoded ? '' : $decoded;
	}

	public static function base64url_encode( $raw ) {
		return rtrim( strtr( base64_encode( (string) $raw ), '+/', '-_' ), '=' );
	}

	public static function base64url_decode( $encoded ) {
		$encoded = (string) $encoded;
		if ( '' === $encoded || ! preg_match( '/^[A-Za-z0-9_-]+$/', $encoded ) ) {
			return false;
		}
		$padding = strlen( $encoded ) % 4;
		if ( $padding ) {
			$encoded .= str_repeat( '=', 4 - $padding );
		}
		return base64_decode( strtr( $encoded, '-_', '+/' ), true );
	}

	private static function secure_random( $bytes ) {
		try {
			return random_bytes( max( 16, absint( $bytes ) ) );
		} catch ( Exception $exception ) {
			return '';
		}
	}

	private static function sanitize_transports( $input ) {
		$items = is_array( $input ) ? $input : explode( ',', (string) $input );
		$allowed = array( 'usb', 'nfc', 'ble', 'internal', 'hybrid' );
		$out = array();
		foreach ( $items as $item ) {
			$item = sanitize_key( (string) $item );
			if ( in_array( $item, $allowed, true ) ) {
				$out[] = $item;
			}
		}
		return implode( ',', array_values( array_unique( $out ) ) );
	}

	private static function transports_array( $value ) {
		return array_values( array_filter( explode( ',', (string) $value ) ) );
	}

	private static function pending_assurance_key( $user_id ) {
		return 'sauth_pk_pending_' . absint( $user_id ) . '_' . substr( SA_Security::client_fingerprint(), 0, 20 );
	}

	private static function session_assurance_key( $user_id, $token ) {
		return 'sauth_pk_session_' . absint( $user_id ) . '_' . substr( hash_hmac( 'sha256', (string) $token, wp_salt( 'logged_in' ) ), 0, 32 );
	}

	private static function session_binding( $token ) {
		return hash_hmac( 'sha256', 'sauth-passkey-session|' . (string) $token, wp_salt( 'logged_in' ) );
	}

	private static function challenge_key( $id ) {
		return 'sauth_pk_ch_' . md5( (string) $id );
	}

	private static function challenge_claim_key( $id ) {
		return 'sauth_pk_claim_' . md5( (string) $id );
	}

	private static function clear_assurance_for_user( $user_id ) {
		delete_transient( self::pending_assurance_key( $user_id ) );
		$token = (string) wp_get_session_token();
		if ( '' !== $token ) {
			delete_transient( self::session_assurance_key( $user_id, $token ) );
		}
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sauth_passkeys';
	}

	private static function display_time( $mysql ) {
		$time = strtotime( (string) $mysql . ' UTC' );
		return $time ? wp_date( 'M j, Y H:i', $time ) : 'Unknown';
	}

	private static function json_error( $code, $status = 400 ) {
		wp_send_json_error( array( 'code' => sanitize_key( (string) $code ), 'message' => 'The passkey operation could not be completed safely.' ), absint( $status ) );
	}

	public static function cleanup() {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . self::table() . " WHERE status IN ('revoked','compromised') AND revoked_at IS NOT NULL AND revoked_at < %s", $cutoff ) );
		$claim_cutoff = time() - DAY_IN_SECONDS;
		$like = $wpdb->esc_like( 'sauth_pk_claim_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $like, $claim_cutoff ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['sabri-authentication-passkeys'] = array(
			'exporter_friendly_name' => __( 'Sabri Authentication Passkeys', 'sabri-authentication' ),
			'callback' => array( __CLASS__, 'privacy_export' ),
		);
		return $exporters;
	}

	public static function privacy_export( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$page = max( 1, absint( $page ) );
		$limit = 50;
		$offset = ( $page - 1 ) * $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT public_id,nickname,status,created_at,last_used_at,revoked_at,attachment,backup_eligible FROM ' . self::table() . ' WHERE user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',
				$user->ID,
				$limit,
				$offset
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();
		$data = array();
		foreach ( $rows as $credential ) {
			$data[] = array(
				'group_id' => 'sabri-authentication-passkeys',
				'group_label' => __( 'Passkeys', 'sabri-authentication' ),
				'item_id' => 'passkey-' . sanitize_key( $credential['public_id'] ),
				'data' => array(
					array( 'name' => 'Nickname', 'value' => (string) $credential['nickname'] ),
					array( 'name' => 'Status', 'value' => (string) $credential['status'] ),
					array( 'name' => 'Created', 'value' => (string) $credential['created_at'] ),
					array( 'name' => 'Last used', 'value' => (string) $credential['last_used_at'] ),
					array( 'name' => 'Revoked', 'value' => (string) $credential['revoked_at'] ),
					array( 'name' => 'Authenticator type', 'value' => (string) $credential['attachment'] ),
					array( 'name' => 'Sync capable', 'value' => ! empty( $credential['backup_eligible'] ) ? 'Yes' : 'No' ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $rows ) < $limit );
	}

	public static function register_eraser( $erasers ) {
		$erasers['sabri-authentication-passkeys'] = array(
			'eraser_friendly_name' => __( 'Sabri Authentication Passkeys', 'sabri-authentication' ),
			'callback' => array( __CLASS__, 'privacy_erase' ),
		);
		return $erasers;
	}

	public static function privacy_erase( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$user_id = absint( $user->ID );
		global $wpdb;
		$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d', $user_id ) );
		$handle_before = function_exists( 'metadata_exists' ) && metadata_exists( 'user', $user_id, self::USER_HANDLE_META );
		$epoch_before = class_exists( 'SAUTH_Passkey_Runtime' ) && function_exists( 'metadata_exists' ) && metadata_exists( 'user', $user_id, SAUTH_Passkey_Runtime::EPOCH_META );
		$deleted = $wpdb->delete( self::table(), array( 'user_id' => $user_id ), array( '%d' ) );
		delete_user_meta( $user_id, self::USER_HANDLE_META );
		if ( class_exists( 'SAUTH_Passkey_Runtime' ) ) {
			SAUTH_Passkey_Runtime::invalidate_user_assurance( $user_id );
			delete_user_meta( $user_id, SAUTH_Passkey_Runtime::EPOCH_META );
		}
		self::clear_assurance_for_user( $user_id );

		$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d', $user_id ) );
		$handle_retained = function_exists( 'metadata_exists' ) && metadata_exists( 'user', $user_id, self::USER_HANDLE_META );
		$epoch_retained = class_exists( 'SAUTH_Passkey_Runtime' ) && function_exists( 'metadata_exists' ) && metadata_exists( 'user', $user_id, SAUTH_Passkey_Runtime::EPOCH_META );
		$failed = false === $deleted || $remaining > 0 || $handle_retained || $epoch_retained;
		$had_data = $before > 0 || $handle_before || $epoch_before;
		return array(
			'items_removed' => ! $failed && $had_data,
			'items_retained' => $failed,
			'messages' => $failed ? array( __( 'Some passkey authentication data could not be erased. An administrator must review the retained data before the request is considered complete.', 'sabri-authentication' ) ) : array(),
			'done' => true,
		);
	}

}
