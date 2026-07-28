<?php defined( 'ABSPATH' ) || exit; $pages = (array) get_option( 'sa_page_map', array() ); $privacy_url = get_privacy_policy_url(); $terms_page = get_page_by_path( 'terms-of-service', OBJECT, 'page' ); ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card sa-auth-wide" aria-labelledby="sa-signup-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Join the global homeopathy community</span>
		<h1 id="sa-signup-title">Create Your Account</h1><p class="sa-intro">Public reading remains free. An account is required for participation and personal services.</p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<?php if ( ! $registration_open ) : ?><div class="sa-notice sa-notice-error">New registration is temporarily closed.</div><?php else : ?>
		<?php $redirect_to = home_url( '/' ); include SA_DIR . 'templates/partials/google-button.php'; ?>
		<?php if ( $google_ready ) : ?><div class="sa-divider"><span>or register with email</span></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-form sa-form-grid">
			<input type="hidden" name="action" value="sa_register"><?php wp_nonce_field( 'sa_register', 'sa_nonce' ); ?><input class="sa-honeypot" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
			<div class="sa-field-full"><label for="sa-name">Full Name</label><input id="sa-name" name="full_name" type="text" autocomplete="name" required></div>
			<div><label for="sa-email">Email Address</label><input id="sa-email" name="email" type="email" autocomplete="email" required></div>
			<div><label for="sa-phone">Phone Number</label><input id="sa-phone" name="phone" type="tel" autocomplete="tel" required></div>
			<div><label for="sa-country">Country</label><input id="sa-country" name="country" type="text" autocomplete="country-name" required></div>
			<div><label for="sa-city">City</label><input id="sa-city" name="city" type="text" autocomplete="address-level2" required></div>
			<div class="sa-field-full"><label for="sa-type">Account Type</label><select id="sa-type" name="account_type"><option value="member">General Member</option><option value="patient">Patient</option><option value="student">Student</option><option value="doctor">Doctor — Verification Pending</option></select><small>Choosing Doctor does not grant verification, posting or clinic permission.</small></div>
			<div><label for="sa-password">Password</label><div class="sa-password-wrap"><input id="sa-password" name="password" type="password" minlength="10" autocomplete="new-password" required><button type="button" class="sa-show-password" aria-controls="sa-password">Show</button></div></div>
			<div><label for="sa-confirm">Confirm Password</label><input id="sa-confirm" name="confirm_password" type="password" minlength="10" autocomplete="new-password" required></div>
			<div class="sa-field-full sa-consents"><label class="sa-check"><input type="checkbox" name="terms" value="1" required> I accept the <?php if ( $terms_page ) : ?><a href="<?php echo esc_url( get_permalink( $terms_page ) ); ?>" target="_blank" rel="noopener">Terms of Service</a><?php else : ?>Terms of Service<?php endif; ?>.</label><label class="sa-check"><input type="checkbox" name="privacy" value="1" required> I consent to the <?php if ( $privacy_url ) : ?><a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">Privacy Policy</a><?php else : ?>Privacy Policy<?php endif; ?> and account data processing.</label></div>
			<div class="sa-field-full"><button class="sa-primary-button" type="submit">Create Account</button></div>
		</form><?php endif; ?>
		<p class="sa-bottom-text">Already registered? <a href="<?php echo ! empty( $pages['login'] ) ? esc_url( get_permalink( absint( $pages['login'] ) ) ) : esc_url( wp_login_url() ); ?>">Log in</a></p>
	</section>
</main>
