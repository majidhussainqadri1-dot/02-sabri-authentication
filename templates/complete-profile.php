<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
	<section class="sa-auth-card" aria-labelledby="sa-profile-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker"><?php esc_html_e( "Membership Core", 'sabri-authentication' ); ?></span>
		<h1 id="sa-profile-title"><?php esc_html_e( "Verified Profile and Identity", 'sabri-authentication' ); ?></h1>
		<p class="sa-intro"><?php esc_html_e( "File 02 no longer edits account type, WordPress role, verification status, identity evidence, or membership profile fields.", 'sabri-authentication' ); ?></p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<a class="sa-primary-button" href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( "Open Membership Profile", 'sabri-authentication' ); ?></a>
		<a class="sa-secondary-button" href="<?php echo esc_url( $verification_url ); ?>"><?php esc_html_e( "View Verification Status", 'sabri-authentication' ); ?></a>
	</section>
</main>
