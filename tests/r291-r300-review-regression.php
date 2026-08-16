<?php
/**
 * Static cumulative regression gate for the R291-R300 review line.
 * Runs without WordPress/network access and prevents the hardened boundaries
 * added during the review from silently disappearing.
 */

$root = dirname( __DIR__ );
$failures = array();
$assertions = 0;

function r291_r300_read( $relative ) {
	global $root, $failures;
	$path = $root . '/' . $relative;
	if ( ! is_file( $path ) ) {
		$failures[] = 'Missing required file: ' . $relative;
		return '';
	}
	$data = file_get_contents( $path );
	return false === $data ? '' : $data;
}

function r291_r300_contains( $haystack, $needle, $label ) {
	global $failures, $assertions;
	$assertions++;
	if ( false === strpos( $haystack, $needle ) ) {
		$failures[] = $label;
	}
}

function r291_r300_before( $haystack, $first, $second, $label ) {
	global $failures, $assertions;
	$assertions++;
	$a = strpos( $haystack, $first );
	$b = strpos( $haystack, $second );
	if ( false === $a || false === $b || $a >= $b ) {
		$failures[] = $label;
	}
}

$main       = r291_r300_read( 'sabri-authentication.php' );
$privacy    = r291_r300_read( 'includes/class-sauth-privacy-jobs.php' );
$passkey    = r291_r300_read( 'includes/class-sauth-passkey-runtime.php' );
$safe_mode  = r291_r300_read( 'includes/class-sauth-safe-mode-challenge-gate.php' );
$operations = r291_r300_read( 'includes/class-sauth-operations.php' );
$provider   = r291_r300_read( 'includes/class-sauth-provider-health.php' );
$outbox     = r291_r300_read( 'includes/class-sauth-event-outbox.php' );
$session    = r291_r300_read( 'includes/class-sauth-session-manager.php' );
$register   = r291_r300_read( 'includes/class-sa-registration.php' );

r291_r300_contains( $main, "require_once SAUTH_DIR . 'includes/class-sauth-privacy-jobs.php';", 'Privacy job barrier is not loaded.' );
r291_r300_contains( $main, "require_once SAUTH_DIR . 'includes/class-sauth-safe-mode-challenge-gate.php';", 'Safe Mode challenge gate is not loaded.' );
r291_r300_contains( $main, "require_once SAUTH_DIR . 'includes/class-sauth-passkey-runtime.php';", 'Hardened passkey runtime is not loaded.' );
r291_r300_before( $main, 'includes/class-sauth-privacy-jobs.php', 'includes/class-sa-registration.php', 'Privacy job barrier must load before registration/recovery handlers.' );
r291_r300_before( $main, 'includes/class-sauth-passkeys.php', 'includes/class-sauth-passkey-runtime.php', 'Passkey parser must load before hardened runtime controller.' );
r291_r300_before( $main, 'SAUTH_Passkeys::init();', 'SAUTH_Passkey_Runtime::init();', 'Historical passkey parser/runtime must initialize before hardened endpoint replacement.' );
r291_r300_contains( $main, "register_activation_hook( SAUTH_FILE, 'sauth_validate_activation_dependencies' );", 'Dependency readiness gate must execute before File 02 activation mutations.' );
r291_r300_contains( $main, 'SAUTH_Session_Manager::revoke_user_sessions( absint( $user_id ), \'passkey_assurance_epoch_rotated\' )', 'Passkey assurance epoch rotation must use the verified all-session revocation boundary.' );
r291_r300_contains( $main, 'SAUTH_Operations::enter_safe_mode();', 'Failed passkey assurance epoch revocation must enter Safe Mode.' );

r291_r300_contains( $privacy, "const ACTIVE_META = '_sauth_privacy_erasure_active';", 'Privacy erasure barrier marker missing.' );
r291_r300_contains( $privacy, 'public static function valid_snapshot', 'Queued authentication jobs are not bound to a privacy epoch snapshot.' );
r291_r300_contains( $privacy, 'public static function begin_erasure', 'Privacy erasure does not raise the asynchronous job barrier.' );
r291_r300_contains( $privacy, 'return self::purge_jobs( $user_id );', 'Privacy erasure does not purge indexed queued jobs.' );
r291_r300_contains( $register, 'SAUTH_Privacy_Jobs::register_job( $job_user_id, $job_key )', 'Password-recovery transient is not indexed for privacy erasure.' );
r291_r300_contains( $register, 'SAUTH_Privacy_Jobs::forget_job( $user_id, $key )', 'Password-recovery worker does not remove its privacy job index entry.' );

r291_r300_contains( $passkey, 'const MAX_ATTESTATION_B64', 'Passkey attestation input is not explicitly bounded.' );
r291_r300_contains( $passkey, 'consume_challenge', 'Hardened passkey runtime lacks one-time challenge consumption.' );
r291_r300_contains( $passkey, 'credential_store_postcondition_failed', 'Passkey credential persistence postcondition guard missing.' );
r291_r300_contains( $passkey, 'public static function invalidate_user_assurance', 'Passkey assurance invalidation boundary missing.' );
r291_r300_contains( $passkey, "const EPOCH_META           = '_sauth_passkey_assurance_epoch_v1';", 'Passkey assurance epoch marker missing.' );

r291_r300_contains( $safe_mode, "array( 'sauth_passkey_finish_registration', 'sauth_passkey_finish_authentication' )", 'Safe Mode gate does not cover both passkey finish ceremonies.' );
r291_r300_contains( $safe_mode, '$created <= $entered_at', 'Safe Mode gate does not reject pre-containment challenges.' );
r291_r300_contains( $safe_mode, 'delete_transient( $key );', 'Safe Mode gate does not consume the invalidated challenge.' );

r291_r300_contains( $operations, 'safe_mode_entered_at', 'Operations layer does not retain Safe Mode entry evidence.' );
r291_r300_contains( $provider, 'half_open', 'Provider circuit does not expose bounded half-open state handling.' );
r291_r300_contains( $outbox, 'lease', 'Authentication outbox does not retain lease/concurrency handling.' );
r291_r300_contains( $session, 'destroy_all', 'Session containment does not expose all-session invalidation.' );

if ( ! empty( $failures ) ) {
	fwrite( STDERR, "R291-R300 regression failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, 'R291-R300 cumulative static regression PASS (' . $assertions . " assertions).\n" );
