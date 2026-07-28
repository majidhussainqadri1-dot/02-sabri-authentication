<?php defined( 'ABSPATH' ) || exit; ?>
<main class="sa-auth-shell"><section class="sa-auth-card sa-auth-wide" aria-labelledby="sa-profile-title">
	<div class="sa-brand-mark">SH</div><span class="sa-kicker">Account setup</span><h1 id="sa-profile-title">Complete Your Profile</h1><p class="sa-intro">Private contact details are not displayed publicly by this module.</p>
	<?php include SA_DIR . 'templates/partials/notice.php'; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-form sa-form-grid">
		<input type="hidden" name="action" value="sa_complete_profile"><?php wp_nonce_field( 'sa_complete_profile', 'sa_nonce' ); ?>
		<div class="sa-field-full"><label for="sa-name">Full Name</label><input id="sa-name" name="full_name" type="text" value="<?php echo esc_attr( $user->display_name ); ?>" required></div>
		<div><label for="sa-phone">Phone Number</label><input id="sa-phone" name="phone" type="tel" value="<?php echo esc_attr( get_user_meta( $user->ID, '_sa_phone', true ) ); ?>" required></div>
		<div><label for="sa-language">Preferred Language</label><input id="sa-language" name="preferred_language" type="text" value="<?php echo esc_attr( get_user_meta( $user->ID, '_sa_preferred_language', true ) ?: 'English (US)' ); ?>"></div>
		<div><label for="sa-country">Country</label><input id="sa-country" name="country" type="text" value="<?php echo esc_attr( get_user_meta( $user->ID, '_sa_country', true ) ); ?>" required></div>
		<div><label for="sa-city">City</label><input id="sa-city" name="city" type="text" value="<?php echo esc_attr( get_user_meta( $user->ID, '_sa_city', true ) ); ?>" required></div>
		<div class="sa-field-full"><label for="sa-type">Account Type</label><select id="sa-type" name="account_type"><?php $current_type = get_user_meta( $user->ID, '_sa_account_type', true ) ?: 'member'; foreach ( array( 'member'=>'General Member','patient'=>'Patient','student'=>'Student','doctor'=>'Doctor — Verification Pending' ) as $value=>$label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_type, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
		<div class="sa-field-full"><label for="sa-bio">Short Bio</label><textarea id="sa-bio" name="bio" rows="4"><?php echo esc_textarea( $user->description ); ?></textarea></div>
		<div class="sa-field-full"><label class="sa-check"><input type="checkbox" name="privacy" value="1" required> I confirm the Privacy Policy and consent to saving these account details.</label></div>
		<div class="sa-field-full"><button class="sa-primary-button" type="submit">Save Profile</button></div>
	</form>
</section></main>

