<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card" aria-labelledby="sa-signup-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Verified membership</span>
		<h1 id="sa-signup-title">Create a Verified Account</h1>
		<p class="sa-intro">Registration, age checks, identity evidence, role request, email verification, two-factor setup, and institutional review are handled exclusively by Membership Core.</p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<a class="sa-primary-button" href="<?php echo esc_url( $register_url ); ?>">Open Membership Registration</a>
		<a class="sa-text-link" href="<?php echo esc_url( $login_url ); ?>">Already registered? Sign in securely</a>
	</section>
</main>
