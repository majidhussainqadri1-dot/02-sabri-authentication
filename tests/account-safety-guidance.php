<?php
/** File 02 permanent account safety regression, standalone PHP 7.4+. */
define( 'ABSPATH', __DIR__ );
function is_user_logged_in() { return ! empty( $GLOBALS['logged_in'] ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
class SA_Membership_Adapter {
    public static function login_url( $target = '' ) { return '/login?next=' . rawurlencode( $target ); }
    public static function security_url() { return '/membership-security/'; }
}
class SA_Security { public static function page_url( $key ) { return '/account/' . $key . '/'; } }
class SAUTH_Passkeys { public static function manager_url() { return '/account-passkeys/'; } }
require_once dirname( __DIR__ ) . '/includes/class-sauth-account-safety.php';
function must( $value, $reason ) { if ( ! $value ) { fwrite( STDERR, 'FAIL: ' . $reason . PHP_EOL ); exit( 1 ); } }
must( ! SAUTH_Account_Safety::registration_acknowledged( array() ), 'missing acknowledgement blocked' );
must( ! SAUTH_Account_Safety::registration_acknowledged( array( 'accept_security_rules' => '0' ) ), 'false acknowledgement blocked' );
must( ! SAUTH_Account_Safety::registration_acknowledged( array( 'accept_security_rules' => array( '1' ) ) ), 'array injection blocked' );
must( SAUTH_Account_Safety::registration_acknowledged( array( 'accept_security_rules' => '1' ) ), 'explicit acknowledgement accepted' );
$GLOBALS['logged_in'] = false;
must( false === strpos( SAUTH_Account_Safety::render(), '/membership-security/' ), 'anonymous private settings hidden' );
$GLOBALS['logged_in'] = true;
$html = SAUTH_Account_Safety::render();
must( false !== strpos( $html, '/account/sessions/' ) && false !== strpos( $html, '/account-passkeys/' ), 'user security actions exist' );
$root = dirname( __DIR__ );
must( false !== strpos( file_get_contents( $root . '/includes/class-sa-registration.php' ), 'SAUTH_Account_Safety::registration_acknowledged( $_POST )' ), 'server-side register gate' );
must( false !== strpos( file_get_contents( $root . '/templates/signup.php' ), 'name="accept_security_rules" value="1" required' ), 'required form checkbox' );
must( false !== strpos( file_get_contents( $root . '/includes/class-sauth-canonical-routes.php' ), "'^account/safety/?$'" ), 'canonical route' );
echo 'PASS: account-safety server gate, markup, route and private boundary' . PHP_EOL;
