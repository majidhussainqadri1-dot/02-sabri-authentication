<?php
/** Permanent no-network regression for the R337 comprehensive remediation. */
$root = dirname( __DIR__ );
$read = static function ( $path ) use ( $root ) {
	$value = file_get_contents( $root . '/' . $path );
	return false === $value ? '' : $value;
};
$fail = array();
$count = 0;
$req = static function ( $ok, $message ) use ( &$fail, &$count ) {
	++$count;
	if ( ! $ok ) { $fail[] = $message; }
};
$has = static function ( $text, $needle, $message ) use ( $req ) { $req( false !== strpos( $text, $needle ), $message ); };
$not = static function ( $text, $needle, $message ) use ( $req ) { $req( false === strpos( $text, $needle ), $message ); };

$main = $read( 'sabri-authentication.php' );
$router = $read( 'includes/class-sauth-storage-router.php' );
$activator = $read( 'includes/class-sa-activator.php' );
$email = $read( 'includes/class-sauth-email-verification.php' );
$registration = $read( 'includes/class-sa-registration.php' );
$google = $read( 'includes/class-sa-google-oauth.php' );
$google_registration = $read( 'includes/class-sauth-google-registration.php' );
$passkeys = $read( 'includes/class-sauth-passkeys.php' );
$passkey_runtime = $read( 'includes/class-sauth-passkey-runtime.php' );
$sessions = $read( 'includes/class-sauth-session-manager.php' );
$risk = $read( 'includes/class-sauth-login-risk.php' );
$privacy = $read( 'includes/class-sa-privacy.php' );
$professional = $read( 'includes/class-sa-professional-reauthentication.php' );
$completion = $read( 'includes/class-sauth-completion-resolver.php' );
$adapter = $read( 'includes/class-sa-membership-adapter.php' );
$integration = $read( '.github/workflows/file00-1.2.44-real-integration.yml' );
$lock = json_decode( $read( 'RELEASE-LOCK.json' ), true );

/* R337 DB semantics remain 1.3.0. Later corrective rounds advanced only the
 * runtime line: R338 -> 1.3.1, R339 -> 1.3.2, R340 -> 1.3.3, and R341 ->
 * 1.3.4 for legacy verified-email reconciliation. */
$has( $main, 'Version: 1.3.4', 'current runtime header is not 1.3.4' );
$has( $main, "SAUTH_VERSION', '1.3.4", 'current runtime constant is not 1.3.4' );
$has( $main, "SAUTH_DB_VERSION', '1.3.0", 'R337 DB marker was not preserved for repaired migration' );
$evidence_status = is_array( $lock ) ? (string) ( $lock['cross_file_integration_evidence']['status'] ?? '' ) : '';
$req( in_array( $evidence_status, array( 'pending_exact_head', 'repository_integration_green_on_pre_release_identity_head', 'repository_integration_green' ), true ), 'current cross-file evidence status is invalid' );
$req( 31850253635 === (int) ( $lock['prior_cross_file_integration_evidence']['workflow_run_id'] ?? 0 ), 'historical 1.2.6 integration evidence was not preserved separately' );
$req( 31953732443 === (int) ( $lock['pre_route_fix_cross_file_integration_evidence']['workflow_run_id'] ?? 0 ), 'historical 1.3.1 pre-route-fix integration evidence was not preserved separately' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'external completion gates were falsely advanced' );

/* Storage migration must never trust attacker-influenced SQL text. */
$has( $router, 'private static $suspension_depth = 0;', 'nested router suspension state is missing' );
$has( $router, 'self::suspended()', 'router bypass is not explicit state' );
$not( $router, "stripos( \$query, 'INSERT IGNORE'", 'router still trusts query text to identify migration' );
$has( $activator, 'SAUTH_Storage_Router::suspend();', 'activator does not suspend routing for legacy copy' );
$has( $activator, '} finally {', 'activator migration lacks guaranteed router restoration' );
$has( $activator, 'SAUTH_Storage_Router::resume();', 'activator does not restore compatibility routing' );

