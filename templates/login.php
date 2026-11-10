<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
	<section class="sa-auth-card" aria-labelledby="sa-login-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Sabri Social Homeopathy Platform</span>
		<h1 id="sa-login-title"><?php esc_html_e( 'Secure Member Access', 'sabri-authentication' ); ?></h1>
		<p class="sa-intro"><?php esc_html_e( 'Access your account securely using a password, passkey, or verified Google sign-in.', 'sabri-authentication' ); ?></p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<?php if ( $password_ready ) : ?>
			<form class="sa-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<input type="hidden" name="action" value="sa_login">
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
				<?php wp_nonce_field( 'sa_login', 'sa_nonce' ); ?>
				<div class="sa-honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
				<label for="sa-user-login"><?php esc_html_e( 'Email or username', 'sabri-authentication' ); ?></label>
				<input id="sa-user-login" type="text" name="user_login" autocomplete="username webauthn" autocapitalize="none" maxlength="320" required>
				<label for="sa-user-password"><?php esc_html_e( 'Password', 'sabri-authentication' ); ?></label>
				<div class="sa-password-wrap">
					<input id="sa-user-password" type="password" name="password" autocomplete="current-password" minlength="12" maxlength="4096" required>
					<button class="sa-show-password" type="button" data-sa-toggle-password="sa-user-password" aria-controls="sa-user-password"><?php esc_html_e( 'Show', 'sabri-authentication' ); ?></button>
				</div>
				<div class="sa-form-row">
					<label class="sa-check"><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Keep me signed in on this device', 'sabri-authentication' ); ?></label>
					<a href="<?php echo esc_url( $forgot_url ); ?>">Forgot password?</a>
				</div>
				<button class="sa-primary-button" type="submit"><?php esc_html_e( 'Sign In Securely', 'sabri-authentication' ); ?></button>
			</form>
		<?php else : ?>
			<div class="sa-notice sa-notice-error" role="status"><?php esc_html_e( 'Password sign-in is temporarily unavailable because the required File 00 account contract is not ready. No fallback login or partial session will be created.', 'sabri-authentication' ); ?></div>
		<?php endif; ?>

		<div class="sa-form" data-sauth-passkey-login-region>
			<button class="sa-secondary-button" type="button" data-sauth-passkey-login><?php esc_html_e( 'Sign In with a Passkey', 'sabri-authentication' ); ?></button>
			<div class="sa-data-note" data-sauth-passkey-status role="status" aria-live="polite"><?php esc_html_e( 'Use a passkey stored on this device, your phone, or an approved security key. The private key never leaves your authenticator.', 'sabri-authentication' ); ?></div>
		</div>

		<?php include SA_DIR . 'templates/partials/google-button.php'; ?>
		<p class="sa-data-note"><?php esc_html_e( 'Google sign-in works only after explicit same-email linking to an eligible Membership Core account. Elevated device/network risk may require a separate File 02 passkey sign-in.', 'sabri-authentication' ); ?></p>
		<p class="sa-bottom-text"><?php esc_html_e( 'New to the platform?', 'sabri-authentication' ); ?> <a href="<?php echo esc_url( $signup_url ); ?>">Create a verified account</a></p>
	</section>
</main>
