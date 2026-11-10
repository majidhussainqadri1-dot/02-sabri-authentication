<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
	<section class="sa-auth-card sa-auth-wide" aria-labelledby="sa-signup-title">
		<div class="sa-brand-mark" aria-hidden="true">SH</div><span class="sa-kicker"><?php esc_html_e( 'Verified membership', 'sabri-authentication' ); ?></span>
		<h1 id="sa-signup-title"><?php esc_html_e( 'Create a Verified Account', 'sabri-authentication' ); ?></h1>
		<p class="sa-intro"><?php esc_html_e( 'Join the Sabri Social Homeopathy Platform. Your identity and account eligibility are verified before full access.', 'sabri-authentication' ); ?></p>
		<?php include SA_DIR . 'templates/partials/notice.php'; ?>

		<?php if ( $google_ready && empty( $google_context ) ) : ?>
			<p><a class="sa-google-button" href="<?php echo esc_url( $google_registration_url ); ?>"><span aria-hidden="true">G</span> <?php esc_html_e( 'Continue with Google', 'sabri-authentication' ); ?></a></p>
			<p class="sa-divider" role="separator"><span><?php esc_html_e( 'or register with email and password', 'sabri-authentication' ); ?></span></p>
		<?php elseif ( ! empty( $google_context ) ) : ?>
			<div class="sa-notice sa-notice-success" role="status">Google verified ownership of <strong><?php echo esc_html( $google_context['email'] ); ?></strong>. Complete every remaining mandatory identity, location, account-type, guardian, profile-photograph and consent field.</div>
		<?php endif; ?>

		<?php if ( $account_contract_ready ) : ?>
			<form class="sa-form sa-form-grid" method="post" action="<?php echo esc_url( $form_action ); ?>">
				<input type="hidden" name="action" value="sauth_register">
				<input type="hidden" name="google_registration_token" value="<?php echo esc_attr( $google_token ); ?>">
				<?php wp_nonce_field( 'sa_register', 'sa_nonce' ); ?>
				<div class="sa-honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
				<div>
					<label for="sa-name"><?php esc_html_e( 'Complete name', 'sabri-authentication' ); ?></label>
					<input id="sa-name" type="text" name="name" autocomplete="name" minlength="2" maxlength="100" value="<?php echo esc_attr( $google_context['name'] ?? '' ); ?>" required>
				</div>
				<div>
					<label for="sa-email"><?php esc_html_e( 'Email address', 'sabri-authentication' ); ?></label>
					<input id="sa-email" type="email" name="email" autocomplete="email" maxlength="320" value="<?php echo esc_attr( $google_context['email'] ?? '' ); ?>" <?php echo empty( $google_context ) ? '' : 'readonly aria-readonly="true"'; ?> required>
				</div>
				<div>
					<label for="sa-phone"><?php esc_html_e( 'Mobile/phone with country code', 'sabri-authentication' ); ?></label>
					<input id="sa-phone" type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="64" required>
					<small><?php esc_html_e( 'Ownership verification is completed through File 00 before the account becomes fully verified.', 'sabri-authentication' ); ?></small>
				</div>
				<div>
					<label for="sa-account-type"><?php esc_html_e( 'Declared account type', 'sabri-authentication' ); ?></label>
					<select id="sa-account-type" name="account_type" required>
						<option value=""><?php esc_html_e( 'Select', 'sabri-authentication' ); ?></option>
						<?php foreach ( $account_types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<small><?php esc_html_e( 'A declaration never grants doctor, teacher, staff or institutional privileges; canonical verification remains separate.', 'sabri-authentication' ); ?></small>
				</div>
				<div>
					<label for="sa-country"><?php esc_html_e( 'Country', 'sabri-authentication' ); ?></label>
					<input id="sa-country" type="text" name="country" autocomplete="country-name" maxlength="120" required>
				</div>
				<div>
					<label for="sa-city"><?php esc_html_e( 'City', 'sabri-authentication' ); ?></label>
					<input id="sa-city" type="text" name="city" autocomplete="address-level2" maxlength="120" required>
				</div>
				<div>
					<label for="sa-sex"><?php esc_html_e( 'Sex for the platform age rule', 'sabri-authentication' ); ?></label>
					<select id="sa-sex" name="sex" required>
						<option value=""><?php esc_html_e( 'Select', 'sabri-authentication' ); ?></option>
						<option value="male"><?php esc_html_e( 'Male', 'sabri-authentication' ); ?></option>
						<option value="female"><?php esc_html_e( 'Female', 'sabri-authentication' ); ?></option>
					</select>
				</div>
				<div>
					<label for="sa-birth-date"><?php esc_html_e( 'Date of birth', 'sabri-authentication' ); ?></label>
					<input id="sa-birth-date" type="date" name="date_of_birth" autocomplete="bday" required>
					<small><?php esc_html_e( 'Baseline minimum: male 15, female 12. Every legal minor requires verified guardian consent. Professional declarations require an adult account.', 'sabri-authentication' ); ?></small>
				</div>
				<div class="sa-field-full">
					<label for="sa-address"><?php esc_html_e( 'Full address', 'sabri-authentication' ); ?></label>
					<textarea id="sa-address" name="address" autocomplete="street-address" maxlength="1000" required></textarea>
				</div>
				<div>
					<label for="sa-identity-type"><?php esc_html_e( 'Identity document type', 'sabri-authentication' ); ?></label>
					<select id="sa-identity-type" name="identity_type" required>
						<option value=""><?php esc_html_e( 'Select', 'sabri-authentication' ); ?></option>
						<option value="national_id"><?php esc_html_e( 'National ID', 'sabri-authentication' ); ?></option>
						<option value="passport"><?php esc_html_e( 'Passport', 'sabri-authentication' ); ?></option>
					</select>
				</div>
				<div>
					<label for="sa-identity-reference"><?php esc_html_e( 'Document reference', 'sabri-authentication' ); ?></label>
					<input id="sa-identity-reference" type="text" name="identity_reference" autocomplete="off" spellcheck="false" minlength="5" maxlength="200" required>
					<small><?php esc_html_e( 'Passed directly to Membership Core; File 02 does not retain it as public profile data.', 'sabri-authentication' ); ?></small>
				</div>
				<div class="sa-field-full">
					<label for="sa-guardian-reference"><?php esc_html_e( 'Guardian reference, when under 18', 'sabri-authentication' ); ?></label>
					<input id="sa-guardian-reference" type="text" name="guardian_reference" autocomplete="off" maxlength="200">
					<small><?php esc_html_e( 'Required for every minor account; final guardian verification belongs to File 00.', 'sabri-authentication' ); ?></small>
				</div>

				<?php if ( empty( $google_context ) ) : ?>
					<div>
						<label for="sa-password"><?php esc_html_e( 'Password', 'sabri-authentication' ); ?></label>
						<div class="sa-password-wrap">
							<input id="sa-password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="4096" required>
							<button class="sa-show-password" type="button" data-sa-toggle-password="sa-password" aria-controls="sa-password"><?php esc_html_e( 'Show', 'sabri-authentication' ); ?></button>
						</div>
					</div>
					<div>
						<label for="sa-password-confirm"><?php esc_html_e( 'Confirm password', 'sabri-authentication' ); ?></label>
						<div class="sa-password-wrap">
							<input id="sa-password-confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="4096" required>
							<button class="sa-show-password" type="button" data-sa-toggle-password="sa-password-confirm" aria-controls="sa-password-confirm"><?php esc_html_e( 'Show', 'sabri-authentication' ); ?></button>
						</div>
					</div>
				<?php else : ?>
					<div class="sa-field-full sa-notice" role="note"><?php esc_html_e( 'This account will initially use Google sign-in. A local password may later be established through the protected password-recovery workflow.', 'sabri-authentication' ); ?></div>
				<?php endif; ?>

				<div class="sa-field-full sa-consents">
					<label class="sa-check"><input type="checkbox" name="profile_photo_required" value="1" required> <?php esc_html_e( 'I understand that a profile photograph is mandatory and must be completed through the canonical File 03 profile workflow before full account completion.', 'sabri-authentication' ); ?></label>
					<label class="sa-check"><input type="checkbox" name="accept_terms" value="1" required> <?php esc_html_e( 'I accept the current Terms of Use.', 'sabri-authentication' ); ?></label>
					<label class="sa-check"><input type="checkbox" name="accept_privacy" value="1" required> <?php esc_html_e( 'I have read and accept the Privacy Notice and identity-verification processing.', 'sabri-authentication' ); ?></label>
					<label class="sa-check"><input type="checkbox" name="accept_ethics" value="1" required> <?php esc_html_e( 'I accept the Islamic, professional and institutional Ethical Conduct Charter, including truthful identity and role declarations.', 'sabri-authentication' ); ?></label>
				</div>
				<div class="sa-field-full">
					<button class="sa-primary-button" type="submit"><?php esc_html_e( 'Create Account and Continue Verification', 'sabri-authentication' ); ?></button>
				</div>
			</form>
		<?php else : ?>
			<div class="sa-notice sa-notice-error" role="status"><?php esc_html_e( 'Registration is temporarily unavailable because the required File 00 account-orchestration contract is not ready. No partial account, fallback role or duplicate identity will be created.', 'sabri-authentication' ); ?></div>
		<?php endif; ?>
		<a class="sa-text-link" href="<?php echo esc_url( $login_url ); ?>">Already registered? Sign in securely</a>
	</section>
</main>