/* Email challenge issuance and verification are compare-and-set state machines. */
$not( $email, '$wpdb->replace', 'email issuance still uses destructive REPLACE semantics' );
foreach ( array( "status='issuing'", "status='verifying'", 'delivery_failed', 'sauth_email_challenge_claim_failed', 'sauth_email_challenge_publish_failed' ) as $marker ) {
	$has( $email, $marker, 'email state-machine marker missing: ' . $marker );
}
$has( $email, 'SELECT email_hash,token_hash,status,sent_at,expires_at', 'email issuance lacks exact reservation readback' );

/* Google registration, login, link and unlink share one ordered lock namespace. */
foreach ( array( 'SELECT GET_LOCK(%s,%d)', 'SELECT IS_USED_LOCK(%s)', 'SELECT RELEASE_LOCK(%s)', 'subject_available_for_registration', 'google_subject_lock_name', 'google_user_lock_name' ) as $marker ) {
	$has( $google, $marker, 'Google database-lock invariant missing: ' . $marker );
}
$subject_check = strpos( $registration, 'subject_available_for_registration' );
$provider_mutation = strpos( $registration, 'SAUTH_Account_Contract::register_account' );
$req( false !== $subject_check && false !== $provider_mutation && $subject_check < $provider_mutation, 'Google subject ownership is not proved before File 00 account mutation' );
$has( $registration, 'registration_subject_matches', 'Google registration does not validate returned canonical subject UUID' );
$has( $google_registration, 'SA_Google_OAuth::link_locks_owned', 'Google registration finalization does not prove shared lock ownership' );
$has( $google, 'SAUTH_Session_Manager::session_binding_ready', 'Google login does not prove exact File 02 session projection' );

/* Password and passkey success require exact session plus durable risk evidence. */
$has( $risk, 'WP_Session_Tokens::get_instance( $user->ID )->create', 'password login does not explicitly own its WordPress token' );
$has( $risk, 'SAUTH_Session_Manager::session_binding_ready', 'password login does not prove exact session binding' );
$has( $risk, 'return $device_stored && $attempt_stored;', 'risk success does not require both durable projections' );
$has( $risk, "self::upsert_device( \$user_id, 100, 'expired' )", 'failed success-attempt persistence can leave the device trusted' );
$has( $sessions, 'public static function session_binding_ready', 'shared exact session postcondition is missing' );
$has( $sessions, 'public static function revoke_other_sessions', 'exact other-session revocation helper is missing' );
$has( $sessions, 'self::revoke_other_sessions( $user_id, $token', 'session UI bypasses exact WordPress/File 02 revoke-other postconditions' );
$has( $sessions, "'legacy_session_projection_failed_closed'", 'a failed upgrade-session projection can remain authenticated for the request' );
foreach ( array( $passkeys, $passkey_runtime ) as $source ) {
	$has( $source, "'backup_eligibility_changed'", 'passkey backup eligibility is not immutable' );
	$has( $source, '( $stored_count > 0 || $new_count > 0 ) && $new_count <= $stored_count', 'passkey non-zero counter reset/regression is not contained' );
	$has( $source, 'SAUTH_Session_Manager::session_binding_ready', 'passkey success does not prove exact session binding' );
	$has( $source, 'SAUTH_Login_Risk::record_successful_login', 'passkey success does not require durable risk evidence' );
}
$has( $passkey_runtime, "\$store_error = 'credential_store_postcondition_failed';", 'runtime passkey enrollment exits before its database lock can be released' );
$has( $passkeys, "\$store_error = 'credential_store_rollback_failed';", 'compatibility passkey enrollment does not remove an unproven credential row' );
$has( $passkey_runtime, "\$purpose = 'register' === \$operation ? 'passkey-enrollment'", 'runtime and compatibility passkey enrollment do not share one lock namespace' );
$has( $passkeys, 'strlen( $modulus ) < 256', 'RS256 minimum modulus is not enforced' );
$has( $passkeys, '"\\x01\\x00\\x01" !== $exponent', 'RS256 exponent 65537 is not enforced' );
$has( $passkeys, '$bits >= 2048', 'OpenSSL RSA bit strength is not revalidated' );
$has( $passkeys, 'self::key_details_match_algorithm( $details, $algorithm )', 'stored WebAuthn keys are not revalidated during assertion verification' );

