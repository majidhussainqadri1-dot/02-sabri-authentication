<?php
/**
 * No-network policy tests for F02-FR-007 through F02-FR-012.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'SA_VERSION', '1.0.0' );

$GLOBALS['sauth_test_transients'] = array();
$GLOBALS['sauth_test_pages'] = array(
	'login'          => 'https://example.test/login/',
	'signup'         => 'https://example.test/register/',
	'forgot'         => 'https://example.test/forgot-password/',
	'reset'          => 'https://example.test/reset-password/',
	'risk_challenge' => 'https://example.test/confirm-sign-in/',
);

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_User { public $ID = 1; public $user_login = 'test'; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_salt( $scheme = 'auth' ) { return hash( 'sha256', 'file02-complete-test|' . $scheme ); }
function home_url( $path = '/' ) { return 'https://example.test' . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_validate_redirect( $url, $fallback = '' ) {
	$url = trim( (string) $url );
	if ( '' === $url ) { return $fallback; }
	$host = parse_url( $url, PHP_URL_HOST );
	return 'example.test' === $host ? $url : $fallback;
}
function untrailingslashit( $value ) { return rtrim( (string) $value, '/' ); }
function set_transient( $key, $value, $ttl ) { $GLOBALS['sauth_test_transients'][ $key ] = array( 'value' => $value, 'expires' => time() + $ttl ); return true; }
function get_transient( $key ) {
	if ( ! isset( $GLOBALS['sauth_test_transients'][ $key ] ) ) { return false; }
	$record = $GLOBALS['sauth_test_transients'][ $key ];
	return $record['expires'] > time() ? $record['value'] : false;
}
function delete_transient( $key ) { unset( $GLOBALS['sauth_test_transients'][ $key ] ); return true; }
function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }

class SA_Security {
	public static function client_fingerprint() { return str_repeat( 'a', 64 ); }
	public static function safe_redirect( $url, $fallback = '' ) { return wp_validate_redirect( $url, $fallback ?: home_url( '/' ) ); }
	public static function page_url( $key, $fallback = '' ) { return $GLOBALS['sauth_test_pages'][ $key ] ?? $fallback; }
}
class SA_Membership_Adapter {
	public static function profile_url() { return 'https://example.test/sabri-profile/'; }
}
class SAUTH_Account_Contract {
	public static $state = array();
	public static function completion_state( $user_id, $context = array() ) { return self::$state; }
}

function sauth_complete_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-sauth-completion-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-sauth-provider-health.php';
require_once dirname( __DIR__ ) . '/includes/class-sauth-login-risk.php';
require_once dirname( __DIR__ ) . '/includes/class-sauth-session-manager.php';

SAUTH_Account_Contract::$state = array( 'result' => 'allow', 'missing_steps' => array(), 'next_route' => '' );
$resolved = SAUTH_Completion_Resolver::resolve( 7, 'https://example.test/home/' );
sauth_complete_assert( 'allow' === $resolved['result'] && 'completion_not_required' === $resolved['reason_code'], 'complete account was not routed to the intended destination' );

SAUTH_Account_Contract::$state = array( 'result' => 'allow', 'missing_steps' => array( 'guardian', 'profile' ), 'next_route' => 'https://example.test/sabri-profile/' );
$first = SAUTH_Completion_Resolver::resolve( 7, 'https://example.test/home/' );
$second = SAUTH_Completion_Resolver::resolve( 7, 'https://example.test/home/' );
$third = SAUTH_Completion_Resolver::resolve( 7, 'https://example.test/home/' );
sauth_complete_assert( 'completion_required' === $first['reason_code'], 'valid completion route was rejected' );
sauth_complete_assert( 'completion_required' === $second['reason_code'], 'second resumable completion visit was blocked too early' );
sauth_complete_assert( 'completion_loop_prevented' === $third['reason_code'], 'repeated account-completion loop was not prevented' );

SAUTH_Account_Contract::$state = array( 'result' => 'allow', 'missing_steps' => array( 'terms' ), 'next_route' => 'https://evil.test/collect/' );
$external = SAUTH_Completion_Resolver::resolve( 8, 'https://example.test/home/' );
sauth_complete_assert( 'unknown' === $external['result'] && 'completion_route_invalid' === $external['reason_code'], 'external completion route was accepted' );

SAUTH_Account_Contract::$state = array( 'result' => 'allow', 'missing_steps' => array( 'profile' ), 'next_route' => 'https://example.test/login/' );
$auth_loop = SAUTH_Completion_Resolver::resolve( 9, 'https://example.test/home/' );
sauth_complete_assert( 'completion_route_invalid' === $auth_loop['reason_code'], 'login route was accepted as completion destination' );

sauth_complete_assert( SAUTH_Completion_Resolver::is_completion_step( 'guardian' ), 'guardian completion step was not recognized' );
sauth_complete_assert( ! SAUTH_Completion_Resolver::is_completion_step( 'administrator' ), 'unsupported completion step was recognized' );

SAUTH_Provider_Health::record_failure( 'google', 'timeout', 15001 );
SAUTH_Provider_Health::record_failure( 'google', 'timeout', 15002 );
SAUTH_Provider_Health::record_failure( 'google', 'timeout', 15003 );
sauth_complete_assert( SAUTH_Provider_Health::allow_request( 'google' ), 'provider circuit opened before threshold' );
SAUTH_Provider_Health::record_failure( 'google', 'timeout', 15004 );
$open = SAUTH_Provider_Health::state( 'google' );
sauth_complete_assert( 'open' === $open['status'] && ! SAUTH_Provider_Health::allow_request( 'google' ), 'provider circuit did not open at threshold' );
sauth_complete_assert( 15004 === $open['latency_ms'], 'provider latency was not retained within bounds' );
SAUTH_Provider_Health::record_success( 'google', 42 );
$healthy = SAUTH_Provider_Health::state( 'google' );
sauth_complete_assert( 'healthy' === $healthy['status'] && SAUTH_Provider_Health::allow_request( 'google' ), 'provider success did not close the circuit' );
sauth_complete_assert( 42 === $healthy['latency_ms'], 'provider success latency was not recorded' );

$risk_band = new ReflectionMethod( 'SAUTH_Login_Risk', 'risk_band' );
$risk_band->setAccessible( true );
sauth_complete_assert( 'low' === $risk_band->invoke( null, 0 ), 'low risk band failed' );
sauth_complete_assert( 'medium' === $risk_band->invoke( null, 45 ), 'medium risk threshold failed' );
sauth_complete_assert( 'high' === $risk_band->invoke( null, 80 ), 'high risk threshold failed' );

$device_label = new ReflectionMethod( 'SAUTH_Login_Risk', 'device_label' );
$device_label->setAccessible( true );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Mobile Firefox/125.0';
sauth_complete_assert( 'Firefox on a mobile device' === $device_label->invoke( null ), 'risk device label was not generalized' );

$session_ua = new ReflectionMethod( 'SAUTH_Session_Manager', 'generalize_user_agent' );
$session_ua->setAccessible( true );
sauth_complete_assert( 'Chrome on a computer' === $session_ua->invoke( null, 'Mozilla Chrome/125.0' ), 'session user-agent projection was not generalized' );

$session_ip = new ReflectionMethod( 'SAUTH_Session_Manager', 'generalize_ip' );
$session_ip->setAccessible( true );
sauth_complete_assert( '203.0.x.x' === $session_ip->invoke( null, '203.0.113.44' ), 'IPv4 session network was not minimized' );
sauth_complete_assert( false === strpos( $session_ip->invoke( null, '2001:db8:abcd::1' ), 'abcd' ), 'IPv6 session network exposed excessive precision' );

$token_hash = new ReflectionMethod( 'SAUTH_Session_Manager', 'token_hash' );
$token_hash->setAccessible( true );
$hash = $token_hash->invoke( null, 'raw-session-token' );
sauth_complete_assert( 64 === strlen( $hash ) && 'raw-session-token' !== $hash, 'session registry did not use a one-way token binding' );

echo "File 02 completion, risk, session and provider policy checks passed.\n";
