<?php
/** Static no-network regression for R299 cross-flow corrections. */
$root = dirname( __DIR__ );
$fail = array();
$n = 0;
function r299_read( $path ) { global $root; $data = file_get_contents( $root . '/' . $path ); return false === $data ? '' : $data; }
function r299_has( $text, $needle, $label ) { global $fail, $n; ++$n; if ( false === strpos( $text, $needle ) ) { $fail[] = $label; } }
function r299_not( $text, $needle, $label ) { global $fail, $n; ++$n; if ( false !== strpos( $text, $needle ) ) { $fail[] = $label; } }
$passkeys = r299_read( 'includes/class-sauth-passkeys.php' );
$js = r299_read( 'assets/js/authentication.js' );
$email = r299_read( 'includes/class-sauth-email-verification.php' );
$registration = r299_read( 'includes/class-sa-registration.php' );
$google = r299_read( 'includes/class-sa-google-oauth.php' );
$google_reg = r299_read( 'includes/class-sauth-google-registration.php' );
$ops = r299_read( 'includes/class-sauth-operations.php' );
$completion = r299_read( 'includes/class-sauth-completion-resolver.php' );
$plugin = r299_read( 'includes/class-sa-plugin.php' );
$plan = r299_read( 'PLAN-TRACEABILITY.md' );
$status = r299_read( 'STATUS.md' );
r299_has( $passkeys, 'public static function maybe_install( $force = false )', 'passkey repair is not force-aware' );
r299_has( $passkeys, 'SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available()', 'passkey schema mutation is not dependency-gated' );
r299_has( $passkeys, 'SHOW TABLES LIKE %s', 'passkey schema marker lacks table postcondition' );
r299_not( $passkeys, 'id="sauth-passkey-stepup"', 'passkey manager still solicits retired factor code' );
r299_not( $js, 'step_up_code', 'browser still transmits retired factor code' );
r299_has( $email, 'SAUTH_Privacy_Jobs::register_job( $job_user_id, $job_key )', 'email resend job is not privacy-indexed' );
r299_has( $email, 'SAUTH_Privacy_Jobs::forget_job( $user_id, $key )', 'email resend worker does not clear privacy index' );
r299_has( $registration, 'Password reset is temporarily unavailable. No credential was changed.', 'password reset is not dependency/Safe-Mode fail-closed' );
r299_has( $google, 'SAUTH_Login_Risk::evaluate( $locked_user->ID, $completion )', 'Google login is absent from locked risk evaluation' );
r299_has( $google, 'SAUTH_Login_Risk::record_successful_login( $user->ID, \'google\', absint( $risk[\'score\'] ?? 0 ) )', 'Google login is absent from risk/session trust projection' );
r299_has( $google, 'AccountAuthenticationSucceeded.v1', 'Google login success event is missing' );
r299_has( $google, 'GoogleAccountLinked.v1', 'Google link event is missing' );
r299_has( $google, 'GoogleAccountUnlinked.v1', 'Google unlink event is missing' );
r299_has( $google, 'completion_state( $locked_user->ID, array( \'purpose\' => \'google_sign_in\' ) )', 'Google login does not enforce completion state under its lock' );
r299_has( $google, 'google_account_unlink_incomplete', 'Google unlink lacks postcondition containment' );
r299_not( $google_reg, "SAUTH_Provider_Health::record_failure( 'google'", 'Google registration double-counts HTTP circuit failures' );
r299_not( $google_reg, "SAUTH_Provider_Health::record_success( 'google'", 'Google registration double-counts HTTP circuit success' );
r299_has( $google_reg, 'if ( ! $scheduled ) { delete_option( $claim_key ); }', 'Google registration replay claim can leak if cron scheduling fails' );
foreach ( array( 'sa_forgot_password', 'sauth_forgot_password', 'sa_reset_password', 'sauth_reset_password', 'sauth_verify_email', 'sauth_resend_email_verification', 'sa_google_unlink' ) as $action ) { r299_has( $ops, "'" . $action . "'", 'Safe Mode misses ' . $action ); }
foreach ( array( 'profile_photo', 'identity_reference', 'address', 'city', 'country', 'account_type', 'ethical_conduct' ) as $step ) { r299_has( $completion, "'" . $step . "'", 'completion helper misses ' . $step ); }
r299_has( $plugin, '$snapshot = array();', 'Google settings lack transactional snapshot' );
r299_has( $plugin, 'settings_store_failed', 'Google settings lack rollback failure path' );
r299_has( $plan, 'agent/file02-comprehensive-remediation-1.3.0', 'traceability candidate branch is stale' );
r299_has( $status, 'agent/file02-comprehensive-remediation-1.3.0', 'status candidate branch is stale' );
if ( $fail ) { fwrite( STDERR, "R299 regression failures:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R299 cross-flow regression PASS (' . $n . " assertions).\n";
