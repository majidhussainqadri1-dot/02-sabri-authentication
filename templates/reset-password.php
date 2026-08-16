<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-reset-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Account recovery</span>
		<h1 id="sa-reset-title">Reset Your Password</h1>
		<p class="sa-intro">Use the one-time reset link sent to your email. Successful completion revokes all existing sessions.</p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<?php if ( empty( $key ) || empty( $login ) ) : ?>
			<div class="sa-inline-warning" role="alert">
				<p>A valid reset key and account login are required.</p>
			</div>
			<a class="sa-primary-button" href="<?php echo esc_url( SA_Security::page_url( 'forgot', wp_lostpassword_url() ) ); ?>">Request a New Reset Link</a>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="sa_reset_password">
				<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
				<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">
				<?php wp_nonce_field( 'sa_reset_password_' . $login, 'sa_nonce' ); ?>
				<p>
					<label for="sa-new-password">New password</label>
					<input id="sa-new-password" name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required aria-describedby="sa-password-help">
					<span id="sa-password-help" class="sa-field-help">Use at least 12 characters and avoid reused passwords.</span>
				</p>
				<p>
					<label for="sa-confirm-password">Confirm new password</label>
					<input id="sa-confirm-password" name="password_confirm" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required>
				</p>
				<button class="sa-primary-button" type="submit">Change Password and Sign Out Everywhere</button>
			</form>
		<?php endif; ?>
	</section>
</main>
