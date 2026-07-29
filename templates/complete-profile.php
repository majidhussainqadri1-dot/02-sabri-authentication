<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-profile-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Membership Core</span>
		<h1 id="sa-profile-title">Verified Profile and Identity</h1>
		<p class="sa-intro">File 02 no longer edits account type, WordPress role, verification status, identity evidence, or membership profile fields.</p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<a class="sa-primary-button" href="<?php echo esc_url( $profile_url ); ?>">Open Membership Profile</a>
		<a class="sa-secondary-button" href="<?php echo esc_url( $verification_url ); ?>">View Verification Status</a>
	</section>
</main>
