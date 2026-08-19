<?php
/**
 * R340 permanent regression for the live-proven File 00 <-> File 02
 * passkey-assurance circular dependency.
 */

$root      = dirname( __DIR__ );
$runtime   = file_get_contents( $root . '/includes/class-sauth-passkey-runtime.php' );
$google    = file_get_contents( $root . '/includes/class-sa-google-oauth.php' );
$contracts = file_get_contents( $root . '/CONTRACTS.md' );
$fail      = array();

$req = static function ( $ok, $message ) use ( &$fail ) {
	if ( ! $ok ) {
		$fail[] = $message;
	}
};

$start_marker = 'public static function current_assurance( $user_id ) {';
$start        = strpos( $runtime, $start_marker );
$req( false !== $start, 'current_assurance method is missing' );

$method = '';
if ( false !== $start ) {
	$brace  = strpos( $runtime, '{', $start );
	$depth  = 0;
	$length = strlen( $runtime );
	for ( $i = $brace; false !== $brace && $i < $length; $i++ ) {
		if ( '{' === $runtime[ $i ] ) {
			$depth++;
		} elseif ( '}' === $runtime[ $i ] ) {
			$depth--;
			if ( 0 === $depth ) {
				$method = substr( $runtime, $start, $i - $start + 1 );
				break;
			}
		}
	}
}

$req( '' !== $method, 'current_assurance body could not be isolated' );
$req( false !== strpos( $method, 'get_transient( self::session_assurance_key( $user_id, $token ) )' ), 'session-bound receipt lookup missing' );
$req( false !== strpos( $method, 'self::valid_session_receipt( $receipt, $user_id, $token )' ), 'receipt validity gate missing' );
$req( false === strpos( $method, 'SA_Membership_Adapter::membership_assertion' ), 'current_assurance must not re-enter File 00 membership authorization' );
$req( false === strpos( $method, 'delete_transient( self::session_assurance_key( $user_id, $token ) )' ), 'pure assurance projection must not delete a valid receipt because an external authorization provider cannot complete' );
$req( false !== strpos( $method, "'owner' => 'file02'" ), 'File 02 assurance ownership marker missing' );
$req( false !== strpos( $method, "'passkey_asserted' => true" ), 'passkey assertion projection missing' );

$membership_gate = strpos( $google, 'SA_Membership_Adapter::can_use_google( get_current_user_id() )' );
$passkey_gate    = strpos( $google, 'self::fresh_passkey( get_current_user_id() )' );
$req( false !== $membership_gate, 'Google link membership authorization gate missing' );
$req( false !== $passkey_gate, 'Google link fresh passkey gate missing' );
$req( false !== $membership_gate && false !== $passkey_gate && $membership_gate < $passkey_gate, 'Google link must authorize membership before requiring fresh passkey assurance' );
$req( false !== strpos( $contracts, "It never authorizes the consumer's native object or action." ), 'authentication-assurance authorization boundary is undocumented' );

if ( $fail ) {
	fwrite( STDERR, "R340 passkey assurance cycle regressions:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}

echo 'R340 passkey assurance cycle PASS (12 assertions).' . PHP_EOL;