/* Privacy is bounded and covers every File 02 identity projection. */
$has( $privacy, 'const EXPORT_LIMIT = 50;', 'privacy export page size is unbounded' );
$has( $privacy, 'sabri-authentication-devices', 'device projection is absent from privacy export' );
$has( $privacy, 'sabri-authentication-risk-challenges', 'risk-challenge projection is absent from privacy export' );
$has( $privacy, 'SAUTH_Storage_Router::suspend();', 'legacy privacy operations are still routed into canonical tables' );
$has( $privacy, 'LIMIT 50', 'privacy erasure lacks a bounded row batch' );
$has( $privacy, 'erase_identity_payload', 'outbox payload identity erasure is missing' );
$has( $privacy, 'payload_has_identity', 'outbox anonymization postcondition is missing' );

/* Downstream assurance provenance and retired File 00 MFA semantics. */
foreach ( array( 'provider_contract', 'provider_version', 'provider_scope_hash', 'provider_trace_id', "'clinical_sign_in' !==" ) as $marker ) {
	$has( $professional, $marker, 'professional assurance provenance marker missing: ' . $marker );
}
$has( $completion, 'file00_mfa_required', 'completion resolver does not require explicit File 00 MFA-retirement evidence' );
$has( $completion, "in_array( (string) ( \$assertion['result'] ?? '' ), array( 'allow', 'deny' ), true )", 'an unresolved File 00 assertion can retire a completion requirement' );

/* R339 preserves ownership by consuming only File 00's real page-map keys. */
$has( $adapter, "MEMBERSHIP_APPLICATION_KEY  = 'application'", 'File00 application route key is not canonical' );
$has( $adapter, "MEMBERSHIP_SECURITY_KEY     = 'security'", 'File00 security route key is not canonical' );
$has( $adapter, "MEMBERSHIP_STATUS_KEY       = 'status'", 'File00 status route key is not canonical' );
foreach ( array( 'sabri_profile', 'sabri_security_center', 'sabri_verification_status' ) as $forbidden ) {
	$not( $adapter, $forbidden, 'invented File00 membership route remains: ' . $forbidden );
}

/* Real integration must exercise the defect while the router is active and
 * also prove the canonical File 00 managed-page route contract. */
$has( $integration, 'Prove active-router one-way legacy migration on real MariaDB', 'real integration lacks active-router migration rehearsal' );
$has( $integration, 'SAUTH_Activator::migrate_legacy_tables()', 'real integration does not invoke the repaired migration while active' );
$has( $integration, 'Prove File 02 resolves exact File 00 canonical membership routes', 'real integration lacks File00 canonical route proof' );
$has( $integration, 'FILE02_VERSION: \'1.3.4\'', 'real integration runtime identity is stale' );
$has( $integration, 'FILE02_DB_VERSION: \'1.3.0\'', 'real integration DB identity is stale' );
$req( file_exists( $root . '/review-evidence/R337-COMPREHENSIVE-REMEDIATION.md' ), 'R337 review evidence is missing' );
$req( file_exists( $root . '/tests/r339-file00-canonical-route-contract-regression.php' ), 'R339 route-contract regression is missing' );
$req( file_exists( $root . '/tests/r340-passkey-assurance-cycle-regression.php' ), 'R340 passkey-assurance cycle regression is missing' );

if ( $fail ) {
	fwrite( STDERR, "R337 comprehensive remediation regressions:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}
echo 'R337 comprehensive remediation regression PASS (' . $count . ' assertions).' . PHP_EOL;