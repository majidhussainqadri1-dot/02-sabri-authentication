<?php
/**
 * V12.3 disposable WordPress integration:
 * An unaccepted or stale account-safety policy MUST reject before File00
 * can create a canonical account, for password and Google-token entry.
 *
 * Run as a fresh `wp eval-file` process with SAUTH_TEST_FLOW=password|google
 * and SAUTH_TEST_VARIANT=missing|stale|array. wp_safe_redirect() terminates
 * the registration handler; the wp_redirect filter proves the exact notice.
 *
 * This runs ONLY in disposable CI, never against a deployed site.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'SA_Registration' ) ) {
    fwrite( STDERR, "FAIL account safety: WordPress registration runtime absent\n" );
    exit( 1 );
}
$flow = getenv( 'SAUTH_TEST_FLOW' );
$variant = getenv( 'SAUTH_TEST_VARIANT' );
if ( ! in_array( $flow, array( 'password', 'google' ), true )
    || ! in_array( $variant, array( 'missing', 'stale', 'array' ), true ) ) {
    fwrite( STDERR, "FAIL account safety: invalid fixture selection\n" );
    exit( 1 );
}
wp_set_current_user( 0 );
$account_email = 'negative-' . $flow . '-' . $variant . '@invalid.example';
if ( get_user_by( 'email', $account_email ) ) {
    fwrite( STDERR, "FAIL account safety: fixture account already exists\n" );
    exit( 1 );
}
$_POST = array(
    'sa_nonce'                 => wp_create_nonce( 'sa_register' ),
    'name'                     => 'Disposable Negative Fixture',
    'email'                    => $account_email,
    'phone'                    => '+15551234567',
    'google_registration_token'=> 'google' === $flow ? 'not-a-valid-registration-token' : '',
    'accept_security_rules'    => 'missing' === $variant ? '' : '1',
    'security_policy_version'  => 'stale' === $variant ? '1900-01-01' : SAUTH_Account_Safety::POLICY_VERSION,
);
if ( 'array' === $variant ) {
    $_POST['accept_security_rules'] = array( '1' );
}
$before = (int) count_users()['total_users'];
$seen_redirect = false;
add_filter( 'wp_redirect', function( $location ) use ( &$seen_redirect, $account_email, $before, $flow, $variant ) {
    $seen_redirect = true;
    $query = wp_parse_url( (string) $location, PHP_URL_QUERY );
    $args = array();
    parse_str( is_string( $query ) ? $query : '', $args );
    $expected_message = 'Read and acknowledge the mandatory account safety rules before registering.';
    if ( ( $args['sa_notice'] ?? '' ) !== 'error'
        || ( $args['sa_msg'] ?? '' ) !== $expected_message
        || ! SA_Security::notice_valid(
            (string) ( $args['sa_notice'] ?? '' ),
            (string) ( $args['sa_msg'] ?? '' ),
            (string) ( $args['sa_sig'] ?? '' ),
            (int) ( $args['sa_iat'] ?? 0 )
        )
        || get_user_by( 'email', $account_email )
        || (int) count_users()['total_users'] !== $before ) {
        fwrite( STDERR, 'FAIL account safety: admission bypass or wrong signed denial ' . $flow . '/' . $variant . "\n" );
        exit( 1 );
    }
    echo 'PASS V12.3 negative registration ' . $flow . '/' . $variant . ' rejected before account creation with signed notice' . "\n";
    return $location;
}, 10, 1 );
( new SA_Registration() )->register();
/* register() must terminate through its redirect, never return. */
fwrite( STDERR, 'FAIL account safety: register returned without safe denial: '
    . $flow . '/' . $variant . ' redirect_seen=' . ( $seen_redirect ? 'yes' : 'no' ) . "\n" );
exit( 1 );
