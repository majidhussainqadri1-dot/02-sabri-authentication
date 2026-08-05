<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell">
	<section class="sa-auth-card sa-auth-wide" aria-labelledby="sa-signup-title">
		<div class="sa-brand-mark">SH</div><span class="sa-kicker">Verified membership</span>
		<h1 id="sa-signup-title">Create a Verified Account</h1>
		<p class="sa-intro">File 02 validates and orchestrates registration. File 00 remains the sole owner of identity, age and guardian eligibility, roles, verification, membership status and evidence review.</p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>
		<?php if ( $account_contract_ready ) : ?>
			<form class="sa-form sa-form-grid" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<input type="hidden" name="action" value="sa_register">
				<?php wp_nonce_field( 'sa_register', 'sa_nonce' ); ?>
				<div class="sa-honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
				<div>
					<label for="sa-name">Complete name</label>
					<input id="sa-name" type="text" name="name" autocomplete="name" minlength="2" maxlength="100" required>
				</div>
				<div>
					<label for="sa-email">Email address</label>
					<input id="sa-email" type="email" name="email" autocomplete="email" required>
				</div>
				<div>
					<label for="sa-phone">Phone with country code</label>
					<input id="sa-phone" type="tel" name="phone" autocomplete="tel" inputmode="tel" required>
				</div>
				<div>
					<label for="sa-country">Country</label>
					<input id="sa-country" type="text" name="country" autocomplete="country-name" required>
				</div>
				<div>
					<label for="sa-sex">Sex for the platform age rule</label>
					<select id="sa-sex" name="sex" required>
						<option value="">Select</option>
						<option value="male">Male</option>
						<option value="female">Female</option>
					</select>
				</div>
				<div>
					<label for="sa-birth-date">Date of birth</label>
					<input id="sa-birth-date" type="date" name="date_of_birth" autocomplete="bday" required>
					<small>Baseline minimum: male 15, female 12. Every legal minor requires verified guardian consent.</small>
				</div>
				<div class="sa-field-full">
					<label for="sa-address">Full address</label>
					<textarea id="sa-address" name="address" autocomplete="street-address" required></textarea>
				</div>
				<div>
					<label for="sa-identity-reference">National ID or Passport reference</label>
					<input id="sa-identity-reference" type="text" name="identity_reference" autocomplete="off" spellcheck="false" required>
					<small>Passed directly to Membership Core; File 02 does not retain it in its own profile data.</small>
				</div>
				<div>
					<label for="sa-guardian-reference">Guardian reference, when under 18</label>
					<input id="sa-guardian-reference" type="text" name="guardian_reference" autocomplete="off">
					<small>Required for every minor account; final guardian verification belongs to File 00.</small>
				</div>
				<div>
					<label for="sa-password">Password</label>
					<div class="sa-password-wrap">
						<input id="sa-password" type="password" name="password" autocomplete="new-password" minlength="12" required>
						<button class="sa-show-password" type="button" data-sa-toggle-password="sa-password" aria-controls="sa-password">Show</button>
					</div>
				</div>
				<div>
					<label for="sa-password-confirm">Confirm password</label>
					<div class="sa-password-wrap">
						<input id="sa-password-confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="12" required>
						<button class="sa-show-password" type="button" data-sa-toggle-password="sa-password-confirm" aria-controls="sa-password-confirm">Show</button>
					</div>
				</div>
				<div class="sa-field-full sa-consents">
					<label class="sa-check"><input type="checkbox" name="accept_terms" value="1" required> I accept the current Terms of Use.</label>
					<label class="sa-check"><input type="checkbox" name="accept_privacy" value="1" required> I have read and accept the Privacy Notice and identity-verification processing.</label>
				</div>
				<div class="sa-field-full">
					<button class="sa-primary-button" type="submit">Create Account and Send Verification Email</button>
				</div>
			</form>
		<?php else : ?>
			<div class="sa-notice sa-notice-error" role="status">Registration is temporarily unavailable because the required File 00 account-orchestration contract is not ready. No partial account, fallback role or duplicate identity will be created.</div>
		<?php endif; ?>
		<a class="sa-text-link" href="<?php echo esc_url( $login_url ); ?>">Already registered? Sign in securely</a>
	</section>
</main>
