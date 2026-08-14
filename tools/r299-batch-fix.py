#!/usr/bin/env python3
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[1]

def replace(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    actual = text.count(old)
    if actual != count:
        raise SystemExit(f'{path}: expected {count} occurrence(s), found {actual}: {old[:120]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

def replace_all(path, old, new, minimum=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    actual = text.count(old)
    if actual < minimum:
        raise SystemExit(f'{path}: expected at least {minimum} occurrence(s), found {actual}: {old!r}')
    p.write_text(text.replace(old, new), encoding='utf-8')

# ---------------------------------------------------------------------------
# R299-A: Passkey management must not solicit retired File 00 factor secrets.
# R299-B: Passkey schema reconciliation must be dependency-gated and forceable.
# ---------------------------------------------------------------------------
replace(
    'includes/class-sauth-passkeys.php',
    "\tpublic static function maybe_install() {\n\t\tif ( self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) {\n\t\t\treturn;\n\t\t}",
    "\tpublic static function maybe_install( $force = false ) {\n\t\t/* Never mutate File 02 passkey schema/pages while the mandatory File 00\n\t\t * runtime, DB and account contract are unavailable. Guarded repair passes\n\t\t * $force=true only after proving those dependencies ready. */\n\t\tif ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {\n\t\t\treturn;\n\t\t}\n\t\tif ( ! $force && self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) {\n\t\t\treturn;\n\t\t}"
)
replace(
    'includes/class-sauth-passkeys.php',
    "\t\tdbDelta( $sql );\n\t\tself::ensure_manager_page();\n\t\tupdate_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );\n\t\tif ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {",
    "\t\tdbDelta( $sql );\n\t\tself::ensure_manager_page();\n\n\t\t/* Do not publish a successful schema marker until the table and private\n\t\t * manager page actually exist. A failed dbDelta/page write must remain\n\t\t * retryable on the next request or guarded repair. */\n\t\t$table_exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );\n\t\t$map = (array) get_option( 'sauth_page_map', array() );\n\t\t$page_id = isset( $map['passkeys'] ) ? absint( $map['passkeys'] ) : 0;\n\t\t$page_ready = $page_id > 0 && 'trash' !== get_post_status( $page_id );\n\t\tif ( ! $table_exists || ! $page_ready ) {\n\t\t\tdelete_option( self::OPTION_SCHEMA_VERSION );\n\t\t\treturn;\n\t\t}\n\t\tupdate_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );\n\t\tif ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) {\n\t\t\tdelete_option( self::OPTION_SCHEMA_VERSION );\n\t\t\treturn;\n\t\t}\n\t\tif ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {"
)
replace(
    'includes/class-sauth-passkeys.php',
    "\t\t\t\t\t\t<label for=\"sauth-passkey-password\">Current password <span class=\"screen-reader-text\">accepted only when stronger File 00 step-up is not already required</span></label>\n\t\t\t\t\t\t<input id=\"sauth-passkey-password\" type=\"password\" autocomplete=\"current-password\" maxlength=\"256\">\n\t\t\t\t\t\t<label for=\"sauth-passkey-stepup\">Authenticator or recovery code <span class=\"screen-reader-text\">required when File 00 two-factor protection is enabled</span></label>\n\t\t\t\t\t\t<input id=\"sauth-passkey-stepup\" type=\"text\" autocomplete=\"one-time-code\" maxlength=\"128\">",
    "\t\t\t\t\t\t<label for=\"sauth-passkey-password\">Current password <span class=\"screen-reader-text\">used when this session does not already have a fresh File 02 passkey assurance</span></label>\n\t\t\t\t\t\t<input id=\"sauth-passkey-password\" type=\"password\" autocomplete=\"current-password\" maxlength=\"256\">"
)
replace(
    'includes/class-sauth-passkeys.php',
    "\t\t\t\t<p class=\"sa-data-note\">If a device is lost, revoke its passkey and review Active Sessions. Support must never ask for your password, authenticator code or recovery code.</p>",
    "\t\t\t\t<p class=\"sa-data-note\">If a device is lost, revoke its passkey and review Active Sessions. Support must never ask for your password, passkey private key, biometric data or recovery material.</p>"
)
replace_all(
    'includes/class-sauth-passkeys.php',
    "\t\t$step_up = isset( $_POST['step_up_code'] ) ? sanitize_text_field( wp_unslash( $_POST['step_up_code'] ) ) : '';",
    "\t\t$step_up = ''; // Retired File 00 factor material is never accepted as File 02 authority.",
    minimum=2
)
replace(
    'includes/class-sauth-passkeys.php',
    "\tprivate static function reauthenticate_for_management( $user_id, $password, $step_up, $scope ) {\n\t\t$user_id = absint( $user_id );\n\t\tif ( ! $user_id ) {\n\t\t\treturn false;\n\t\t}\n\t\t$current = self::file00_assurance( array(), $user_id );\n\t\tif ( 'file02' === ( $current['owner'] ?? '' ) && ! empty( $current['passkey_asserted'] ) ) {\n\t\t\treturn true;\n\t\t}\n\t\t$two_factor_required = SA_Membership_Adapter::two_factor_enabled( $user_id );\n\t\tif ( '' !== (string) $step_up && class_exists( 'SA_Authentication_Assurance' ) ) {\n\t\t\t$result = SA_Authentication_Assurance::verify_and_record(\n\t\t\t\t$user_id,\n\t\t\t\t(string) $step_up,\n\t\t\t\tarray(\n\t\t\t\t\t'purpose' => 'authentication_link',\n\t\t\t\t\t'scope' => sanitize_key( $scope ),\n\t\t\t\t\t'trace_id' => strtolower( wp_generate_uuid4() ),\n\t\t\t\t)\n\t\t\t);\n\t\t\tif ( 'valid' === ( $result['result'] ?? '' ) ) {\n\t\t\t\treturn true;\n\t\t\t}\n\t\t}\n\t\tif ( $two_factor_required ) {\n\t\t\treturn false;\n\t\t}\n\t\tif ( '' !== (string) $password ) {\n\t\t\t$user = get_userdata( $user_id );\n\t\t\treturn $user instanceof WP_User && function_exists( 'wp_check_password' ) && wp_check_password( (string) $password, (string) $user->user_pass, $user_id );\n\t\t}\n\t\treturn false;\n\t}",
    "\tprivate static function reauthenticate_for_management( $user_id, $password, $step_up, $scope ) {\n\t\t$user_id = absint( $user_id );\n\t\t$step_up = ''; // Compatibility argument only; File 00 TOTP/recovery codes are retired.\n\t\t$scope = '';\n\t\tif ( ! $user_id ) {\n\t\t\treturn false;\n\t\t}\n\t\t$current = self::file00_assurance( array(), $user_id );\n\t\tif ( 'file02' === ( $current['owner'] ?? '' ) && ! empty( $current['passkey_asserted'] ) ) {\n\t\t\treturn true;\n\t\t}\n\t\tif ( '' !== (string) $password ) {\n\t\t\t$user = get_userdata( $user_id );\n\t\t\treturn $user instanceof WP_User && function_exists( 'wp_check_password' ) && wp_check_password( (string) $password, (string) $user->user_pass, $user_id );\n\t\t}\n\t\treturn false;\n\t}"
)
replace(
    'includes/class-sauth-passkeys.php',
    "\t\treturn '' !== $ctx['rp_id'] && ( 'https' === $ctx['scheme'] || ( $local && 'http' === $ctx['scheme'] ) ) && function_exists( 'openssl_verify' );",
    "\t\treturn '' !== $ctx['rp_id']\n\t\t\t&& ( 'https' === $ctx['scheme'] || ( $local && 'http' === $ctx['scheme'] ) )\n\t\t\t&& function_exists( 'openssl_verify' )\n\t\t\t&& SA_Membership_Adapter::available()\n\t\t\t&& SAUTH_Account_Contract::provider_available();"
)

# Browser must not collect or transmit retired factor material.
replace_all('assets/js/authentication.js', "    var stepUp = document.getElementById('sauth-passkey-stepup');\n", '', minimum=2)
replace(
    'assets/js/authentication.js',
    "        nonce: cfg.nonce || '',\n        current_password: password ? password.value : '',\n        step_up_code: stepUp ? stepUp.value : ''\n",
    "        nonce: cfg.nonce || '',\n        current_password: password ? password.value : ''\n"
)
replace(
    'assets/js/authentication.js',
    "      if (stepUp) {\n        stepUp.value = '';\n      }\n",
    '',
    count=2
)
replace(
    'assets/js/authentication.js',
    "        credential_id: button.getAttribute('data-sauth-passkey-revoke') || '',\n        current_password: password ? password.value : '',\n        step_up_code: stepUp ? stepUp.value : ''\n",
    "        credential_id: button.getAttribute('data-sauth-passkey-revoke') || '',\n        current_password: password ? password.value : ''\n"
)

# ---------------------------------------------------------------------------
# R299-C: Password recovery is fail-closed under Safe Mode/dependency outage.
# ---------------------------------------------------------------------------
replace(
    'includes/class-sa-registration.php',
    "\tpublic function forgot_password() {\n\t\tcheck_admin_referer( 'sa_forgot_password', 'sa_nonce' );",
    "\tpublic function forgot_password() {\n\t\tcheck_admin_referer( 'sa_forgot_password', 'sa_nonce' );\n\t\tif ( SAUTH_Operations::safe_mode() || ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {\n\t\t\twp_safe_redirect( SA_Security::message_url( 'forgot', 'success', 'If the account exists and recovery is available, a reset email will be sent.' ) );\n\t\t\texit;\n\t\t}"
)
replace(
    'includes/class-sa-registration.php',
    "\t\tif ( $user_id ) { SAUTH_Privacy_Jobs::forget_job( $user_id, $key ); }\n\t\tif ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { return; }",
    "\t\tif ( $user_id ) { SAUTH_Privacy_Jobs::forget_job( $user_id, $key ); }\n\t\tif ( SAUTH_Operations::safe_mode() || ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) { return; }\n\t\tif ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { return; }"
)
replace(
    'includes/class-sa-registration.php',
    "\tpublic function reset_password() {\n\t\t$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';",
    "\tpublic function reset_password() {\n\t\tif ( SAUTH_Operations::safe_mode() || ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {\n\t\t\twp_safe_redirect( SA_Security::message_url( 'reset', 'error', 'Password reset is temporarily unavailable. No credential was changed.' ) );\n\t\t\texit;\n\t\t}\n\t\t$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';"
)

# ---------------------------------------------------------------------------
# R299-D: Email resend jobs join privacy erasure index and Safe Mode boundary.
# ---------------------------------------------------------------------------
replace(
    'includes/class-sauth-email-verification.php',
    "\t\tif ( ! SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {\n\t\t\treturn new WP_Error( 'sauth_email_privacy_erasure_active', 'Email verification is paused while File 02 privacy erasure is active.' );\n\t\t}\n\t\t$email = sanitize_email( (string) $canonical_user->user_email );",
    "\t\tif ( SAUTH_Operations::safe_mode() ) {\n\t\t\treturn new WP_Error( 'sauth_email_safe_mode', 'Email verification is temporarily paused by Safe Mode.' );\n\t\t}\n\t\tif ( ! SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {\n\t\t\treturn new WP_Error( 'sauth_email_privacy_erasure_active', 'Email verification is paused while File 02 privacy erasure is active.' );\n\t\t}\n\t\t$email = sanitize_email( (string) $canonical_user->user_email );",
    count=1
)
replace(
    'includes/class-sauth-email-verification.php',
    "\t\t\t\tset_transient( $job_key, $job, self::RESEND_JOB_TTL );\n\t\t\t\tif ( get_transient( $job_key ) === $job && function_exists( 'wp_schedule_single_event' ) ) {\n\t\t\t\t\twp_schedule_single_event( time() + 1, self::RESEND_JOB_HOOK, array( $job_token ) );\n\t\t\t\t} else {\n\t\t\t\t\tdelete_transient( $job_key );\n\t\t\t\t}",
    "\t\t\t\tset_transient( $job_key, $job, self::RESEND_JOB_TTL );\n\t\t\t\t$stored = get_transient( $job_key ) === $job;\n\t\t\t\t$indexed = ! $job_user_id || SAUTH_Privacy_Jobs::register_job( $job_user_id, $job_key );\n\t\t\t\t$scheduled = false;\n\t\t\t\tif ( $stored && $indexed && function_exists( 'wp_schedule_single_event' ) ) {\n\t\t\t\t\t$scheduled = false !== wp_schedule_single_event( time() + 1, self::RESEND_JOB_HOOK, array( $job_token ) );\n\t\t\t\t}\n\t\t\t\tif ( ! $scheduled ) {\n\t\t\t\t\tdelete_transient( $job_key );\n\t\t\t\t\tif ( $job_user_id ) { SAUTH_Privacy_Jobs::forget_job( $job_user_id, $job_key ); }\n\t\t\t\t}"
)
replace(
    'includes/class-sauth-email-verification.php',
    "\t\t$job = get_transient( $key );\n\t\tdelete_transient( $key );\n\t\tif ( ! is_array( $job ) || absint( $job['created_at'] ?? 0 ) < time() - self::RESEND_JOB_TTL ) { return; }\n\t\t$user_id = absint( $job['user_id'] ?? 0 );\n\t\t$epoch   = (string) ( $job['privacy_epoch'] ?? '' );\n\t\tif ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { return; }",
    "\t\t$job = get_transient( $key );\n\t\tdelete_transient( $key );\n\t\tif ( ! is_array( $job ) ) { return; }\n\t\t$user_id = absint( $job['user_id'] ?? 0 );\n\t\t$epoch   = (string) ( $job['privacy_epoch'] ?? '' );\n\t\tif ( $user_id ) { SAUTH_Privacy_Jobs::forget_job( $user_id, $key ); }\n\t\tif ( absint( $job['created_at'] ?? 0 ) < time() - self::RESEND_JOB_TTL ) { return; }\n\t\tif ( SAUTH_Operations::safe_mode() || ! SAUTH_Account_Contract::provider_available() ) { return; }\n\t\tif ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { return; }"
)
replace(
    'includes/class-sauth-email-verification.php',
    "\t\tif ( ! SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {\n\t\t\treturn new WP_Error( 'sauth_email_privacy_erasure_active', 'Email verification is paused while File 02 privacy erasure is active.' );\n\t\t}\n\t\tif ( SA_Security::rate_limited( 'email_verification_attempt', self::MAX_ATTEMPTS, HOUR_IN_SECONDS, (string) $user_id ) ) {",
    "\t\tif ( SAUTH_Operations::safe_mode() ) {\n\t\t\treturn new WP_Error( 'sauth_email_safe_mode', 'Email verification is temporarily paused by Safe Mode.' );\n\t\t}\n\t\tif ( ! SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {\n\t\t\treturn new WP_Error( 'sauth_email_privacy_erasure_active', 'Email verification is paused while File 02 privacy erasure is active.' );\n\t\t}\n\t\tif ( SA_Security::rate_limited( 'email_verification_attempt', self::MAX_ATTEMPTS, HOUR_IN_SECONDS, (string) $user_id ) ) {",
    count=1
)

# ---------------------------------------------------------------------------
# R299-E/F/G: Google login completion/event parity, link/unlink event evidence,
# unlink postconditions, and one owner for HTTP circuit accounting.
# ---------------------------------------------------------------------------
replace(
    'includes/class-sa-google-oauth.php',
    "\t\t\tSA_Membership_Adapter::audit( 'google_account_linked', $user->ID );",
    "\t\t\tSAUTH_Event_Outbox::emit( 'GoogleAccountLinked.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc' ), 'security' );\n\t\t\tSA_Membership_Adapter::audit( 'google_account_linked', $user->ID );"
)
replace(
    'includes/class-sa-google-oauth.php',
    "\t\twp_set_current_user( $user->ID );\n\t\twp_set_auth_cookie( $user->ID, true, is_ssl() );\n\t\tself::safe_observer_action( 'wp_login', array( $user->user_login, $user ) );\n\t\tupdate_user_meta( $user->ID, '_sauth_google_last_login_at', current_time( 'mysql', true ) );\n\t\tupdate_user_meta( $user->ID, '_sa_google_last_login_at', current_time( 'mysql', true ) );\n\t\tSA_Membership_Adapter::audit( 'google_login_success', $user->ID );\n\t\t$destination = isset( $data['redirect'] ) ? SA_Security::safe_redirect( $data['redirect'] ) : SA_Membership_Adapter::profile_url();\n\t\twp_safe_redirect( $destination );",
    "\t\t$completion = SAUTH_Account_Contract::completion_state( $user->ID, array( 'purpose' => 'google_sign_in' ) );\n\t\tif ( ! is_array( $completion ) || 'allow' !== ( $completion['result'] ?? '' ) ) {\n\t\t\t$this->fail( 'Account completion status could not be verified for Google sign-in.', 'login' );\n\t\t}\n\t\twp_set_current_user( $user->ID );\n\t\twp_set_auth_cookie( $user->ID, true, is_ssl() );\n\t\tself::safe_observer_action( 'wp_login', array( $user->user_login, $user ) );\n\t\tSAUTH_Login_Risk::record_successful_login( $user->ID, 'google', 0 );\n\t\tSAUTH_Event_Outbox::emit( 'AccountAuthenticationSucceeded.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc', 'risk' => 'provider_authenticated' ), 'security' );\n\t\tupdate_user_meta( $user->ID, '_sauth_google_last_login_at', current_time( 'mysql', true ) );\n\t\tupdate_user_meta( $user->ID, '_sa_google_last_login_at', current_time( 'mysql', true ) );\n\t\tSA_Membership_Adapter::audit( 'google_login_success', $user->ID );\n\t\t$requested = isset( $data['redirect'] ) ? SA_Security::safe_redirect( $data['redirect'], SA_Membership_Adapter::profile_url() ) : SA_Membership_Adapter::profile_url();\n\t\t$resolution = SAUTH_Completion_Resolver::resolve( $user->ID, $requested, $completion );\n\t\t$destination = SA_Membership_Adapter::profile_url();\n\t\tif ( 'allow' === ( $resolution['result'] ?? '' ) || 'completion_loop_prevented' === ( $resolution['reason_code'] ?? '' ) ) {\n\t\t\t$destination = SA_Security::safe_redirect( (string) ( $resolution['destination'] ?? '' ), $destination );\n\t\t}\n\t\twp_safe_redirect( $destination );"
)
replace(
    'includes/class-sa-google-oauth.php',
    "\t\tforeach ( self::google_meta_keys() as $key ) {\n\t\t\tdelete_user_meta( $user_id, $key );\n\t\t}\n\t\tif ( class_exists( 'WP_Session_Tokens' ) ) {\n\t\t\tWP_Session_Tokens::get_instance( $user_id )->destroy_others( wp_get_session_token() );\n\t\t}\n\t\tSA_Security::clear_rate_limit( 'google_unlink', (string) $user_id );\n\t\tSA_Membership_Adapter::audit( 'google_account_unlinked', $user_id );",
    "\t\tforeach ( self::google_meta_keys() as $key ) {\n\t\t\tdelete_user_meta( $user_id, $key );\n\t\t}\n\t\t$remaining = array();\n\t\tforeach ( self::google_meta_keys() as $key ) {\n\t\t\tif ( metadata_exists( 'user', $user_id, $key ) ) { $remaining[] = $key; }\n\t\t}\n\t\tif ( ! empty( $remaining ) ) {\n\t\t\t/* Fail closed even if the underlying store partially rejected deletion. */\n\t\t\tupdate_user_meta( $user_id, '_sauth_google_account', '0' );\n\t\t\tupdate_user_meta( $user_id, '_sa_google_account', '0' );\n\t\t\tif ( class_exists( 'WP_Session_Tokens' ) ) { WP_Session_Tokens::get_instance( $user_id )->destroy_all(); }\n\t\t\tSA_Membership_Adapter::audit( 'google_account_unlink_incomplete', $user_id, array( 'remaining_count' => count( $remaining ) ) );\n\t\t\twp_safe_redirect( SA_Security::message_url( 'google_account', 'error', 'Google unlinking could not be completed safely. All sessions were revoked; sign in again and contact support.' ) );\n\t\t\texit;\n\t\t}\n\t\tif ( class_exists( 'WP_Session_Tokens' ) ) {\n\t\t\tWP_Session_Tokens::get_instance( $user_id )->destroy_others( wp_get_session_token() );\n\t\t}\n\t\tSA_Security::clear_rate_limit( 'google_unlink', (string) $user_id );\n\t\tSAUTH_Event_Outbox::emit( 'GoogleAccountUnlinked.v1', $user_id, $user_id, array( 'method' => 'google_oidc' ), 'security' );\n\t\tSA_Membership_Adapter::audit( 'google_account_unlinked', $user_id );"
)

# The provider HTTP guard is the single transport circuit owner; semantic claim
# failures are authentication failures, not provider-availability failures.
for line in [
    "\t\t$started = microtime( true );\n",
    "\t\t$latency = (int) round( ( microtime( true ) - $started ) * 1000 );\n",
    "\t\t\tSAUTH_Provider_Health::record_failure( 'google', 'registration_token_exchange_failed', $latency );\n",
    "\t\t\tSAUTH_Provider_Health::record_failure( 'google', 'registration_identity_token_missing', $latency );\n",
    "\t\t\tSAUTH_Provider_Health::record_failure( 'google', 'registration_identity_validation_failed', $latency );\n",
    "\t\t\tSAUTH_Provider_Health::record_failure( 'google', 'registration_claims_invalid', $latency );\n",
    "\t\tSAUTH_Provider_Health::record_success( 'google', $latency );\n",
]:
    replace('includes/class-sauth-google-registration.php', line, '')
replace(
    'includes/class-sauth-google-registration.php',
    "\t\tif ( function_exists( 'wp_schedule_single_event' ) ) {\n\t\t\twp_schedule_single_event( time() + self::CLAIM_TTL, self::CLAIM_HOOK, array( $claim_key ) );\n\t\t}\n\t\t$data = self::context( $token );\n\t\tdelete_transient( self::context_key( $token ) );\n\t\treturn $data;",
    "\t\t$scheduled = false;\n\t\tif ( function_exists( 'wp_schedule_single_event' ) ) {\n\t\t\t$scheduled = false !== wp_schedule_single_event( time() + self::CLAIM_TTL, self::CLAIM_HOOK, array( $claim_key ) );\n\t\t}\n\t\t$data = self::context( $token );\n\t\tdelete_transient( self::context_key( $token ) );\n\t\t/* If cron could not retain the cleanup, release only after the one-time\n\t\t * context is gone; concurrent replay still cannot recover that context. */\n\t\tif ( ! $scheduled ) { delete_option( $claim_key ); }\n\t\treturn $data;"
)

# ---------------------------------------------------------------------------
# R299-H: Safe Mode must cover credential/account mutation surfaces.
# ---------------------------------------------------------------------------
replace(
    'includes/class-sauth-operations.php',
    "\t\t\t'sauth_register',\n\t\t);",
    "\t\t\t'sauth_register',\n\t\t\t'sa_forgot_password',\n\t\t\t'sauth_forgot_password',\n\t\t\t'sa_reset_password',\n\t\t\t'sauth_reset_password',\n\t\t\t'sauth_verify_email',\n\t\t\t'sauth_resend_email_verification',\n\t\t\t'sa_google_unlink',\n\t\t);"
)

# Newer completion vocabulary must be recognized by the canonical helper.
replace(
    'includes/class-sauth-completion-resolver.php',
    "\t\t\tarray( 'email', 'email_verification', 'phone', 'mobile_verification', 'age', 'guardian', 'profile', 'identity', 'terms', 'privacy', 'two_factor', 'mfa', 'verification' ),",
    "\t\t\tarray( 'email', 'email_verification', 'phone', 'mobile_verification', 'age', 'guardian', 'profile', 'profile_photo', 'identity', 'identity_reference', 'address', 'city', 'country', 'account_type', 'terms', 'privacy', 'ethical_conduct', 'two_factor', 'mfa', 'verification' ),"
)

# ---------------------------------------------------------------------------
# R299-I: Google settings commit as one verified unit or roll back completely.
# ---------------------------------------------------------------------------
old_method = """\tpublic function save_settings() {
\t\tif ( ! current_user_can( 'manage_options' ) ) {
\t\t\twp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) );
\t\t}
\t\tcheck_admin_referer( 'sa_save_auth_settings', 'sa_nonce' );
\t\tif ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {
\t\t\twp_safe_redirect( add_query_arg( 'error', 'dependency_unavailable', self::settings_url() ) );
\t\t\texit;
\t\t}
\t\t$client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
\t\tif ( '' !== $client_id && ! preg_match( '/^[0-9A-Za-z._-]+\\.apps\\.googleusercontent\\.com$/', $client_id ) ) {
\t\t\twp_safe_redirect( add_query_arg( 'error', 'invalid_client_id', self::settings_url() ) );
\t\t\texit;
\t\t}
\t\tupdate_option( 'sauth_google_client_id', $client_id, false );
\t\tupdate_option( 'sa_google_client_id', $client_id, false );

\t\tif ( ! empty( $_POST['clear_google_client_secret'] ) ) {
\t\t\tdelete_option( 'sauth_google_client_secret' );
\t\t\tdelete_option( 'sa_google_client_secret' );
\t\t} elseif ( isset( $_POST['google_client_secret'] ) && '' !== trim( (string) wp_unslash( $_POST['google_client_secret'] ) ) ) {
\t\t\t$secret    = trim( sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) );
\t\t\t$encrypted = SA_Security::encrypt( $secret );
\t\t\t$secret = '';
\t\t\tunset( $_POST['google_client_secret'] );
\t\t\tif ( '' === $encrypted ) {
\t\t\t\twp_safe_redirect( add_query_arg( 'error', 'encryption_failed', self::settings_url() ) );
\t\t\t\texit;
\t\t\t}
\t\t\tupdate_option( 'sauth_google_client_secret', $encrypted, false );
\t\t\tupdate_option( 'sa_google_client_secret', $encrypted, false );
\t\t}

\t\t$enable = ! empty( $_POST['google_enabled'] );
\t\tif ( $enable && ( SAUTH_Operations::safe_mode() || ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() || ! is_ssl() || '' === $client_id || '' === SA_Security::decrypt( (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) ) ) ) ) {
\t\t\tupdate_option( 'sauth_google_enabled', '0', false );
\t\t\tupdate_option( 'sa_google_enabled', '0', false );
\t\t\twp_safe_redirect( add_query_arg( 'error', 'not_ready', self::settings_url() ) );
\t\t\texit;
\t\t}
\t\tupdate_option( 'sauth_google_enabled', $enable ? '1' : '0', false );
\t\tupdate_option( 'sa_google_enabled', $enable ? '1' : '0', false );
\t\tSAUTH_Provider_Health::reset( 'google' );
\t\twp_safe_redirect( add_query_arg( 'updated', '1', self::settings_url() ) );
\t\texit;
\t}
"""
new_method = """\tpublic function save_settings() {
\t\tif ( ! current_user_can( 'manage_options' ) ) {
\t\t\twp_die( esc_html__( 'Access denied.', 'sabri-authentication' ) );
\t\t}
\t\tcheck_admin_referer( 'sa_save_auth_settings', 'sa_nonce' );
\t\tif ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {
\t\t\twp_safe_redirect( add_query_arg( 'error', 'dependency_unavailable', self::settings_url() ) );
\t\t\texit;
\t\t}
\t\t$client_id = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
\t\tif ( '' !== $client_id && ! preg_match( '/^[0-9A-Za-z._-]+\\.apps\\.googleusercontent\\.com$/', $client_id ) ) {
\t\t\twp_safe_redirect( add_query_arg( 'error', 'invalid_client_id', self::settings_url() ) );
\t\t\texit;
\t\t}

\t\t$encrypted = (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) );
\t\tif ( ! empty( $_POST['clear_google_client_secret'] ) ) {
\t\t\t$encrypted = '';
\t\t} elseif ( isset( $_POST['google_client_secret'] ) && '' !== trim( (string) wp_unslash( $_POST['google_client_secret'] ) ) ) {
\t\t\t$secret = trim( sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) );
\t\t\t$encrypted = SA_Security::encrypt( $secret );
\t\t\t$secret = '';
\t\t\tunset( $_POST['google_client_secret'] );
\t\t\tif ( '' === $encrypted ) {
\t\t\t\twp_safe_redirect( add_query_arg( 'error', 'encryption_failed', self::settings_url() ) );
\t\t\t\texit;
\t\t\t}
\t\t}

\t\t$enable = ! empty( $_POST['google_enabled'] );
\t\tif ( $enable && ( SAUTH_Operations::safe_mode() || ! is_ssl() || '' === $client_id || '' === $encrypted || '' === SA_Security::decrypt( $encrypted ) ) ) {
\t\t\twp_safe_redirect( add_query_arg( 'error', 'not_ready', self::settings_url() ) );
\t\t\texit;
\t\t}

\t\t$keys = array(
\t\t\t'sauth_google_client_id', 'sa_google_client_id',
\t\t\t'sauth_google_client_secret', 'sa_google_client_secret',
\t\t\t'sauth_google_enabled', 'sa_google_enabled',
\t\t);
\t\t$snapshot = array();
\t\tforeach ( $keys as $key ) { $snapshot[ $key ] = get_option( $key, null ); }
\t\t$desired = array(
\t\t\t'sauth_google_client_id' => $client_id,
\t\t\t'sa_google_client_id' => $client_id,
\t\t\t'sauth_google_client_secret' => $encrypted,
\t\t\t'sa_google_client_secret' => $encrypted,
\t\t\t'sauth_google_enabled' => $enable ? '1' : '0',
\t\t\t'sa_google_enabled' => $enable ? '1' : '0',
\t\t);
\t\t$stored_ok = true;
\t\tforeach ( $desired as $key => $value ) {
\t\t\tif ( '' === $value && false !== strpos( $key, 'client_secret' ) ) { delete_option( $key ); }
\t\t\telse { update_option( $key, $value, false ); }
\t\t\t$current = get_option( $key, null );
\t\t\tif ( '' === $value && false !== strpos( $key, 'client_secret' ) ) {
\t\t\t\t$stored_ok = $stored_ok && null === $current;
\t\t\t} else {
\t\t\t\t$stored_ok = $stored_ok && (string) $current === (string) $value;
\t\t\t}
\t\t}
\t\tif ( ! $stored_ok ) {
\t\t\tforeach ( $snapshot as $key => $value ) {
\t\t\t\tif ( null === $value ) { delete_option( $key ); }
\t\t\t\telse { update_option( $key, $value, false ); }
\t\t\t}
\t\t\twp_safe_redirect( add_query_arg( 'error', 'settings_store_failed', self::settings_url() ) );
\t\t\texit;
\t\t}
\t\tSAUTH_Provider_Health::reset( 'google' );
\t\twp_safe_redirect( add_query_arg( 'updated', '1', self::settings_url() ) );
\t\texit;
\t}
"""
replace('includes/class-sa-plugin.php', old_method, new_method)

# ---------------------------------------------------------------------------
# R299-J: Documentation/release truth must match the actual review candidate.
# ---------------------------------------------------------------------------
replace('PLAN-TRACEABILITY.md', '**Candidate branch:** `codex/file02-four-plan-passkey-completion-1.2.0`', '**Candidate branch:** `review/file02-r291-r300-main-2026-08-14`')
replace('PLAN-TRACEABILITY.md', '**Candidate version/schema:** `1.2.0 / 1.2.0`; passkey schema `1.0.0`', '**Candidate version/schema:** `1.2.1 / 1.2.0`; passkey schema `1.0.0`')
replace('PLAN-TRACEABILITY.md', '| Management reauth | current passkey or File 00 step-up; password bootstrap only when File 00 2FA is not enabled |', '| Management reauth | fresh File 02 passkey assurance, otherwise current password; retired File 00 factor codes are never solicited or accepted |')
replace('PLAN-TRACEABILITY.md', '| F02-FR-008 Login risk | new device/network/failure/provider state and File 00-owned step-up | Implemented |', '| F02-FR-008 Login risk | new device/network/failure/provider state; elevated password risk requires a separate File 02 passkey sign-in | Implemented |')
replace('STATUS.md', '- Branch: `fix/live-bootstrap-storage-router-1.2.1`', '- Branch: `review/file02-r291-r300-main-2026-08-14`')
replace('STATUS.md', '- New-device/network/recent-failure challenge with File 00-owned MFA/step-up policy.', '- New-device/network/recent-failure policy with elevated password risk requiring a separate File 02 passkey sign-in; retired File 00 factor codes are not an authentication ceremony.')
replace('README.md', '- suspicious-login risk challenge and File 00-owned step-up;', '- suspicious-login risk policy with elevated password risk requiring a separate File 02 passkey sign-in;')
replace('readme.txt', 'Version 1.2.1 is the live-proven bootstrap correction over the four-plan 1.2.0 source candidate.', 'Version 1.2.1 contains the repository correction for a bootstrap defect proven by a real File 00/File 02 WordPress integration run against 1.2.0.')
replace('readme.txt', '* Passkey enrollment/revocation requires fresh reauthentication. If File 00 two-factor protection is enabled, password-only management is rejected and File 00 step-up is required.', '* Passkey enrollment/revocation requires fresh reauthentication: a fresh File 02 passkey assurance, otherwise the current password. Retired File 00 Authenticator/recovery codes are never solicited or accepted as File 02 authentication authority.')
replace('readme.txt', '* New-device/network/recent-failure risk scoring with File 00-owned step-up.', '* New-device/network/recent-failure risk scoring; elevated password risk requires a separate File 02 passkey sign-in.')
replace('MIGRATION.md', '# File 02 Migration Guide — 1.2.0', '# File 02 Migration Guide — 1.2.1')
replace('MIGRATION.md', 'The 1.2.0 candidate preserves the seven canonical authentication tables from 1.1.0 and adds the isolated `sauth_passkeys` schema `1.0.0` plus its private passkey manager page.', 'The 1.2.1 candidate preserves the seven canonical authentication tables from 1.1.0 and the additive `sauth_passkeys` schema `1.0.0` introduced in 1.2.0, while correcting bootstrap and review-discovered lifecycle controls without changing DB schema 1.2.0.')
replace('MIGRATION.md', '- Verify File 00 `smc.authentication-account 1.1.0`, existing step-up assurance and later Advanced Trust passkey consumer compatibility.', '- Verify File 00 `smc.authentication-account 1.1.0`, current membership assurance, and the Advanced Trust consumer compatibility for File 02 passkey assurance; retired File 00 factor codes are not a File 02 ceremony.')
replace('MIGRATION.md', '1. Install the exact deterministic 1.2.0 package with registration/provider/passkey mutations gated.', '1. Install the exact deterministic 1.2.1 package only after its release build is separately produced and approved; registration/provider/passkey mutations remain gated.')
replace('MIGRATION.md', '3. Confirm `sauth_version` and `sauth_db_version` are `1.2.0` and `sauth_passkey_schema_version` is `1.0.0`.', '3. Confirm `sauth_version` is `1.2.1`, `sauth_db_version` remains `1.2.0`, and `sauth_passkey_schema_version` is `1.0.0`.')
replace('ROLLBACK.md', '# File 02 Rollback Guide — 1.1.0', '# File 02 Rollback Guide — 1.2.1')
replace('BACKUP-RESTORE.md', '# File 02 Backup and Restore Runbook — 1.1.0', '# File 02 Backup and Restore Runbook — 1.2.1')

lock_path = ROOT / 'RELEASE-LOCK.json'
lock = json.loads(lock_path.read_text(encoding='utf-8'))
lock['candidate_branch'] = 'review/file02-r291-r300-main-2026-08-14'
lock['status']['coded'] = 'review_candidate'
lock['status']['automated_qa'] = 'review_exact_head_gated'
lock_path.write_text(json.dumps(lock, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')

# Permanent R299 cross-flow regression gate.
(ROOT / 'tests/r299-cross-flow-regression.php').write_text(r'''<?php
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
r299_has( $google, "SAUTH_Login_Risk::record_successful_login( $user->ID, 'google', 0 );", 'Google login is absent from risk/session trust projection' );
r299_has( $google, "AccountAuthenticationSucceeded.v1", 'Google login success event is missing' );
r299_has( $google, "GoogleAccountLinked.v1", 'Google link event is missing' );
r299_has( $google, "GoogleAccountUnlinked.v1", 'Google unlink event is missing' );
r299_has( $google, "completion_state( $user->ID, array( 'purpose' => 'google_sign_in' ) )", 'Google login does not enforce completion state' );
r299_has( $google, 'google_account_unlink_incomplete', 'Google unlink lacks postcondition containment' );
r299_not( $google_reg, "SAUTH_Provider_Health::record_failure( 'google'", 'Google registration double-counts HTTP circuit failures' );
r299_not( $google_reg, "SAUTH_Provider_Health::record_success( 'google'", 'Google registration double-counts HTTP circuit success' );
r299_has( $google_reg, 'if ( ! $scheduled ) { delete_option( $claim_key ); }', 'Google registration replay claim can leak if cron scheduling fails' );
foreach ( array( 'sa_forgot_password', 'sauth_forgot_password', 'sa_reset_password', 'sauth_reset_password', 'sauth_verify_email', 'sauth_resend_email_verification', 'sa_google_unlink' ) as $action ) { r299_has( $ops, "'" . $action . "'", 'Safe Mode misses ' . $action ); }
foreach ( array( 'profile_photo', 'identity_reference', 'address', 'city', 'country', 'account_type', 'ethical_conduct' ) as $step ) { r299_has( $completion, "'" . $step . "'", 'completion helper misses ' . $step ); }
r299_has( $plugin, '$snapshot = array();', 'Google settings lack transactional snapshot' );
r299_has( $plugin, "settings_store_failed", 'Google settings lack rollback failure path' );
r299_has( $plan, 'review/file02-r291-r300-main-2026-08-14', 'traceability candidate branch is stale' );
r299_has( $status, 'review/file02-r291-r300-main-2026-08-14', 'status candidate branch is stale' );
if ( $fail ) { fwrite( STDERR, "R299 regression failures:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R299 cross-flow regression PASS (' . $n . " assertions).\n";
''', encoding='utf-8')

# Ensure both review CI and future release CI execute the new regression.
replace(
    '.github/workflows/review-branch-integrity.yml',
    "          php tests/r291-r300-review-regression.php\n",
    "          php tests/r291-r300-review-regression.php\n          php tests/r299-cross-flow-regression.php\n"
)
replace(
    '.github/workflows/baseline-integrity.yml',
    "              'tests/professional-reauthentication-unit.php',\n              'tests/architecture-check.py', 'tests/security-unit.php',",
    "              'tests/professional-reauthentication-unit.php',\n              'tests/r299-cross-flow-regression.php',\n              'tests/architecture-check.py', 'tests/security-unit.php',"
)
replace(
    '.github/workflows/baseline-integrity.yml',
    "          php tests/three-plan-completion-unit.php\n",
    "          php tests/three-plan-completion-unit.php\n          php tests/r299-cross-flow-regression.php\n"
)

print('R299 batch corrections staged successfully.')
