<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
	<section class="sa-auth-card" aria-labelledby="sa-google-verify-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker"><?php esc_html_e( "Authentication security", 'sabri-authentication' ); ?></span>
		<h1 id="sa-google-verify-title"><?php esc_html_e( "Google Authentication Step Retired", 'sabri-authentication' ); ?></h1>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<div class="sa-notice sa-notice-error"><?php esc_html_e( "This older Authenticator/recovery-code step belonged to a retired File 00 MFA workflow. Current Google sign-in uses verified Google OIDC and the File 02 risk policy; elevated risk may require a separate File 02 passkey sign-in. Google link and unlink changes require a fresh File 02 passkey assurance.", 'sabri-authentication' ); ?></div>
		<a class="sa-primary-button" href="<?php echo esc_url( SA_Membership_Adapter::login_url() ); ?>"><?php esc_html_e( "Start Secure Log In Again", 'sabri-authentication' ); ?></a>
	</section>
</main>
