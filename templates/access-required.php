<?php
defined( 'ABSPATH' ) || exit;
$redirect_to = wp_get_referer() ? wp_get_referer() : home_url( '/' );
$login_url   = SA_Membership_Adapter::login_url( $redirect_to );
$signup_url  = SA_Membership_Adapter::register_url();
?>
<main class="sa-auth-shell" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"><section class="sa-auth-card sa-access-card" aria-labelledby="sa-access-title">
	<div class="sa-brand-mark">SH</div>
	<h1 id="sa-access-title"><?php esc_html_e( "Verified Account Required", 'sabri-authentication' ); ?></h1>
	<p><?php esc_html_e( "Public content remains open. Sabri Authentication sign-in is required for comments, saving, following, messaging, publishing, and personal services; Membership Core remains the membership and eligibility authority.", 'sabri-authentication' ); ?></p>
	<?php include SA_DIR . 'templates/partials/notice.php'; ?>
	<a class="sa-primary-button" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( "Secure Log In", 'sabri-authentication' ); ?></a>
	<a class="sa-secondary-button" href="<?php echo esc_url( $signup_url ); ?>"><?php esc_html_e( "Create Verified Account", 'sabri-authentication' ); ?></a>
	<a class="sa-text-link" href="<?php echo esc_url( $redirect_to ); ?>"><?php esc_html_e( "Not Now", 'sabri-authentication' ); ?></a>
</section></main>
