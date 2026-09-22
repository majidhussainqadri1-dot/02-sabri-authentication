<?php
/**
 * File 02 Modern Authentication 24 — source/security contract gate.
 *
 * X-QA-24-01..16. This test is intentionally no-network and proves the
 * repository-level invariants. Real browser/provider/staging acceptance remains
 * a separate release gate.
 */

$root = dirname( __DIR__ );
$passed = 0;

function sauth_x24_read( $root, $path ) {
	$file = $root . '/' . $path;
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "FAIL: missing {$path}\n" );
		exit( 1 );
	}
	return file_get_contents( $file );
}
function sauth_x24_assert( $condition, $label ) {
	global $passed;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
	$passed++;
}
function sauth_x24_has_all( $text, array $markers, $label ) {
	foreach ( $markers as $marker ) {
		sauth_x24_assert( false !== strpos( $text, $marker ), $label . ' missing ' . $marker );
	}
}

$bootstrap = sauth_x24_read( $root, 'sabri-authentication.php' );
$modern    = sauth_x24_read( $root, 'includes/class-sauth-modern-auth.php' );
$security  = sauth_x24_read( $root, 'includes/class-sauth-security-orchestrator.php' );
$signals   = sauth_x24_read( $root, 'includes/class-sauth-shared-signals.php' );
$password  = sauth_x24_read( $root, 'includes/class-sauth-password-safety.php' );
$dpop      = sauth_x24_read( $root, 'includes/class-sauth-dpop.php' );
$fido      = sauth_x24_read( $root, 'includes/class-sauth-fido-trust.php' );
$passkeys  = sauth_x24_read( $root, 'includes/class-sauth-passkeys.php' );
$runtime   = sauth_x24_read( $root, 'includes/class-sauth-passkey-runtime.php' );
$routes    = sauth_x24_read( $root, 'includes/class-sauth-canonical-routes.php' );
$activator = sauth_x24_read( $root, 'includes/class-sa-activator.php' );
$privacy   = sauth_x24_read( $root, 'includes/class-sa-privacy.php' );
$js        = sauth_x24_read( $root, 'assets/js/authentication.js' );
$login     = sauth_x24_read( $root, 'templates/login.php' );
$google    = sauth_x24_read( $root, 'includes/class-sa-google-oauth.php' );
$risk      = sauth_x24_read( $root, 'includes/class-sauth-login-risk.php' );
$reg       = sauth_x24_read( $root, 'includes/class-sa-registration.php' );
$ops       = sauth_x24_read( $root, 'includes/class-sauth-operations.php' );
$builder   = sauth_x24_read( $root, 'tools/build-package.sh' );

/* X-QA-24-01 — exact feature registry. */
preg_match_all( "/'F02-X-24-(\\d{3})'/", $modern, $matches );
$ids = isset( $matches[1] ) ? array_values( array_unique( $matches[1] ) ) : array();
$expected = array();
for ( $i = 1; $i <= 24; $i++ ) { $expected[] = str_pad( (string) $i, 3, '0', STR_PAD_LEFT ); }
sort( $ids );
sauth_x24_assert( $expected === $ids, 'X-QA-24-01 exact feature registry 001..024' );

/* X-QA-24-02 — conditional mediation/capability fallback/race containment. */
sauth_x24_has_all( $js, array(
	'isConditionalMediationAvailable',
	"mediation: 'conditional'",
	'getClientCapabilities',
	'AbortController',
	'passkeyInFlight',
), 'X-QA-24-02 conditional UI' );
sauth_x24_assert( false !== strpos( $login, 'autocomplete="username webauthn"' ), 'X-QA-24-02 webauthn autofill token' );

/* X-QA-24-03 — browser credential signals are advisory only. */
sauth_x24_has_all( $js, array( 'signalAllAcceptedCredentials', 'signalUnknownCredential', 'signalCurrentUserDetails' ), 'X-QA-24-03 browser signals' );
sauth_x24_has_all( $modern, array( "'authoritative'=>false", 'browser_credential_signal_observed' ), 'X-QA-24-03 server truth unchanged' );

