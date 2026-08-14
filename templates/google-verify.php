<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-google-verify-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Authentication security</span>
		<h1 id="sa-google-verify-title">Google Authentication Step Retired</h1>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<div class="sa-notice sa-notice-error">This older Authenticator/recovery-code step belonged to a retired File 00 MFA workflow. Current Google sign-in uses verified Google OIDC and the File 02 risk policy; elevated risk may require a separate File 02 passkey sign-in. Google link and unlink changes require a fresh File 02 passkey assurance.</div>
		<a class="sa-primary-button" href="<?php echo esc_url( SA_Membership_Adapter::login_url() ); ?>">Start Secure Log In Again</a>
	</section>
</main>
