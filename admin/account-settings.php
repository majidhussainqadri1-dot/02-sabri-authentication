<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
	<h1>Sabri Accounts and Google Login</h1>
	<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
	<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;max-width:920px;margin:18px 0">
		<div class="card"><h2><?php echo esc_html( number_format_i18n( $counts['total_users'] ) ); ?></h2><p>Total registered users</p></div>
		<div class="card"><h2><?php echo SA_Google_OAuth::configured() ? 'Ready' : 'Not configured'; ?></h2><p>Google login status</p></div>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:920px">
		<input type="hidden" name="action" value="sa_save_auth_settings">
		<?php wp_nonce_field( 'sa_save_auth_settings', 'sa_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Public registration</th><td><label><input type="checkbox" name="allow_registration" value="1" <?php checked( get_option( 'sa_allow_registration', '1' ), '1' ); ?>> Allow new users to create accounts</label></td></tr>
			<tr><th scope="row">Google login</th><td><label><input type="checkbox" name="google_enabled" value="1" <?php checked( get_option( 'sa_google_enabled', '0' ), '1' ); ?>> Enable after credentials and Google consent settings are complete</label></td></tr>
			<tr><th scope="row"><label for="sa-client-id">Google Client ID</label></th><td><input id="sa-client-id" class="regular-text" type="text" name="google_client_id" value="<?php echo esc_attr( get_option( 'sa_google_client_id', '' ) ); ?>" autocomplete="off"></td></tr>
			<tr><th scope="row"><label for="sa-client-secret">Google Client Secret</label></th><td><input id="sa-client-secret" class="regular-text" type="password" name="google_client_secret" value="" autocomplete="new-password" placeholder="Leave blank to keep the saved secret"><p class="description">Stored encrypted with this WordPress installation's authentication salt.</p></td></tr>
			<tr><th scope="row">Authorized redirect URI</th><td><code style="user-select:all"><?php echo esc_html( SA_Google_OAuth::callback_url() ); ?></code><p class="description">Copy this exact HTTPS address into the Google Cloud Web OAuth client.</p></td></tr>
			<tr><th scope="row">Requested scopes</th><td><code>openid email profile</code><p class="description">The module requests only the minimum identity scopes and does not store Google access or refresh tokens.</p></td></tr>
		</table>
		<?php submit_button( 'Save Account Settings' ); ?>
	</form>
	<hr>
	<h2>Google production-readiness checklist</h2>
	<ul style="list-style:disc;padding-left:22px;max-width:920px">
		<li>Verify ownership of sabrihomeopathy.com in Google Search Console.</li>
		<li>Use the same public domain for the app home, Privacy Policy and Terms.</li>
		<li>Add the exact redirect URI shown above to the Web OAuth client.</li>
		<li>Keep Privacy Policy disclosures accurate for name, email and profile data.</li>
		<li>Use HTTPS and enable Google login only after the consent screen is ready.</li>
	</ul>
</div>

