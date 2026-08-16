<?php
/** Permanent no-network regression for the File 00 canonical managed-page contract. */
$root = dirname( __DIR__ );
$adapter_source = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) {
	if ( ! $ok ) { $fail[] = $message; }
};

foreach ( array(
	"MEMBERSHIP_APPLICATION_KEY  = 'application'",
	"MEMBERSHIP_APPLICATION_PATH = '/membership-application/'",
	"MEMBERSHIP_SECURITY_KEY     = 'security'",
	"MEMBERSHIP_SECURITY_PATH    = '/membership-security/'",
	"MEMBERSHIP_STATUS_KEY       = 'status'",
	"MEMBERSHIP_STATUS_PATH      = '/membership-status/'",
) as $marker ) {
	$req( false !== strpos( $adapter_source, $marker ), 'canonical File 00 route marker missing: ' . $marker );
}
foreach ( array( 'sabri_profile', 'sabri_security_center', 'sabri_verification_status', '/sabri-profile/', '/sabri-security-center/', '/sabri-verification-status/' ) as $forbidden ) {
	$req( false === strpos( $adapter_source, $forbidden ), 'invented non-File00 membership route remains: ' . $forbidden );
}

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
if ( ! defined( 'SMC_VERSION' ) ) { define( 'SMC_VERSION', '1.2.43' ); }
if ( ! defined( 'SMC_DB_VERSION' ) ) { define( 'SMC_DB_VERSION', '1.4.5' ); }
if ( ! defined( 'SMC_CF01_CONTRACT_VERSION' ) ) { define( 'SMC_CF01_CONTRACT_VERSION', '1.1.0' ); }
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = '' ) { return 'smc_db_version' === $key ? '1.4.5' : $default; }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) { return 'https://example.test/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'smc_page_url' ) ) {
	function smc_page_url( $key, $fallback = '/' ) {
		$GLOBALS['r339_keys'][] = (string) $key;
		return home_url( $fallback );
	}
}
if ( ! function_exists( 'smc_user_status' ) ) {
	function smc_user_status( $user_id ) { return 'approved'; }
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
}
if ( ! class_exists( 'SMC_Security' ) ) { final class SMC_Security {} }
if ( ! class_exists( 'SMC_Completion' ) ) { final class SMC_Completion { public static function safe_mode() { return false; } } }
if ( ! class_exists( 'SMC_CF01_Contract' ) ) {
	final class SMC_CF01_Contract {
		public static function membership_assertion( $user_id, $context = array() ) { return array( 'result' => 'allow' ); }
	}
}

require_once $root . '/includes/class-sa-membership-adapter.php';
$GLOBALS['r339_keys'] = array();
$req( 'https://example.test/membership-application/' === SA_Membership_Adapter::profile_url(), 'profile_url does not resolve through File 00 application route' );
$req( 'https://example.test/membership-security/' === SA_Membership_Adapter::security_url(), 'security_url does not resolve through File 00 security route' );
$req( 'https://example.test/membership-status/' === SA_Membership_Adapter::verification_url(), 'verification_url does not resolve through File 00 status route' );
$req( array( 'application', 'security', 'application', 'status' ) === $GLOBALS['r339_keys'], 'File 02 did not ask File 00 for the exact canonical page keys' );

if ( $fail ) {
	fwrite( STDERR, "R339 File 00 route-contract regressions:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}
echo 'R339 File 00 canonical route contract PASS (16 assertions).' . PHP_EOL;
