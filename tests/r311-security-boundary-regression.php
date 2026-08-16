<?php
$root = dirname( __DIR__ );
$risk = file_get_contents( $root . '/includes/class-sauth-login-risk.php' );
$guard = file_get_contents( $root . '/includes/class-sauth-provider-http-guard.php' );
$fail = array();
$checks = array(
    array( $risk, "'risk_storage_unavailable'", 'risk engine does not fail closed on storage errors' ),
    array( $risk, 'null === $has_active_passkey', 'passkey lookup uncertainty can still become a medium-risk password allow' ),
    array( $risk, "'passkey_status_unavailable'", 'passkey lookup failure has no explicit fail-closed reason' ),
    array( $risk, 'null === $count && \'\' !== (string) $wpdb->last_error', 'active-passkey database errors collapse to false/no-passkey' ),
    array( $guard, '\'https\' !== $scheme', 'provider HTTP guard does not reject non-HTTPS Google URLs' ),
    array( $guard, "'sauth_provider_https_required'", 'provider HTTPS rejection is not explicit' ),
);
foreach ( $checks as $check ) { if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; } }
if ( $fail ) { fwrite( STDERR, "R311 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R311 security boundary regression PASS (' . count( $checks ) . " assertions).\n";
