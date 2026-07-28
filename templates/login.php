<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-login-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Sabri Social Homeopathy Platform</span>
		<h1 id="sa-login-title">Secure Member Access</h1>
		<p class="sa-intro">Membership Core verifies your identity, approval status, password, and mandatory two-factor code. File 02 does not create a parallel login account.</p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<a class="sa-primary-button" href="<?php echo esc_url( $member_login ); ?>">Sign In through Membership Core</a>
		<?php include SA_DIR . 'templates/partials/google-button.php'; ?>
		<p class="sa-data-note">Google sign-in works only after you explicitly link Google to an approved Membership Core account and confirm a current Authenticator or recovery code.</p>
	</section>
</main>
