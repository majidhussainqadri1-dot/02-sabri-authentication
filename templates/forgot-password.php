<?php defined( 'ABSPATH' ) || exit; $pages = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) ); ?>
<main class="sa-auth-shell"><section class="sa-auth-card" aria-labelledby="sa-forgot-title">
	<div class="sa-brand-mark">SH</div><span class="sa-kicker">Secure account recovery</span><h1 id="sa-forgot-title">Forgot Password</h1><p class="sa-intro">Enter your email or username. For privacy, the response will not reveal whether an account exists.</p>
	<?php include SA_DIR . 'templates/partials/notice.php'; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-form"><input type="hidden" name="action" value="sa_forgot_password"><?php wp_nonce_field( 'sa_forgot_password', 'sa_nonce' ); ?><label for="sa-user-login">Email or Username</label><input id="sa-user-login" name="user_login" type="text" autocomplete="username" maxlength="320" required><button class="sa-primary-button" type="submit">Send Reset Link</button></form>
	<p class="sa-bottom-text"><a href="<?php echo ! empty( $pages['login'] ) ? esc_url( get_permalink( absint( $pages['login'] ) ) ) : esc_url( wp_login_url() ); ?>">Return to Log In</a></p>
</section></main>

