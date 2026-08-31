<?php
/** R343 permanent regression for the live-proven draft-account sign-in deadlock. */
$root     = dirname( __DIR__ );
$adapter  = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$register = file_get_contents( $root . '/includes/class-sa-registration.php' );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$runtime  = file_get_contents( $root . '/includes/class-sauth-passkey-runtime.php' );
$google   = file_get_contents( $root . '/includes/class-sa-google-oauth.php' );
$fail     = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };
$req( false !== strpos( $adapter, 'public static function sign_in_allowed( array $assertion, array $completion )' ), 'central sign-in admission helper missing' );
$req( false !== strpos( $adapter, "'unknown' === \$result" ), 'unknown membership no longer fail-closed' );
$req( false !== strpos( $adapter, "membership']['suspended']" ), 'suspended membership guard missing' );
$req( false !== strpos( $adapter, "'membership_prerequisite_denied'" ), 'completion admission is not constrained to prerequisite denial' );
$req( false !== strpos( $adapter, "'allow' === ( \$completion['result'] ?? '' )" ), 'completion result must be canonical allow' );
$req( false !== strpos( $adapter, "! empty( \$completion['missing_steps'] )" ) && false !== strpos( $adapter, "! empty( \$completion['next_route'] )" ), 'completion admission requires missing steps and canonical route' );
foreach ( array( $register, $passkeys, $runtime ) as $source ) {
    $req( false !== strpos( $source, 'SA_Membership_Adapter::sign_in_allowed( $assertion, $completion )' ), 'authentication surface does not delegate to central completion-aware admission' );
}
$req( false !== strpos( $google, "\$membership = SA_Membership_Adapter::membership_assertion( \$locked_user->ID, 'clinical_identity_link', 'google_sign_in' );" ), 'Google sign-in does not obtain the same canonical membership assertion' );
$req( false !== strpos( $google, 'SA_Membership_Adapter::sign_in_allowed( $membership, $completion )' ), 'Google sign-in bypasses completion-aware admission' );
$req( false === strpos( $google, '|| ! SA_Membership_Adapter::can_use_google( $locked_user->ID ) )' ), 'Google sign-in still requires fully eligible membership before completion' );
if ( $fail ) { fwrite( STDERR, "R343 draft completion login regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R343 draft completion login PASS.' . PHP_EOL;
