#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

def rep(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    n = text.count(old)
    if n != count:
        raise SystemExit(f'{path}: expected {count}, found {n}: {old[:160]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

# R306-A: a registry read failure/unknown state cannot fail open for a token
# that may have been revoked. A genuine no-row legacy session remains eligible
# for the explicit lazy-reconciliation path.
rep(
    'includes/class-sauth-session-manager.php',
    "\t\t$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE user_id=%d AND token_hash=%s', $user_id, self::token_hash( $token ) ) );\n\t\treturn 'revoked' === $status || 'expired' === $status ? 0 : $user_id;",
    "\t\t$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE user_id=%d AND token_hash=%s', $user_id, self::token_hash( $token ) ) );\n\t\tif ( '' !== (string) $wpdb->last_error ) {\n\t\t\tSA_Membership_Adapter::audit( 'session_registry_read_failed', $user_id );\n\t\t\treturn 0;\n\t\t}\n\t\tif ( null === $status ) {\n\t\t\treturn $user_id; // Legitimate pre-registry/upgrade session; reconciled on init.\n\t\t}\n\t\treturn 'active' === (string) $status ? $user_id : 0;"
)

# R306-B: lazy reconciliation must inherit the real WordPress session expiry,
# never synthesize a one-day projection that can prematurely log out a valid
# remembered session.
rep(
    'includes/class-sauth-session-manager.php',
    "\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d AND token_hash=%s', absint( $user_id ), $hash ) );\n\t\tif ( 0 === (int) $exists ) { self::register_cookie( '', time() + DAY_IN_SECONDS, time() + DAY_IN_SECONDS, $user_id, 'logged_in', $token ); }",
    "\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d AND token_hash=%s', absint( $user_id ), $hash ) );\n\t\tif ( '' !== (string) $wpdb->last_error ) { return; }\n\t\tif ( 0 === (int) $exists ) {\n\t\t\t$expiration = time() + YEAR_IN_SECONDS;\n\t\t\tif ( class_exists( 'WP_Session_Tokens' ) ) {\n\t\t\t\t$session = WP_Session_Tokens::get_instance( absint( $user_id ) )->get( $token );\n\t\t\t\tif ( is_array( $session ) && absint( $session['expiration'] ?? 0 ) > time() ) {\n\t\t\t\t\t$expiration = absint( $session['expiration'] );\n\t\t\t\t}\n\t\t\t}\n\t\t\tself::register_cookie( '', $expiration, $expiration, $user_id, 'logged_in', $token );\n\t\t}"
)

# R306-C: revoke-others must reconcile every projection, not only the first
# MAX_LIST rows, after WordPress destroys every other session.
rep(
    'includes/class-sauth-session-manager.php',
    "\t\t$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT public_id FROM ' . self::table() . \" WHERE user_id=%d AND status='active' AND token_hash<>%s\", $user_id, $current ) );\n\t\t$ids = is_array( $rows ) ? $rows : array();\n\t\tself::mark_revoked( $user_id, $ids, 'user_revoke_others' );\n\t\tWP_Session_Tokens::get_instance( $user_id )->destroy_others( $token );\n\t\t$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . \" WHERE user_id=%d AND status='active' AND token_hash<>%s\", $user_id, $current ) );\n\t\tif ( $remaining > 0 ) { self::redirect( 'error', 'Other WordPress sessions were revoked, but session evidence could not be reconciled completely. Reload and review again.' ); }",
    "\t\t$now = current_time( 'mysql', true );\n\t\t$db_result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . \" SET status='revoked', revoked_at=%s, revocation_reason=%s, updated_at=%s WHERE user_id=%d AND status='active' AND token_hash<>%s\", $now, 'user_revoke_others', $now, $user_id, $current ) );\n\t\tWP_Session_Tokens::get_instance( $user_id )->destroy_others( $token );\n\t\t$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . \" WHERE user_id=%d AND status='active' AND token_hash<>%s\", $user_id, $current ) );\n\t\tif ( false === $db_result || '' !== (string) $wpdb->last_error || $remaining > 0 ) { self::redirect( 'error', 'Other WordPress sessions were revoked, but session evidence could not be reconciled completely. Reload and review again.' ); }"
)

# R306-D: expose a read-only readiness projection for the risk engine.
rep(
    'includes/class-sauth-passkeys.php',
    "\tprivate static function environment_ready() {",
    "\tpublic static function authentication_ready() {\n\t\treturn self::environment_ready();\n\t}\n\n\tprivate static function environment_ready() {"
)

# R306-E: passkey-storage outage must not masquerade as 'user has no passkey'
# and thereby downgrade a medium/high-risk password or Google sign-in.
rep(
    'includes/class-sauth-login-risk.php',
    "\t\tif ( $score < self::CHALLENGE_THRESHOLD ) { return array( 'action' => 'allow', 'score' => $score, 'reason_code' => $reason ); }\n\t\tif ( self::has_active_passkey( $user_id ) ) { return array( 'action' => 'challenge', 'score' => $score, 'reason_code' => $reason ); }\n\t\t/* A first/new network alone may produce medium risk before a user has ever\n\t\t * enrolled a passkey. Keep that path usable but never allow high risk. */",
    "\t\tif ( $score < self::CHALLENGE_THRESHOLD ) { return array( 'action' => 'allow', 'score' => $score, 'reason_code' => $reason ); }\n\t\t$passkey_ready = class_exists( 'SAUTH_Passkeys' )\n\t\t\t&& is_callable( array( 'SAUTH_Passkeys', 'authentication_ready' ) )\n\t\t\t&& SAUTH_Passkeys::authentication_ready();\n\t\tif ( ! $passkey_ready ) {\n\t\t\treturn array( 'action' => 'deny', 'score' => max( self::HIGH_RISK_THRESHOLD, $score ), 'reason_code' => 'strong_authentication_unavailable' );\n\t\t}\n\t\tif ( self::has_active_passkey( $user_id ) ) { return array( 'action' => 'challenge', 'score' => $score, 'reason_code' => $reason ); }\n\t\t/* A first/new network alone may produce medium risk before a user has ever\n\t\t * enrolled a passkey. Keep that path usable but never allow high risk. */"
)

# R306-F: derived authentication assurance must re-check the exact current
# passkey contract/method before remaining valid.
rep(
    'includes/class-sa-authentication-assurance.php',
    "\t\tif ( empty( $passkey['passkey_asserted'] ) || 'file02' !== (string) ( $passkey['owner'] ?? '' ) ) {",
    "\t\tif ( empty( $passkey['passkey_asserted'] )\n\t\t\t|| 'file02' !== (string) ( $passkey['owner'] ?? '' )\n\t\t\t|| 'webauthn_passkey' !== (string) ( $passkey['method'] ?? '' )\n\t\t\t|| ! defined( 'SAUTH_PASSKEY_CONTRACT_VERSION' )\n\t\t\t|| SAUTH_PASSKEY_CONTRACT_VERSION !== (string) ( $passkey['contract_version'] ?? '' ) ) {"
)

# R306-G: professional reauth must die with a password change even if a caller
# changes the password without rotating the current WordPress session.
rep(
    'includes/class-sa-professional-reauthentication.php',
    "\t\t$receipt['fingerprint'] = SA_Security::client_fingerprint();\n\t\t$receipt['password_verified'] = true;",
    "\t\t$receipt['fingerprint'] = SA_Security::client_fingerprint();\n\t\t$receipt['password_verified'] = true;\n\t\t$receipt['password_binding'] = self::password_binding( $user_id );\n\t\tif ( '' === $receipt['password_binding'] ) { return false; }"
)
rep(
    'includes/class-sa-professional-reauthentication.php',
    "\t\tif ( ! hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) )\n\t\t\t|| ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )\n\t\t\t|| ! hash_equals( self::scope_hash( $scope ), (string) ( $receipt['scope_hash'] ?? '' ) ) ) {",
    "\t\tif ( ! hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) )\n\t\t\t|| ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )\n\t\t\t|| ! hash_equals( self::scope_hash( $scope ), (string) ( $receipt['scope_hash'] ?? '' ) )\n\t\t\t|| '' === self::password_binding( $user_id )\n\t\t\t|| ! hash_equals( (string) ( $receipt['password_binding'] ?? '' ), self::password_binding( $user_id ) ) ) {"
)
rep(
    'includes/class-sa-professional-reauthentication.php',
    "\tprivate static function public_receipt( $receipt ) {\n\t\tunset( $receipt['session_binding'], $receipt['fingerprint'] );",
    "\tprivate static function public_receipt( $receipt ) {\n\t\tunset( $receipt['session_binding'], $receipt['fingerprint'], $receipt['password_binding'] );"
)
insert = "\n\tprivate static function write_transient_verified( $key, $value, $ttl ) {"
helper = r'''

	private static function password_binding( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user instanceof WP_User || '' === (string) $user->user_pass ) { return ''; }
		return hash_hmac( 'sha256', (string) $user->user_pass, wp_salt( 'auth' ) );
	}
'''
p = ROOT / 'includes/class-sa-professional-reauthentication.php'
text = p.read_text(encoding='utf-8')
if insert not in text:
    raise SystemExit('professional helper insertion point missing')
p.write_text(text.replace(insert, helper + insert, 1), encoding='utf-8')

print('R306 corrections staged.')
