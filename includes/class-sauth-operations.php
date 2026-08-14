<?php

defined( 'ABSPATH' ) || exit;

/** File 02 operational safety, Safe Mode and guarded repair. */
final class SAUTH_Operations {
	const SAFE_MODE_OPTION      = 'sauth_safe_mode';
	const SAFE_MODE_ENTERED_AT  = 'sauth_safe_mode_last_entered';
	const REPAIR_LOCK_OPTION    = 'sauth_guarded_repair_lock';
	const REPAIR_LOCK_TTL       = 120;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 35 );
		add_action( 'admin_post_sauth_toggle_safe_mode', array( __CLASS__, 'toggle_safe_mode' ) );
		add_action( 'admin_post_sauth_guarded_repair', array( __CLASS__, 'run_repair' ) );
		/* admin_init runs before admin-ajax action dispatch, so a pre-issued passkey
		 * challenge cannot mutate while Safe Mode is active. */
		add_action( 'admin_init', array( __CLASS__, 'enforce_safe_mode_request_gate' ), 0 );
	}

	public static function safe_mode() {
		return '1' === (string) get_option( self::SAFE_MODE_OPTION, '0' );
	}

	/** Timestamp of the latest Safe Mode entry; retained after exit to kill pre-entry challenges. */
	public static function safe_mode_entered_at() {
		return absint( get_option( self::SAFE_MODE_ENTERED_AT, 0 ) );
	}

	public static function high_risk_actions_available() {
		return ! self::safe_mode()
			&& SA_Membership_Adapter::available()
			&& SAUTH_Account_Contract::provider_available();
	}

	public static function enforce_safe_mode_request_gate() {
		if ( ! self::safe_mode() ) { return; }
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $action ) { return; }
		$blocked = array(
			'sauth_passkey_begin_registration',
			'sauth_passkey_finish_registration',
			'sauth_passkey_begin_authentication',
			'sauth_passkey_finish_authentication',
			'sauth_passkey_revoke',
			'sa_google_start',
			'sa_google_callback',
			'sauth_google_registration_start',
			'sauth_google_registration_callback',
			'sa_register',
			'sauth_register',
			'sa_forgot_password',
			'sauth_forgot_password',
			'sa_reset_password',
			'sauth_reset_password',
			'sauth_verify_email',
			'sauth_resend_email_verification',
			'sa_google_unlink',
		);
		if ( ! in_array( $action, $blocked, true ) ) { return; }
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			wp_send_json_error( array( 'code' => 'safe_mode_active', 'message' => 'This authentication mutation is paused by Safe Mode.' ), 503 );
		}
		wp_die( esc_html__( 'This authentication mutation is paused by Safe Mode.', 'sabri-authentication' ), esc_html__( 'Safe Mode active', 'sabri-authentication' ), array( 'response' => 503, 'back_link' => true ) );
	}

	public static function admin_menu() {
		$parent = defined( 'SABRI_SHELL_VERSION' ) ? 'sabri-shell' : 'tools.php';
		add_submenu_page( $parent, __( 'Authentication System Check', 'sabri-authentication' ), __( 'Authentication System Check', 'sabri-authentication' ), 'manage_options', 'sabri-authentication-system-check', array( __CLASS__, 'render_page' ) );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$checks = self::system_check();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Sabri Authentication — System Check', 'sabri-authentication' ); ?></h1>
			<p><strong><?php echo esc_html__( 'Safe Mode:', 'sabri-authentication' ); ?></strong> <?php echo self::safe_mode() ? '<span style="color:#b42318">ON</span>' : '<span style="color:#067647">OFF</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Evidence</th></tr></thead><tbody>
			<?php foreach ( $checks as $check ) : ?><tr><td><?php echo esc_html( $check['label'] ); ?></td><td><strong><?php echo esc_html( strtoupper( $check['status'] ) ); ?></strong></td><td><?php echo esc_html( $check['evidence'] ); ?></td></tr><?php endforeach; ?>
			</tbody></table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px"><input type="hidden" name="action" value="sauth_toggle_safe_mode"><?php wp_nonce_field( 'sauth_toggle_safe_mode', 'sauth_nonce' ); ?><input type="hidden" name="enable" value="<?php echo self::safe_mode() ? '0' : '1'; ?>"><button class="button <?php echo self::safe_mode() ? 'button-primary' : ''; ?>" type="submit"><?php echo esc_html( self::safe_mode() ? 'Exit Safe Mode' : 'Enter Safe Mode' ); ?></button></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px"><input type="hidden" name="action" value="sauth_guarded_repair"><?php wp_nonce_field( 'sauth_guarded_repair', 'sauth_nonce' ); ?><button class="button" type="submit">Run Guarded Repair</button></form>
			<p><em>Guarded Repair is additive/idempotent. It reconciles File 02 schema, passkey schema and private pages; it does not purge File 00 identity or File 02 authentication evidence.</em></p>
		</div>
		<?php
	}

	public static function toggle_safe_mode() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) ); }
		check_admin_referer( 'sauth_toggle_safe_mode', 'sauth_nonce' );
		$enable = ! empty( $_POST['enable'] );
		$expected = $enable ? '1' : '0';
		if ( $enable ) {
			$entered = time();
			update_option( self::SAFE_MODE_ENTERED_AT, $entered, false );
			if ( $entered !== absint( get_option( self::SAFE_MODE_ENTERED_AT, 0 ) ) ) {
				self::redirect( 'error', 'Safe Mode could not establish its challenge-revocation epoch.' );
			}
		}
		update_option( self::SAFE_MODE_OPTION, $expected, false );
		if ( $expected !== (string) get_option( self::SAFE_MODE_OPTION, '' ) ) {
			self::redirect( 'error', 'Safe Mode state could not be stored safely.' );
		}
		if ( $enable ) {
			/* Contain active authenticated state immediately. Any pre-entry WebAuthn
			 * challenge is also rejected later by its created_at vs entered_at check. */
			if ( is_user_logged_in() && class_exists( 'WP_Session_Tokens' ) ) {
				WP_Session_Tokens::get_instance( get_current_user_id() )->destroy_others( wp_get_session_token() );
			}
			SAUTH_Provider_Health::reset( 'google' );
		}
		SA_Membership_Adapter::audit( $enable ? 'authentication_safe_mode_enabled' : 'authentication_safe_mode_disabled', get_current_user_id() );
		self::redirect( 'success', $enable ? 'Safe Mode enabled. Provider and strong-auth mutations are paused.' : 'Safe Mode disabled. New authentication challenges may be issued; pre-Safe-Mode challenges remain invalid.' );
	}

	public static function run_repair() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) ); }
		check_admin_referer( 'sauth_guarded_repair', 'sauth_nonce' );
		$lock = self::claim_repair_lock();
		if ( '' === $lock ) { self::redirect( 'error', 'Another guarded repair is already running. Wait and retry.' ); }
		try {
			if ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {
				self::redirect( 'error', 'File 00 readiness/contracts are unavailable; repair stopped before mutation.' );
			}
			$was_safe = self::safe_mode();
			if ( ! $was_safe ) {
				update_option( self::SAFE_MODE_OPTION, '1', false );
				update_option( self::SAFE_MODE_ENTERED_AT, time(), false );
				if ( '1' !== (string) get_option( self::SAFE_MODE_OPTION, '' ) ) { self::redirect( 'error', 'Guarded repair could not enter Safe Mode.' ); }
			}
			$core_repaired = SAUTH_Activator::repair();
			SAUTH_Passkeys::maybe_install( true );
			if ( ! $core_repaired || ! SAUTH_Activator::storage_ready() || ! SAUTH_Passkeys::authentication_ready() ) {
				SA_Membership_Adapter::audit( 'authentication_guarded_repair_postcondition_failed', get_current_user_id() );
				self::redirect( 'error', 'Guarded repair could not prove all core/passkey storage postconditions. Safe Mode remains enabled.' );
			}
			$checks = self::system_check();
			$bad = array_filter( $checks, static function ( $check ) { return 'ok' !== ( $check['status'] ?? '' ); } );
			if ( empty( $bad ) && ! $was_safe ) {
				update_option( self::SAFE_MODE_OPTION, '0', false );
			}
			if ( ! empty( $bad ) ) {
				SA_Membership_Adapter::audit( 'authentication_guarded_repair_incomplete', get_current_user_id(), array( 'failed_checks' => count( $bad ) ) );
				self::redirect( 'error', 'Guarded repair completed with unresolved checks. Safe Mode remains enabled.' );
			}
			SA_Membership_Adapter::audit( 'authentication_guarded_repair_completed', get_current_user_id() );
			self::redirect( 'success', 'Guarded repair completed and all checked postconditions passed.' );
		} catch ( Throwable $error ) {
			update_option( self::SAFE_MODE_OPTION, '1', false );
			SA_Membership_Adapter::audit( 'authentication_guarded_repair_failed', get_current_user_id(), array( 'reference' => substr( hash( 'sha256', get_class( $error ) . '|' . $error->getMessage() ), 0, 20 ) ) );
			self::redirect( 'error', 'Guarded repair failed safely. Safe Mode remains enabled.' );
		} finally {
			self::release_repair_lock( $lock );
		}
	}

	/** Current source/runtime checks only; staging/live acceptance is separate. */
	public static function system_check() {
		global $wpdb;
		$checks = array();
		$checks[] = self::check( 'File 00 runtime and DB parity', SA_Membership_Adapter::available(), SA_Membership_Adapter::available() ? 'Membership Core runtime, DB and CF-01 membership assurance are ready.' : 'Membership Core runtime/DB/Safe Mode/CF-01 readiness is unavailable.' );
		$checks[] = self::check( 'Account orchestration contract', SAUTH_Account_Contract::provider_available(), SAUTH_Account_Contract::provider_available() ? 'smc.authentication-account 1.1.0 provider is callable.' : 'Account provider unavailable or incompatible.' );
		$passkey_ready = class_exists( 'SAUTH_Passkey_Runtime' ) && class_exists( 'SAUTH_Passkeys' ) && SAUTH_Passkeys::authentication_ready();
		$checks[] = self::check( 'File 02 passkey authentication assurance', $passkey_ready, $passkey_ready ? 'File 02 passkey runtime, schema, table, HTTPS/origin and dependencies are ready.' : 'File 02 passkey authentication readiness is incomplete.' );
		$checks[] = self::check( 'Runtime version marker', SAUTH_VERSION === (string) get_option( 'sauth_version', '' ), 'Runtime=' . SAUTH_VERSION . '; stored=' . (string) get_option( 'sauth_version', '' ) );
		$checks[] = self::check( 'Database schema marker', SAUTH_DB_VERSION === (string) get_option( 'sauth_db_version', '' ), 'Expected=' . SAUTH_DB_VERSION . '; stored=' . (string) get_option( 'sauth_db_version', '' ) );
		foreach ( SAUTH_Activator::required_tables() as $name => $table ) {
			$exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			$checks[] = self::check( 'Table ' . $name, $exists, $exists ? $table . ' exists.' : $table . ' is missing.' );
		}
		$passkey_table = $wpdb->prefix . 'sauth_passkeys';
		$passkey_exists = $passkey_table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $passkey_table ) ) );
		$checks[] = self::check( 'Passkey table', $passkey_exists, $passkey_exists ? $passkey_table . ' exists.' : $passkey_table . ' is missing.' );
		$checks[] = self::check( 'HTTPS origin', is_ssl(), is_ssl() ? 'HTTPS detected.' : 'HTTPS is required for Google OIDC and WebAuthn.' );
		return $checks;
	}

	private static function check( $label, $ok, $evidence ) { return array( 'label' => (string) $label, 'status' => $ok ? 'ok' : 'fail', 'evidence' => (string) $evidence ); }
	private static function claim_repair_lock() {
		$token = SA_Security::random_token( 16 ); if ( '' === $token ) { return ''; }
		$value = array( 'token' => $token, 'expires' => time() + self::REPAIR_LOCK_TTL );
		if ( add_option( self::REPAIR_LOCK_OPTION, $value, '', false ) ) { return $token; }
		$current = get_option( self::REPAIR_LOCK_OPTION, array() );
		if ( is_array( $current ) && absint( $current['expires'] ?? 0 ) < time() ) { delete_option( self::REPAIR_LOCK_OPTION ); if ( add_option( self::REPAIR_LOCK_OPTION, $value, '', false ) ) { return $token; } }
		return '';
	}
	private static function release_repair_lock( $token ) { $current = get_option( self::REPAIR_LOCK_OPTION, array() ); if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) { delete_option( self::REPAIR_LOCK_OPTION ); } }
	private static function redirect( $type, $message ) { wp_safe_redirect( SA_Security::message_url( 'system_check', $type, $message, admin_url( 'admin.php?page=sabri-authentication-system-check' ) ) ); exit; }
}
