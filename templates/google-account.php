<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-google-account-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Authentication security</span>
		<h1 id="sa-google-account-title">Google Account Security</h1>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>

		<?php if ( ! $eligible ) : ?>
			<div class="sa-notice sa-notice-error">Your Membership Core account must be approved and protected by two-factor authentication before Google can be linked.</div>
			<a class="sa-primary-button" href="<?php echo esc_url( $verify_url ); ?>">View Membership Verification</a>
			<a class="sa-secondary-button" href="<?php echo esc_url( $security_url ); ?>">Open Security Center</a>
		<?php elseif ( ! $google_ready ) : ?>
			<div class="sa-notice sa-notice-error">Google sign-in is not enabled by the platform administrator.</div>
		<?php elseif ( ! $linked ) : ?>
			<p class="sa-intro">Link a Google account whose verified email exactly matches <strong><?php echo esc_html( $user->user_email ); ?></strong>. The process will also require your current Membership Core Authenticator or recovery code.</p>
			<a class="sa-google-button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'sa_google_start', 'flow' => 'link', 'redirect_to' => SA_Security::page_url( 'google_account' ) ), admin_url( 'admin-post.php' ) ), 'sa_google_link_start' ) ); ?>"><span>Link Matching Google Account</span></a>
		<?php else : ?>
			<dl class="sa-definition">
				<div><dt>Status</dt><dd>Linked</dd></div>
				<div><dt>Google email</dt><dd><?php echo esc_html( $google_email ); ?></dd></div>
				<div><dt>Linked at</dt><dd><?php echo esc_html( $linked_at ? $linked_at : 'Recorded' ); ?></dd></div>
			</dl>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-form">
				<input type="hidden" name="action" value="sa_google_unlink">
				<?php wp_nonce_field( 'sa_google_unlink', 'sa_nonce' ); ?>
				<label for="sa-unlink-code">Current Authenticator or recovery code</label>
				<input id="sa-unlink-code" name="second_factor" type="text" autocomplete="one-time-code" required>
				<button class="sa-secondary-button" type="submit">Unlink Google</button>
			</form>
		<?php endif; ?>
	</section>
</main>