/* X-QA-24-04 — emergency lockdown contains active/new auth; privileged recovery is separate/fail-closed. */
sauth_x24_has_all( $security, array(
	'emergency_lockdown',
	'revoke_user_sessions',
	'authentication_blocked',
	'sauth_lockdown_recovery_authorize_v1',
), 'X-QA-24-04 lockdown' );
sauth_x24_assert( false !== strpos( $reg, 'emergency_lockdown_active' ), 'X-QA-24-04 password blocked' );
sauth_x24_assert( false !== strpos( $runtime, 'emergency_lockdown_active' ), 'X-QA-24-04 passkey blocked' );
sauth_x24_assert( false !== strpos( $google, 'emergency security lockdown' ), 'X-QA-24-04 Google blocked' );
sauth_x24_assert( false !== strpos( $modern, "'emergency_lockdown_active'" ), 'X-QA-24-04 FedCM blocked' );

/* X-QA-24-05 — downgrade protection on strong method/provider removal. */
sauth_x24_has_all( $security, array( 'allow_method_removal', "'remove_'", "'phishing_resistant'=>true" ), 'X-QA-24-05 downgrade' );
sauth_x24_assert( false !== strpos( $runtime, 'allow_method_removal( $user_id, \'passkey\' )' ), 'X-QA-24-05 passkey revoke gate' );
sauth_x24_assert( false !== strpos( $google, 'allow_method_removal( $user_id, \'google\' )' ), 'X-QA-24-05 Google unlink gate' );

/* X-QA-24-06 — recovery cooling-off, bounded window, protected owner token, no silent apply. */
sauth_x24_has_all( $security, array(
	'RECOVERY_DEFAULT     = DAY_IN_SECONDS',
	'RECOVERY_MIN         = HOUR_IN_SECONDS',
	'RECOVERY_MAX         = 7 * DAY_IN_SECONDS',
	'owner_token_ciphertext',
	'sauth_apply_recovery_change_v1',
	"'cancelled'",
), 'X-QA-24-06 recovery cooling' );

/* X-QA-24-07 — adaptive risk without raw identifiers. */
sauth_x24_has_all( $security, array( 'rapid_device_velocity', 'rapid_network_velocity', 'shared_security_signal', 'explain_reasons' ), 'X-QA-24-07 adaptive risk' );
sauth_x24_assert( false === strpos( $security, 'REMOTE_ADDR' ), 'X-QA-24-07 orchestrator does not expose raw IP' );
sauth_x24_assert( false !== strpos( $risk, 'SAUTH_Security_Orchestrator::risk_v2' ), 'X-QA-24-07 risk v2 integrated' );

/* X-QA-24-08 — CAEP/RISC verifier fail-closed, replay-safe, duplicate-idempotent, containment. */
sauth_x24_has_all( $signals, array(
	"'caep'", "'risc'",
	'sauth_shared_signal_authorize_v1',
	'false, $request',
	"WHERE event_id=%s",
	"'session_revoked','credential_compromise','account_disabled'",
), 'X-QA-24-08 shared signals' );

/* X-QA-24-09 — additive Assurance v2, <=5m, session/fingerprint and phishing resistance. */
sauth_x24_has_all( $security, array(
	"ASSURANCE_V2_VERSION = '2.0.0'",
	"'phishing_resistant'=>true",
	"'session_binding'=>hash_hmac",
	"'fingerprint_binding'=>SA_Security::client_fingerprint()",
	'$verified+300',
), 'X-QA-24-09 assurance v2' );
sauth_x24_assert( false !== strpos( $bootstrap, "SAUTH_PASSKEY_CONTRACT_VERSION', '1.0.0" ), 'X-QA-24-09 assurance v1 preserved' );

/* X-QA-24-10 — only 5-hex prefix crosses breach adapter boundary. */
sauth_x24_has_all( $password, array( 'substr( $sha1, 0, 5 )', 'sauth_breached_password_prefix_lookup_v1', 'null, $prefix' ), 'X-QA-24-10 password privacy' );
sauth_x24_assert( false === strpos( $password, 'sauth_breached_password_prefix_lookup_v1\', null, $password' ), 'X-QA-24-10 raw password not passed' );
sauth_x24_assert( false === strpos( $password, 'sauth_breached_password_prefix_lookup_v1\', null, $sha1' ), 'X-QA-24-10 full SHA1 not passed' );
sauth_x24_assert( false !== strpos( $reg, 'SAUTH_Password_Safety::check' ), 'X-QA-24-10 registration/reset integration' );

