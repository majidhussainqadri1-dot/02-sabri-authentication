<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1>Sabri Authentication and Google Sign-In</h1>
	<?php
	$settings_saved = false;
	if ( isset( $_GET['updated_token'] ) ) {
		$receipt = sanitize_text_field( wp_unslash( $_GET['updated_token'] ) );
		$key = 'sauth_settings_saved_' . get_current_user_id();
		$expected = (string) get_transient( $key );
		$settings_saved = '' !== $receipt && '' !== $expected && hash_equals( $expected, hash( 'sha256', $receipt ) );
		if ( $settings_saved ) { delete_transient( $key ); }
	}
	?>
	<?php if ( $settings_saved ) : ?><div class="notice notice-success"><p>Settings saved and verified.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error"><p>
			<?php
			$error = sanitize_key( wp_unslash( $_GET['error'] ) );
			$messages = array(
				'invalid_client_id' => 'The Google Client ID format is invalid.',
				'encryption_failed' => 'The Google Client Secret could not be encrypted. Google sign-in remains disabled.',
				'not_ready'         => 'Google sign-in cannot be enabled until Membership Core, HTTPS, Client ID, a dedicated SA_MASTER_KEY, and encrypted Client Secret are ready.',
				'dependency_unavailable' => 'Membership Core/account-contract readiness is unavailable; settings were not changed.',
				'settings_store_failed' => 'The complete Google settings unit could not be stored and was rolled back.',
				'settings_receipt_failed' => 'Settings may have been written, but the success receipt could not be verified. Reload and inspect the stored values before relying on them.',
			);
			echo esc_html( isset( $messages[ $error ] ) ? $messages[ $error ] : 'The settings could not be saved.' );
			?>
		</p></div>
	<?php endif; ?>

	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:920px;margin:18px 0">
		<div class="card"><h2><?php echo esc_html( number_format_i18n( $counts['total_users'] ) ); ?></h2><p>Total WordPress users</p></div>
		<div class="card"><h2><?php echo $dependency_ready ? 'Ready' : 'Missing'; ?></h2><p>Membership Core dependency</p></div>
		<div class="card"><h2><?php echo SA_Google_OAuth::configured() ? 'Ready' : 'Disabled'; ?></h2><p>Google sign-in status</p></div>
		<div class="card"><h2><?php echo esc_html( number_format_i18n( $legacy_roles ) ); ?></h2><p>Legacy doctor-pending roles requiring review</p></div>
	</div>

	<div class="notice notice-info inline"><p><strong>Architecture:</strong> File 00 owns membership, verified identity, guardian status, roles/capabilities, verification and eligibility. File 02 owns registration orchestration, password authentication, Google OIDC/linking, passkeys, authentication assurance, recovery, risk and sessions. Retired File 00 authenticator/recovery codes are not File 02 authentication factors.</p></div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:920px">
		<input type="hidden" name="action" value="sa_save_auth_settings">
		<?php wp_nonce_field( 'sa_save_auth_settings', 'sa_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Google sign-in</th><td><label><input type="checkbox" name="google_enabled" value="1" <?php checked( get_option( 'sauth_google_enabled', get_option( 'sa_google_enabled', '0' ) ), '1' ); ?>> Enable only after credentials, HTTPS, consent settings, and staging validation are complete</label></td></tr>
			<tr><th scope="row"><label for="sa-client-id">Google Client ID</label></th><td><input id="sa-client-id" class="regular-text" type="text" name="google_client_id" value="<?php echo esc_attr( get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) ) ); ?>" autocomplete="off" placeholder="123456789.apps.googleusercontent.com"></td></tr>
			<tr><th scope="row"><label for="sa-client-secret">Google Client Secret</label></th><td><input id="sa-client-secret" class="regular-text" type="password" name="google_client_secret" value="" autocomplete="new-password" placeholder="Leave blank to keep the saved secret"><p class="description">Stored with authenticated AES-256-GCM encryption only when a strong dedicated <code>SA_MASTER_KEY</code> (32+ characters) is defined in <code>wp-config.php</code>. WordPress authentication salts are not accepted as the current secret-encryption authority.</p><label><input type="checkbox" name="clear_google_client_secret" value="1"> Remove the saved Client Secret and disable Google sign-in</label></td></tr>
			<tr><th scope="row">Authorized redirect URI</th><td><code style="user-select:all"><?php echo esc_html( SA_Google_OAuth::callback_url() ); ?></code><p class="description">Copy this exact HTTPS address into the Google Cloud Web OAuth client.</p></td></tr>
			<tr><th scope="row">Requested scopes</th><td><code>openid email profile</code><p class="description">Access and refresh tokens are not retained. A linked eligible Membership Core account is required; sensitive Google link/unlink changes require a fresh File 02 passkey assurance.</p></td></tr>
		</table>
		<?php submit_button( 'Save Authentication Settings' ); ?>
	</form>

	<hr>
	<h2>Production-readiness gates</h2>
	<ul style="list-style:disc;padding-left:22px;max-width:920px">
		<li>Membership Core must be active and its identity workflow must be accepted.</li>
		<li>The site and callback must use HTTPS.</li>
		<li>Google app home, Privacy Policy, and Terms must use the verified public domain.</li>
		<li>The exact redirect URI shown above must be registered.</li>
		<li>Link, unlink, linked login, passkey step-up, password recovery, logout, and privacy erasure must pass staging tests.</li>
		<li>Any user still assigned the deprecated <code>sabri_doctor_pending</code> role must be reviewed under Membership Core; File 02 no longer creates or changes roles.</li>
	</ul>
</div>
