<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
	<section class="sa-auth-card" aria-labelledby="sa-google-account-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker"><?php esc_html_e( "Authentication security", 'sabri-authentication' ); ?></span>
		<h1 id="sa-google-account-title"><?php esc_html_e( "Google Account Security", 'sabri-authentication' ); ?></h1>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>

		<?php if ( ! $eligible ) : ?>
			<div class="sa-notice sa-notice-error"><?php esc_html_e( "Your account must have an active approved membership before Google can be linked.", 'sabri-authentication' ); ?></div>
			<a class="sa-primary-button" href="<?php echo esc_url( $verify_url ); ?>"><?php esc_html_e( "View Membership Verification", 'sabri-authentication' ); ?></a>
			<a class="sa-secondary-button" href="<?php echo esc_url( $security_url ); ?>"><?php esc_html_e( "Open Security Center", 'sabri-authentication' ); ?></a>
		<?php elseif ( ! $google_ready ) : ?>
			<div class="sa-notice sa-notice-error"><?php esc_html_e( "Google sign-in and account linking are currently unavailable. This can occur while the provider is disabled, temporarily unhealthy, or authentication Safe Mode is active.", 'sabri-authentication' ); ?></div>
		<?php elseif ( ! $linked ) : ?>
			<p class="sa-intro">Link a Google account whose verified email exactly matches <strong><?php echo esc_html( $user->user_email ); ?></strong>. For this sensitive account change, first verify a File 02 passkey in the current session; the passkey is checked again when Google returns.</p>
			<a class="sa-google-button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'sa_google_start', 'flow' => 'link', 'redirect_to' => SA_Security::page_url( 'google_account' ) ), admin_url( 'admin-post.php' ) ), 'sa_google_link_start' ) ); ?>"><span><?php esc_html_e( "Link Matching Google Account", 'sabri-authentication' ); ?></span></a>
		<?php else : ?>
			<dl class="sa-definition">
				<div><dt><?php esc_html_e( "Status", 'sabri-authentication' ); ?></dt><dd><?php esc_html_e( "Linked", 'sabri-authentication' ); ?></dd></div>
				<div><dt><?php esc_html_e( "Google email", 'sabri-authentication' ); ?></dt><dd><?php echo esc_html( $google_email ); ?></dd></div>
				<div><dt><?php esc_html_e( "Linked at", 'sabri-authentication' ); ?></dt><dd><?php echo esc_html( $linked_at ? $linked_at : 'Recorded' ); ?></dd></div>
			</dl>
			<p class="sa-intro"><?php esc_html_e( "Unlinking is a sensitive account change. A fresh File 02 passkey assurance must already exist in this session.", 'sabri-authentication' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-form">
				<input type="hidden" name="action" value="sa_google_unlink">
				<?php wp_nonce_field( 'sa_google_unlink', 'sa_nonce' ); ?>
				<button class="sa-secondary-button" type="submit"><?php esc_html_e( "Unlink Google", 'sabri-authentication' ); ?></button>
			</form>
		<?php endif; ?>
	</section>
</main>
