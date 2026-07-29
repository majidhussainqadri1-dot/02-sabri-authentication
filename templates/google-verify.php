<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-google-verify-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Membership Core 2FA</span>
		<h1 id="sa-google-verify-title">Verify Google Authentication</h1>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<?php if ( empty( $challenge ) || empty( $data ) ) : ?>
			<div class="sa-notice sa-notice-error">This verification challenge is missing or expired. Start Google authentication again.</div>
			<a class="sa-primary-button" href="<?php echo esc_url( SA_Membership_Adapter::login_url() ); ?>">Return to Secure Log In</a>
		<?php else : ?>
			<p class="sa-intro">Google has verified the external identity. Enter your current Membership Core Authenticator or one-time recovery code to complete <?php echo 'link' === $data['operation'] ? 'account linking' : 'sign-in'; ?>.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-form">
				<input type="hidden" name="action" value="sa_google_verify">
				<input type="hidden" name="challenge" value="<?php echo esc_attr( $challenge ); ?>">
				<?php wp_nonce_field( 'sa_google_verify_' . $challenge, 'sa_nonce' ); ?>
				<label for="sa-google-code">Authenticator or recovery code</label>
				<input id="sa-google-code" name="second_factor" type="text" autocomplete="one-time-code" required autofocus>
				<button class="sa-primary-button" type="submit">Verify and Continue</button>
			</form>
		<?php endif; ?>
	</section>
</main>
