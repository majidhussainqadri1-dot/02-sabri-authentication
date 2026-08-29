<?php
/**
 * R342 permanent regression for the live-proven generic authentication-error
 * defect. Provider reason codes must become safe, specific and actionable
 * notices without duplicating File 00 identity truth or weakening enumeration
 * protections on credential and recovery paths.
 */

$root      = dirname( __DIR__ );
$messages  = file_get_contents( $root . '/includes/class-sauth-user-error-messages.php' );
$bootstrap = file_get_contents( $root . '/sabri-authentication.php' );
$security  = file_get_contents( $root . '/includes/class-sa-security.php' );
$register  = file_get_contents( $root . '/includes/class-sa-registration.php' );
$fail      = array();

$req = static function ( $ok, $message ) use ( &$fail ) {
	if ( ! $ok ) {
		$fail[] = $message;
	}
};

$req( false !== strpos( $bootstrap, "require_once SAUTH_DIR . 'includes/class-sauth-user-error-messages.php';" ), 'actionable error-message class is not loaded by bootstrap' );
$req( false !== strpos( $bootstrap, 'SAUTH_User_Error_Messages::init();' ), 'actionable error-message observer is not initialized' );
$req( false !== strpos( $messages, "add_action( 'sauth_event_recorded'" ), 'failure reasons are not captured from the canonical persisted event path' );
$req( false !== strpos( $messages, "add_filter( 'wp_redirect'" ), 'generic signed notices are not rewritten at redirect time' );
$req( false !== strpos( $messages, "'phone_collision'" ) && false !== strpos( $messages, 'This mobile number cannot be used for a new account.' ), 'phone collision does not have a specific actionable message' );
$req( false !== strpos( $messages, "'identity_collision'" ) && false !== strpos( $messages, 'This identity document cannot be used for a new account.' ), 'identity collision does not have a specific actionable message' );
$req( false !== strpos( $messages, "'email_collision'" ) && false !== strpos( $messages, 'This email address cannot be used for a new account.' ), 'email collision does not have a specific actionable message' );
$req( false !== strpos( $messages, "'credentials_invalid'" ) && false !== strpos( $messages, 'email or username and password combination is incorrect' ), 'credential failure is not clear while preserving field ambiguity' );
$req( false !== strpos( $messages, "'rate_limited'" ) && false !== strpos( $messages, 'Wait about 15 minutes' ), 'rate-limit failure does not state the next action' );
$req( false !== strpos( $messages, "'membership_not_eligible'" ) && false !== strpos( $messages, 'required account verification or status review' ), 'membership eligibility failure does not state the next action' );
$req( false !== strpos( $messages, "SA_Security::notice_query_args( 'error', \$message )" ), 'rewritten notice is not re-signed through the canonical notice signer' );
$req( false !== strpos( $messages, "remove_query_arg( array( 'sa_notice', 'sa_msg', 'sa_iat', 'sa_sig' )" ), 'old signed notice tuple is not removed before regeneration' );
$req( false !== strpos( $messages, 'is_generic_notice' ), 'rewrite is not constrained to known generic File 02 notices' );
$req( false !== strpos( $messages, 'If it is yours, sign in or use Account Recovery' ), 'collision guidance does not provide a safe recovery path' );
$req( false === strpos( $messages, 'smc_applications' ) && false === strpos( $messages, 'smc_identity_records' ), 'File 02 actionable messages must not re-query File 00 private tables' );
$req( false === strpos( $messages, 'SMC_Security::' ) && false === strpos( $messages, 'SMC_Authentication_Contract' ), 'File 02 actionable messages must not duplicate File 00 private implementation logic' );
$req( false !== strpos( $security, "notice_signature( \$type, \$message, \$issued_at )" ), 'canonical signed-notice integrity support is missing' );
$req( false !== strpos( $register, 'If the account exists and delivery is available, a reset email will be sent.' ), 'password recovery anti-enumeration response was weakened' );
$req( false !== strpos( $register, 'The sign-in details were not accepted. Check your credentials or complete account verification.' ), 'known generic sign-in source notice changed unexpectedly; R342 rewrite guard must be reviewed' );
$req( false !== strpos( $register, 'Registration could not be completed. The details may already belong to an account, or the membership service may require review.' ), 'known generic registration source notice changed unexpectedly; R342 rewrite guard must be reviewed' );

if ( $fail ) {
	fwrite( STDERR, "R342 actionable authentication error-message regressions:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}

echo 'R342 actionable authentication error messages PASS (20 assertions).' . PHP_EOL;
