<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 operational controls: redacted System Check, reversible repair,
 * safe-mode gating and File 01/File 20 integration manifests.
 */
final class SAUTH_Operations {
	const SAFE_MODE_OPTION = 'sauth_safe_mode';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 40 );
		add_action( 'admin_post_sauth_run_repair', array( __CLASS__, 'handle_repair' ) );
		add_action( 'admin_post_sauth_toggle_safe_mode', array( __CLASS__, 'toggle_safe_mode' ) );
		add_filter( 'spf_module_manifests', array( __CLASS__, 'foundation_manifest' ) );
		add_filter( 'sabri_shell_route_manifests', array( __CLASS__, 'shell_manifest' ) );
		add_action( 'init', array( __CLASS__, 'announce_contracts' ), 5 );
	}

	public static function safe_mode() {
		return '1' === (string) get_option( self::SAFE_MODE_OPTION, '0' );
	}

	public static function high_risk_actions_available() {
		return ! self::safe_mode() && SA_Membership_Adapter::available();
	}

	public static function admin_menu() {
		$parent = defined( 'SABRI_SHELL_VERSION' ) ? 'sabri-shell' : 'options-general.php';
		add_submenu_page(
			$parent,
			'Sabri Authentication System Check',
			'Authentication Health',
			'manage_options',
			'sabri-authentication-health',
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = self::system_check();
		?>
		<div class="wrap">
			<h1>Sabri Authentication — System Check</h1>
			<p>This report is privacy-minimized. It never exposes passwords, reset keys, OAuth tokens, raw session tokens, full IP addresses, database credentials or private File 00 evidence.</p>
			<p><strong>Overall state:</strong> <?php echo esc_html( strtoupper( $report['overall'] ) ); ?></p>
			<table class="widefat striped">
				<thead><tr><th>Check</th><th>Status</th><th>Reason</th></tr></thead>
				<tbody>
				<?php foreach ( $report['checks'] as $check ) : ?>
					<tr>
						<td><?php echo esc_html( $check['label'] ); ?></td>
						<td><?php echo esc_html( strtoupper( $check['status'] ) ); ?></td>
						<td><?php echo esc_html( $check['reason'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Safe Mode</h2>
			<p>Safe Mode disables registration, provider linking and other high-risk authentication mutations while preserving public reading and safe local account recovery where WordPress can perform it correctly.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sauth_toggle_safe_mode">
				<input type="hidden" name="enabled" value="<?php echo self::safe_mode() ? '0' : '1'; ?>">
				<?php wp_nonce_field( 'sauth_toggle_safe_mode', 'sauth_nonce' ); ?>
				<?php submit_button( self::safe_mode() ? 'Disable Safe Mode' : 'Enable Safe Mode', 'secondary', 'submit', false ); ?>
			</form>

			<h2>Guarded Repair</h2>
			<p>The repair is idempotent and limited to File 02 tables, managed pages, expired local challenges, stale session projections and provider-health counters. It never edits File 00 membership, roles, guardian, verification or identity records.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sauth_run_repair">
				<?php wp_nonce_field( 'sauth_run_repair', 'sauth_nonce' ); ?>
				<label><input type="checkbox" name="confirm" value="1" required> I understand the repair scope.</label>
				<?php submit_button( 'Run File 02 Repair', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function system_check() {
		global $wpdb;
		$checks = array();
		$checks[] = self::check( 'plugin_version', 'Plugin and database version', defined( 'SA_VERSION' ) && SA_VERSION === (string) get_option( 'sa_version', '' ) && defined( 'SA_DB_VERSION' ) && SA_DB_VERSION === (string) get_option( 'sa_db_version', '' ), 'Runtime and stored versions match.', 'Run guarded repair after verifying the deployed package.' );
		$checks[] = self::check( 'membership', 'File 00 membership dependency', SA_Membership_Adapter::available(), 'Required File 00 assurance contract is available.', 'File 00 1.2.7+ with the approved assurance contract is unavailable or incompatible.' );
		$checks[] = self::check( 'account_contract', 'File 00 account-orchestration contract', SAUTH_Account_Contract::provider_available(), 'Registration, email completion and completion-state contract is available.', 'The smc.authentication-account provider contract is unavailable or incompatible.' );
		$checks[] = self::check( 'assurance', 'Step-up assurance contract', SA_Authentication_Assurance::provider_available(), 'File 00 step-up verification is available.', 'Risk challenges and provider linking will fail closed.' );
		$checks[] = self::check( 'https', 'HTTPS', is_ssl(), 'HTTPS is active for this request.', 'Authentication providers and sensitive account surfaces require HTTPS.' );
		$checks[] = self::check( 'safe_mode', 'Safe Mode', ! self::safe_mode(), 'Normal high-risk actions are enabled.', 'Safe Mode is active; high-risk mutations are intentionally disabled.', self::safe_mode() ? 'warning' : 'pass' );

		foreach ( SA_Activator::required_tables() as $label => $table ) {
			$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			$checks[] = self::check( 'table_' . sanitize_key( $label ), 'Database table: ' . $label, $exists, 'Present.', 'Missing; run guarded repair.' );
		}

		$page_map = (array) get_option( 'sa_page_map', array() );
		$required_pages = array_keys( SA_Activator::page_specs() );
		$missing_pages = array();
		foreach ( $required_pages as $key ) {
			$page_id = isset( $page_map[ $key ] ) ? absint( $page_map[ $key ] ) : 0;
			if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
				$missing_pages[] = $key;
			}
		}
		$checks[] = self::check( 'routes', 'Managed authentication routes', empty( $missing_pages ), 'All managed routes are published.', 'Missing: ' . implode( ', ', $missing_pages ) );

		$scheduled = array(
			SAUTH_Event_Outbox::CRON_HOOK,
			SAUTH_Email_Verification::CLEANUP_HOOK,
			'sauth_login_risk_cleanup',
			'sauth_session_registry_cleanup',
			'sauth_provider_health_cleanup',
		);
		$missing_cron = array();
		foreach ( $scheduled as $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				$missing_cron[] = $hook;
			}
		}
		$checks[] = self::check( 'cron', 'Scheduled maintenance hooks', empty( $missing_cron ), 'All required hooks are scheduled.', 'Missing: ' . implode( ', ', $missing_cron ), empty( $missing_cron ) ? 'pass' : 'warning' );

		$google_ready = SA_Google_OAuth::configured();
		$checks[] = self::check( 'google', 'Google OAuth', $google_ready, 'Configured and dependency-ready.', 'Optional provider is disabled, incomplete or unavailable.', $google_ready ? 'pass' : 'warning' );

		$providers = SAUTH_Provider_Health::all();
		foreach ( $providers as $provider => $state ) {
			$status = (string) $state['status'];
			$healthy = in_array( $status, array( 'healthy', 'unknown', 'half_open' ), true );
			$checks[] = self::check( 'provider_' . $provider, ucfirst( $provider ) . ' provider circuit', $healthy, 'State: ' . $status . '.', 'State: ' . $status . '; reason category: ' . ( $state['last_reason'] ?: 'unavailable' ) . '.', 'open' === $status ? 'fail' : ( 'degraded' === $status ? 'warning' : 'pass' ) );
		}

		$overall = 'pass';
		foreach ( $checks as $check ) {
			if ( 'fail' === $check['status'] ) {
				$overall = 'fail';
				break;
			}
			if ( 'warning' === $check['status'] ) {
				$overall = 'warning';
			}
		}
		return array(
			'contract'         => 'sauth.system-check',
			'contract_version' => '1.0.0',
			'generated_at'     => gmdate( 'c' ),
			'overall'          => $overall,
			'checks'           => $checks,
		);
	}

	public static function handle_repair() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) );
		}
		check_admin_referer( 'sauth_run_repair', 'sauth_nonce' );
		if ( empty( $_POST['confirm'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-authentication-health', 'repair' => 'not_confirmed' ), admin_url( 'options-general.php' ) ) );
			exit;
		}
		SA_Activator::repair();
		SAUTH_Email_Verification::cleanup();
		SAUTH_Login_Risk::cleanup();
		SAUTH_Session_Manager::cleanup();
		foreach ( array_keys( SAUTH_Provider_Health::all() ) as $provider ) {
			SAUTH_Provider_Health::reset( $provider );
		}
		SA_Membership_Adapter::audit( 'authentication_guarded_repair_completed', get_current_user_id() );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-authentication-health', 'repair' => 'complete' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function toggle_safe_mode() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) );
		}
		check_admin_referer( 'sauth_toggle_safe_mode', 'sauth_nonce' );
		$enabled = ! empty( $_POST['enabled'] ) ? '1' : '0';
		update_option( self::SAFE_MODE_OPTION, $enabled, false );
		SA_Membership_Adapter::audit( 'authentication_safe_mode_changed', get_current_user_id(), array( 'enabled' => '1' === $enabled ) );
		wp_safe_redirect( add_query_arg( 'page', 'sabri-authentication-health', admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * @param array<int|string,mixed> $manifests Existing foundation manifests.
	 * @return array<int|string,mixed>
	 */
	public static function foundation_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests['file02-authentication'] = self::module_manifest();
		return $manifests;
	}

	/**
	 * @param array<int|string,mixed> $manifests Existing shell manifests.
	 * @return array<int|string,mixed>
	 */
	public static function shell_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests['file02-authentication'] = array(
			'owner'       => 'File 02',
			'version'     => SA_VERSION,
			'layout'      => 'single-column-account',
			'cache'       => 'private-no-store',
			'routes'      => self::route_manifest(),
			'safe_mode'   => self::safe_mode(),
			'health_url'  => admin_url( 'options-general.php?page=sabri-authentication-health' ),
		);
		return $manifests;
	}

	public static function announce_contracts() {
		do_action( 'sauth_contract_manifest_ready', self::module_manifest() );
		do_action( 'spf_register_module_manifest', 'file02-authentication', self::module_manifest() );
		do_action( 'sabri_shell_register_route_manifest', 'file02-authentication', self::route_manifest() );
	}

	public static function module_manifest() {
		return array(
			'file'             => '02',
			'owner'            => 'Authentication and Accounts',
			'version'          => SA_VERSION,
			'database_version' => SA_DB_VERSION,
			'contracts'        => array(
				'consumer' => array( SAUTH_Account_Contract::CONTRACT_NAME => SAUTH_Account_Contract::CONTRACT_VERSION ),
				'producer' => array(
					'sauth.system-check' => '1.0.0',
					'sauth.account-completion-resolver' => '1.0.0',
					'sa.cf01.authentication-assurance' => SA_Authentication_Assurance::CONTRACT_VERSION,
				),
			),
			'routes'           => self::route_manifest(),
			'safe_mode'        => self::safe_mode(),
		);
	}

	public static function route_manifest() {
		$output = array();
		foreach ( SA_Activator::page_specs() as $key => $spec ) {
			$output[ $key ] = array(
				'owner'     => 'File 02',
				'route'     => '/' . trim( $spec['slug'], '/' ) . '/',
				'access'    => in_array( $key, array( 'sessions', 'google_account' ), true ) ? 'authenticated' : 'public-or-token',
				'index'     => 'noindex',
				'cache'     => 'no-store',
				'layout'    => 'single-column-account',
				'shortcode' => $spec['shortcode'],
			);
		}
		return $output;
	}

	private static function check( $id, $label, $passed, $pass_reason, $fail_reason, $forced_status = '' ) {
		$status = $forced_status ? $forced_status : ( $passed ? 'pass' : 'fail' );
		return array(
			'id'     => sanitize_key( (string) $id ),
			'label'  => sanitize_text_field( (string) $label ),
			'status' => $status,
			'reason' => sanitize_text_field( $passed ? (string) $pass_reason : (string) $fail_reason ),
		);
	}
}
