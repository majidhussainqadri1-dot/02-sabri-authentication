<?php
defined( 'ABSPATH' ) || exit;
$redirect_to = wp_get_referer() ? wp_get_referer() : home_url( '/' );
$login_url   = SA_Membership_Adapter::login_url( $redirect_to );
$signup_url  = SA_Membership_Adapter::register_url();
?>
<main class="sa-auth-shell"><section class="sa-auth-card sa-access-card" aria-labelledby="sa-access-title">
	<div class="sa-brand-mark">SH</div>
	<h1 id="sa-access-title">Verified Account Required</h1>
	<p>Public content remains open. Sabri Authentication sign-in is required for comments, saving, following, messaging, publishing, and personal services; Membership Core remains the membership and eligibility authority.</p>
	<?php include SA_DIR . 'templates/partials/notice.php'; ?>
	<a class="sa-primary-button" href="<?php echo esc_url( $login_url ); ?>">Secure Log In</a>
	<a class="sa-secondary-button" href="<?php echo esc_url( $signup_url ); ?>">Create Verified Account</a>
	<a class="sa-text-link" href="<?php echo esc_url( $redirect_to ); ?>">Not Now</a>
</section></main>
