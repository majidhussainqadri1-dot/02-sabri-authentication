<?php defined( 'ABSPATH' ) || exit; $pages = (array) get_option( 'sa_page_map', array() ); ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-login-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Sabri Social Homeopathy Platform</span>
		<h1 id="sa-login-title">Welcome Back</h1><p class="sa-intro">Log in to comment, save, follow and use your personal services.</p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<?php include SA_DIR . 'templates/partials/google-button.php'; ?>
		<?php if ( $google_ready ) : ?><div class="sa-divider"><span>or use email</span></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-form">
			<input type="hidden" name="action" value="sa_login"><input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
			<?php wp_nonce_field( 'sa_login', 'sa_nonce' ); ?>
			<label for="sa-log">Email or Username</label><input id="sa-log" name="log" type="text" autocomplete="username" required>
			<label for="sa-pwd">Password</label><div class="sa-password-wrap"><input id="sa-pwd" name="pwd" type="password" autocomplete="current-password" required><button type="button" class="sa-show-password" aria-controls="sa-pwd">Show</button></div>
			<div class="sa-form-row"><label class="sa-check"><input type="checkbox" name="rememberme" value="1"> Remember me</label><a href="<?php echo ! empty( $pages['forgot'] ) ? esc_url( get_permalink( absint( $pages['forgot'] ) ) ) : esc_url( wp_lostpassword_url() ); ?>">Forgot password?</a></div>
			<button class="sa-primary-button" type="submit">Log In</button>
		</form>
		<?php if ( '1' === get_option( 'sa_allow_registration', '1' ) ) : ?><p class="sa-bottom-text">New here? <a href="<?php echo ! empty( $pages['signup'] ) ? esc_url( get_permalink( absint( $pages['signup'] ) ) ) : esc_url( wp_registration_url() ); ?>">Create an account</a></p><?php endif; ?>
		<p class="sa-data-note">Google sign-in uses only your verified account identifier, name, email and profile image for authentication and profile setup. Google tokens are not retained.<?php if ( get_privacy_policy_url() ) : ?> <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Privacy Policy</a><?php endif; ?></p>
	</section>
</main>
