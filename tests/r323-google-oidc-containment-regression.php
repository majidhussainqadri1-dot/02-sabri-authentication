<?php
$root = dirname( __DIR__ );
$google = file_get_contents( $root . '/includes/class-sa-google-oauth.php' );
$registration = file_get_contents( $root . '/includes/class-sauth-google-registration.php' );
$fail = array();
if ( false !== strpos( $registration, 'rawurlencode( $registration_token )' ) ) { $fail[] = 'Google registration continuation is pre-encoded'; }
foreach ( array( $google, $registration ) as $i => $text ) {
    if ( false === strpos( $text, 'return setcookie( self::COOKIE_NAME, $value, $options );' ) ) { $fail[] = 'state cookie result not returned in surface ' . $i; }
}
if ( false === strpos( $google, 'if ( ! $this->state_cookie( $state, time() + self::STATE_TTL ) )' ) ) { $fail[] = 'Google OAuth start does not require state-cookie persistence'; }
if ( false === strpos( $registration, 'if ( ! self::state_cookie( $state, time() + self::STATE_TTL ) )' ) ) { $fail[] = 'Google registration start does not require state-cookie persistence'; }
if ( false === strpos( $google, "SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'google_linkage_containment' )" ) ) { $fail[] = 'linkage containment bypasses verified session revocation'; }
if ( false === strpos( $google, 'SAUTH_Operations::enter_safe_mode();' ) ) { $fail[] = 'linkage containment bypasses central Safe Mode authority'; }
if ( false !== strpos( $google, "update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false )" ) ) { $fail[] = 'Google linkage still writes Safe Mode directly'; }
if ( $fail ) { fwrite( STDERR, "R323 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R323 Google OIDC containment regression PASS (8 assertions).' . PHP_EOL;
