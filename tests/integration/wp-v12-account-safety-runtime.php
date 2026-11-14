<?php
/**
 * V12.3 P05/P07/P08: real disposable WordPress File02 Account Safety
 * integration test. Executes after File00 and File02 activation.
 *
 * This is NOT a browser, staging, provider or production test.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'SAUTH_VERSION' ) ) {
    fwrite( STDERR, "FAIL: File02 WordPress runtime not loaded\n" );
    exit( 1 );
}
function file02_safety_runtime_must( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: File02 account safety: " . $message . "\n" );
        exit( 1 );
    }
}
file02_safety_runtime_must(
    class_exists( 'SAUTH_Account_Safety' ) &&
    class_exists( 'SAUTH_Canonical_Routes' ) &&
    shortcode_exists( 'sabri_auth_account_safety' ),
    'account safety owner classes and registered shortcode'
);
file02_safety_runtime_must(
    '2026-11-10' === SAUTH_Account_Safety::POLICY_VERSION,
    'current version identity'
);
$cases = array(
    array( array(), false ),
    array( array( 'accept_security_rules' => '1' ), false ),
    array( array( 'accept_security_rules' => '1', 'security_policy_version' => '2026-01-01' ), false ),
    array( array( 'accept_security_rules' => array( '1' ), 'security_policy_version' => SAUTH_Account_Safety::POLICY_VERSION ), false ),
    array( array( 'accept_security_rules' => '1', 'security_policy_version' => array( SAUTH_Account_Safety::POLICY_VERSION ) ), false ),
    array( array( 'accept_security_rules' => '0', 'security_policy_version' => SAUTH_Account_Safety::POLICY_VERSION ), false ),
    array( array( 'accept_security_rules' => '1', 'security_policy_version' => SAUTH_Account_Safety::POLICY_VERSION ), true ),
);
foreach ( $cases as $index => $test ) {
    $actual = SAUTH_Account_Safety::registration_acknowledged( $test[0] );
    file02_safety_runtime_must( $actual === $test[1], 'versioned registration case ' . $index );
}
$path = home_url( '/account/safety/' );
file02_safety_runtime_must(
    $path === SAUTH_Canonical_Routes::route_url( '', 'safety' ) &&
    $path === SA_Security::page_url( 'safety' ),
    'canonical route and public URL'
);
$rules = get_option( 'rewrite_rules', array() );
file02_safety_runtime_must(
    is_array( $rules ) &&
    isset( $rules['account/safety/?$'] ) &&
    false !== strpos( (string) $rules['account/safety/?$'], SAUTH_Canonical_Routes::SAFETY ),
    'registered canonical rewrite rule'
);
$manifests = apply_filters( 'sabri_shell_route_manifests', array() );
$route = $manifests['file02-authentication']['routes']['safety'] ?? array();
file02_safety_runtime_must(
    ( $route['route'] ?? '' ) === '/account/safety/' &&
    ( $route['access'] ?? '' ) === 'public' &&
    ( $route['cache'] ?? '' ) === 'no-store' &&
    ( $route['index'] ?? '' ) === 'noindex',
    'public, uncached, noindex shell manifest'
);
wp_set_current_user( 0 );
$anonymous = do_shortcode( '[sabri_auth_account_safety]' );
file02_safety_runtime_must(
    is_string( $anonymous ) &&
    false !== strpos( $anonymous, 'اکاؤنٹ کی لازمی حفاظتی ہدایات' ) &&
    false !== strpos( $anonymous, 'dir="rtl"' ) &&
    false !== strpos( $anonymous, 'lang="ur"' ),
    'anonymous guidance, RTL and language'
);
foreach ( array(
    home_url( '/account/sessions/' ),
    SAUTH_Passkeys::manager_url(),
    SA_Membership_Adapter::security_url(),
) as $private_action ) {
    file02_safety_runtime_must(
        false === strpos( $anonymous, 'href="' . esc_url( $private_action ) . '"' ),
        'anonymous page hides private action'
    );
}
wp_set_current_user( 1 );
$authenticated = do_shortcode( '[sabri_auth_account_safety]' );
file02_safety_runtime_must(
    false !== strpos( $authenticated, esc_url( SAUTH_Passkeys::manager_url() ) ) &&
    false !== strpos( $authenticated, esc_url( SA_Security::page_url( 'sessions' ) ) ) &&
    false !== strpos( $authenticated, esc_url( SA_Membership_Adapter::security_url() ) ),
    'authenticated account owner links'
);
wp_set_current_user( 0 );
echo "PASS: V12.3 real disposable WP account-safety version gate, canonical route, anonymous/private boundary and RTL\n";
