<?php
$root = dirname( __DIR__ );
$session = file_get_contents( $root . '/includes/class-sauth-session-manager.php' );
$risk = file_get_contents( $root . '/includes/class-sauth-login-risk.php' );
$passkey = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$assurance = file_get_contents( $root . '/includes/class-sa-authentication-assurance.php' );
$professional = file_get_contents( $root . '/includes/class-sa-professional-reauthentication.php' );
$fail = array();
$checks = array(
    array( $session, 'session_registry_read_failed', 'session registry read failure still fails open' ),
    array( $session, '\'active\' === (string) $status ? $user_id : 0', 'unknown/revoked session state can remain authenticated' ),
    array( $session, 'WP_Session_Tokens::get_instance( absint( $user_id ) )->get( $token )', 'lazy session projection ignores real WordPress expiration' ),
    array( $session, 'SET status=\'revoked\', revoked_at=%s, revocation_reason=%s', 'revoke-others still depends on display list limit' ),
    array( $passkey, 'public static function authentication_ready()', 'risk engine lacks passkey readiness contract' ),
    array( $risk, 'strong_authentication_unavailable', 'passkey storage outage can downgrade medium-risk login' ),
    array( $assurance, 'SAUTH_PASSKEY_CONTRACT_VERSION !== (string) ( $passkey[\'contract_version\'] ?? \'\' )', 'derived assurance does not bind passkey contract' ),
    array( $professional, 'password_binding', 'professional receipt is not bound to current password state' ),
    array( $professional, 'unset( $receipt[\'session_binding\'], $receipt[\'fingerprint\'], $receipt[\'password_binding\'] )', 'password binding leaks from public professional receipt' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
if ( $fail ) { fwrite( STDERR, "R306 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R306 session/assurance regression PASS (' . count( $checks ) . " assertions).\n";
