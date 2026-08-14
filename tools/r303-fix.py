#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

def replace(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    actual = text.count(old)
    if actual != count:
        raise SystemExit(f'{path}: expected {count}, found {actual}: {old[:120]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

# R303-A: wipe registration password material immediately after the owner call.
replace(
    'includes/class-sa-registration.php',
    "\t\t$latency = (int) round( ( microtime( true ) - $started ) * 1000 );\n\t\tif ( 'allow' !== ( $result['result'] ?? '' ) || empty( $result['user_id'] ) ) {",
    "\t\t$latency = (int) round( ( microtime( true ) - $started ) * 1000 );\n\t\t/* The canonical owner call is the last operation that needs plaintext\n\t\t * registration passwords. Remove them before any mail, audit or error path. */\n\t\t$payload['password'] = '';\n\t\t$payload['password_confirm'] = '';\n\t\tunset( $_POST['password'], $_POST['password_confirm'] );\n\t\tif ( 'allow' !== ( $result['result'] ?? '' ) || empty( $result['user_id'] ) ) {"
)
replace(
    'includes/class-sa-registration.php',
    "\t\t$payload['password'] = '';\n\t\t$payload['password_confirm'] = '';\n\t\tunset( $_POST['password'], $_POST['password_confirm'] );\n\t\tSA_Security::clear_rate_limit( 'registration_email', $email_key );",
    "\t\tSA_Security::clear_rate_limit( 'registration_email', $email_key );"
)

# R303-B: password reset success requires a verifiable persistence postcondition.
replace(
    'includes/class-sa-registration.php',
    "\t\treset_password( $user, $password );\n\t\t$password = ''; $confirm = '';\n\t\tunset( $_POST['password'], $_POST['password_confirm'] );\n\t\tSAUTH_Session_Manager::revoke_user_sessions( $user->ID, 'password_reset' );",
    "\t\t$user_id = (int) $user->ID;\n\t\treset_password( $user, $password );\n\t\t$fresh_user = get_userdata( $user_id );\n\t\t$persisted = $fresh_user instanceof WP_User\n\t\t\t&& function_exists( 'wp_check_password' )\n\t\t\t&& wp_check_password( $password, (string) $fresh_user->user_pass, $user_id );\n\t\t$password = ''; $confirm = '';\n\t\tunset( $_POST['password'], $_POST['password_confirm'] );\n\t\tif ( ! $persisted ) {\n\t\t\t/* Credential state is uncertain. Revoke sessions and never emit a false\n\t\t\t * PasswordResetCompleted event or success message. */\n\t\t\tSAUTH_Session_Manager::revoke_user_sessions( $user_id, 'password_reset_postcondition_failed' );\n\t\t\tSA_Membership_Adapter::audit( 'password_reset_postcondition_failed', $user_id );\n\t\t\twp_safe_redirect( SA_Security::message_url( 'reset', 'error', 'The password change could not be confirmed. All sessions were revoked for safety; request a new reset link.' ) );\n\t\t\texit;\n\t\t}\n\t\tSAUTH_Session_Manager::revoke_user_sessions( $user_id, 'password_reset' );"
)
replace(
    'includes/class-sa-registration.php',
    "\t\tSAUTH_Event_Outbox::emit( 'PasswordResetCompleted.v1', $user->ID, $user->ID, array( 'all_sessions_revoked' => true, 'method' => 'email_reset' ), 'security' );\n\t\tSA_Membership_Adapter::audit( 'password_reset_completed', $user->ID );",
    "\t\tSAUTH_Event_Outbox::emit( 'PasswordResetCompleted.v1', $user_id, $user_id, array( 'all_sessions_revoked' => true, 'method' => 'email_reset' ), 'security' );\n\t\tSA_Membership_Adapter::audit( 'password_reset_completed', $user_id );"
)

# R303-C: completion-only sign-in is allowed only for an explicitly active,
# non-suspended membership. A generic deny without active membership evidence
# must never be overridden merely because a completion URL exists.
replace(
    'includes/class-sa-registration.php',
    "\tprivate static function sign_in_allowed( array $assertion, array $completion ) {\n\t\tif ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || ! empty( $assertion['membership']['suspended'] ) ) { return false; }\n\t\tif ( 'allow' === ( $assertion['result'] ?? '' ) ) { return true; }\n\t\treturn 'allow' === ( $completion['result'] ?? '' ) && ! empty( $completion['missing_steps'] ) && ! empty( $completion['next_route'] );\n\t}",
    "\tprivate static function sign_in_allowed( array $assertion, array $completion ) {\n\t\tif ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || ! empty( $assertion['membership']['suspended'] ) ) { return false; }\n\t\tif ( 'allow' === ( $assertion['result'] ?? '' ) ) { return true; }\n\t\t$active = true === ( $assertion['membership']['active'] ?? false );\n\t\treturn $active\n\t\t\t&& 'allow' === ( $completion['result'] ?? '' )\n\t\t\t&& ! empty( $completion['missing_steps'] )\n\t\t\t&& ! empty( $completion['next_route'] );\n\t}"
)

# R303-D: canonical page-map first; legacy map is fallback only.
replace(
    'templates/forgot-password.php',
    "<?php defined( 'ABSPATH' ) || exit; $pages = (array) get_option( 'sa_page_map', array() ); ?>",
    "<?php defined( 'ABSPATH' ) || exit; $pages = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) ); ?>"
)

# R303-E: client constraints match the server contract instead of rejecting
# otherwise-valid provider inputs before server validation.
replace('templates/signup.php', '<input id="sa-email" type="email" name="email" autocomplete="email" value=', '<input id="sa-email" type="email" name="email" autocomplete="email" maxlength="320" value=')
replace('templates/signup.php', '<input id="sa-phone" type="tel" name="phone" autocomplete="tel" inputmode="tel" required>', '<input id="sa-phone" type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="64" required>')
replace('templates/signup.php', '<input id="sa-country" type="text" name="country" autocomplete="country-name" required>', '<input id="sa-country" type="text" name="country" autocomplete="country-name" maxlength="120" required>')
replace('templates/signup.php', '<textarea id="sa-address" name="address" autocomplete="street-address" maxlength="500" required></textarea>', '<textarea id="sa-address" name="address" autocomplete="street-address" maxlength="1000" required></textarea>')
replace('templates/signup.php', '<input id="sa-identity-reference" type="text" name="identity_reference" autocomplete="off" spellcheck="false" minlength="5" maxlength="64" required>', '<input id="sa-identity-reference" type="text" name="identity_reference" autocomplete="off" spellcheck="false" minlength="5" maxlength="200" required>')
replace('templates/signup.php', '<input id="sa-guardian-reference" type="text" name="guardian_reference" autocomplete="off" maxlength="190">', '<input id="sa-guardian-reference" type="text" name="guardian_reference" autocomplete="off" maxlength="200">')
replace('templates/signup.php', '<input id="sa-password" type="password" name="password" autocomplete="new-password" minlength="12" required>', '<input id="sa-password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="4096" required>')
replace('templates/signup.php', '<input id="sa-password-confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="12" required>', '<input id="sa-password-confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="12" maxlength="4096" required>')
replace('templates/reset-password.php', '<input id="sa-new-password" name="password" type="password" minlength="12" autocomplete="new-password" required aria-describedby="sa-password-help">', '<input id="sa-new-password" name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required aria-describedby="sa-password-help">')
replace('templates/reset-password.php', '<input id="sa-confirm-password" name="password_confirm" type="password" minlength="12" autocomplete="new-password" required>', '<input id="sa-confirm-password" name="password_confirm" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required>')
replace('templates/forgot-password.php', '<input id="sa-user-login" name="user_login" type="text" autocomplete="username" required>', '<input id="sa-user-login" name="user_login" type="text" autocomplete="username" maxlength="320" required>')

# R303-F: verification should respect the already-open membership circuit before
# calling the owner contract. The challenge remains pending and retryable.
replace(
    'includes/class-sauth-email-verification.php',
    "\t\tif ( ! SAUTH_Account_Contract::provider_available() ) {\n\t\t\treturn new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );\n\t\t}",
    "\t\tif ( ! SAUTH_Account_Contract::provider_available() || ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {\n\t\t\treturn new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );\n\t\t}",
    count=1
)
replace(
    'includes/class-sauth-email-verification.php',
    "\t\tif ( SA_Security::rate_limited( 'email_verification_attempt', self::MAX_ATTEMPTS, HOUR_IN_SECONDS, (string) $user_id ) ) {",
    "\t\tif ( ! SAUTH_Account_Contract::provider_available() || ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {\n\t\t\treturn new WP_Error( 'sauth_email_provider_unavailable', 'Account verification is temporarily unavailable.' );\n\t\t}\n\t\tif ( SA_Security::rate_limited( 'email_verification_attempt', self::MAX_ATTEMPTS, HOUR_IN_SECONDS, (string) $user_id ) ) {"
)

# Update the existing policy fixture: only active memberships may enter a
# completion-only session after a non-allow capability assertion.
p = ROOT / 'tests/registration-authentication-unit.php'
text = p.read_text(encoding='utf-8')
text = text.replace("$deny_assertion = array( 'result' => 'deny', 'membership' => array( 'suspended' => false ) );", "$deny_assertion = array( 'result' => 'deny', 'membership' => array( 'active' => true, 'suspended' => false ) );\n$inactive_deny_assertion = array( 'result' => 'deny', 'membership' => array( 'active' => false, 'suspended' => false ) );", 1)
text = text.replace("sauth_registration_assert( true === $policy->invoke( null, $deny_assertion, $completion ), 'safe completion-only sign-in was denied' );", "sauth_registration_assert( true === $policy->invoke( null, $deny_assertion, $completion ), 'active completion-only sign-in was denied' );\nsauth_registration_assert( false === $policy->invoke( null, $inactive_deny_assertion, $completion ), 'inactive membership denial was overridden by completion routing' );", 1)
p.write_text(text, encoding='utf-8')

(ROOT / 'tests/r303-registration-recovery-regression.php').write_text(r'''<?php
$root = dirname( __DIR__ );
$registration = file_get_contents( $root . '/includes/class-sa-registration.php' );
$email = file_get_contents( $root . '/includes/class-sauth-email-verification.php' );
$forgot = file_get_contents( $root . '/templates/forgot-password.php' );
$signup = file_get_contents( $root . '/templates/signup.php' );
$reset = file_get_contents( $root . '/templates/reset-password.php' );
$fail = array();
$checks = array(
    array( $registration, "wp_check_password( $password, (string) $fresh_user->user_pass, $user_id )", 'reset persistence postcondition missing' ),
    array( $registration, 'password_reset_postcondition_failed', 'uncertain reset state is not contained' ),
    array( $registration, "true === ( $assertion['membership']['active'] ?? false )", 'completion-only login does not require active membership' ),
    array( $forgot, "get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) )", 'forgot-password template is not canonical-map first' ),
    array( $signup, 'maxlength="200" required', 'identity-reference client bound is stale' ),
    array( $signup, 'maxlength="1000" required', 'address client bound is stale' ),
    array( $reset, 'maxlength="4096"', 'reset client password bound missing' ),
    array( $email, "SAUTH_Provider_Health::allow_request( 'membership' )", 'email verification ignores membership circuit' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
$owner_call = strpos( $registration, 'SAUTH_Account_Contract::register_account(' );
$wipe = strpos( $registration, "$payload['password'] = '';", $owner_call === false ? 0 : $owner_call );
$delivery = strpos( $registration, 'SAUTH_Email_Verification::issue(', $owner_call === false ? 0 : $owner_call );
if ( false === $owner_call || false === $wipe || false === $delivery || $wipe >= $delivery ) { $fail[] = 'registration password is not wiped before downstream delivery'; }
if ( $fail ) { fwrite( STDERR, "R303 regression failures:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R303 registration/recovery regression PASS (' . ( count( $checks ) + 1 ) . " assertions).\n";
''', encoding='utf-8')
print('R303 corrections staged.')