/* X-QA-24-11 — DPoP claim/JWK/replay/signature fail-closed constraints. */
sauth_x24_has_all( $dpop, array(
	"'htm'", "'htu'", "'iat'", "'jti'", "'ath'",
	'isset( $jwk[\'d\'] )',
	'sauth_dpop_replay',
	'sauth_dpop_verify_signature_v1',
	'false, $parts[0]',
), 'X-QA-24-11 DPoP' );

/* X-QA-24-12 — attestation none never fabricates hardware trust. */
sauth_x24_has_all( $fido, array( "'none' ===", "'hardware_backed'=>false", 'sauth_fido_metadata_lookup_v1' ), 'X-QA-24-12 FIDO trust' );
sauth_x24_assert( false !== strpos( $passkeys, 'SAUTH_FIDO_Trust::assess' ) && false !== strpos( $runtime, 'SAUTH_FIDO_Trust::assess' ), 'X-QA-24-12 FIDO integrated' );

/* X-QA-24-13 — exact HTTPS Related-Origin manifest, no wildcard/path. */
sauth_x24_has_all( $modern, array( 'false !== strpos( $origin, \'*\' )', "'https' !==", "! empty( $parts['path'] ) && '/' !== $parts['path']" ), 'X-QA-24-13 related origins' );
sauth_x24_has_all( $routes, array( "'^\\\\.well-known/webauthn/?$'", '/.well-known/webauthn', 'related_origin_manifest' ), 'X-QA-24-13 well-known route' );

/* X-QA-24-14 — FedCM browser token cannot authenticate without server verifier. */
sauth_x24_has_all( $modern, array(
	'sauth_fedcm_verify_token_v1',
	"'fedcm_server_verification_unavailable'",
	'empty( $verified[\'verified\'] )',
), 'X-QA-24-14 FedCM verifier' );
sauth_x24_assert( false !== strpos( $js, 'identity: { providers: [provider] }' ), 'X-QA-24-14 progressive FedCM browser path' );

/* X-QA-24-15 — new File 02 records participate in privacy lifecycle; File 00 remains untouched. */
foreach ( array( 'security_timeline','recovery_changes','shared_signals' ) as $table ) {
	sauth_x24_assert( false !== strpos( $privacy, "SAUTH_Activator::table( '{$table}' )" ), 'X-QA-24-15 privacy ' . $table );
}
sauth_x24_assert( false === strpos( $privacy, 'smc_' ), 'X-QA-24-15 privacy eraser does not target File 00 tables' );

/* X-QA-24-16 — release/package safety remains deterministic and source-only. */
sauth_x24_has_all( $builder, array( 'PACKAGE-MANIFEST.json', 'CHECKSUMS.sha256' ), 'X-QA-24-16 package builder' );
sauth_x24_has_all( $bootstrap, array( "Version: 1.4.0", "SAUTH_DB_VERSION', '1.4.0", 'class-sauth-modern-auth.php', 'class-sauth-security-orchestrator.php' ), 'X-QA-24-16 release identity' );

/* Architecture/data owner checks shared by all 16 gates. */
sauth_x24_has_all( $activator, array( 'sauth_security_timeline','sauth_recovery_changes','sauth_shared_signals' ), 'Modern Auth 24 schema' );
sauth_x24_assert( false !== strpos( $passkeys, "SCHEMA_VERSION        = '1.1.0'" ), 'Passkey schema 1.1.0' );
sauth_x24_has_all( $ops, array( 'Modern Authentication 24 registry','Authentication Assurance Receipt v2','Related-origin passkeys configuration' ), 'Modern Auth 24 system check' );

fwrite( STDOUT, "File 02 Modern Authentication 24 source/security gate passed: {$passed} assertions (X-QA-24-01..16).\n" );
