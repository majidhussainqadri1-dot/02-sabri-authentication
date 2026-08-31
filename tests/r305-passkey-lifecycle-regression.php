<?php
$root = dirname( __DIR__ );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$runtime = file_get_contents( $root . '/includes/class-sauth-passkey-runtime.php' );
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$fail = array();
$checks = array(
    array( $passkeys, 'maxlength="4096"', 'passkey manager truncates server-accepted password length' ),
    array( $passkeys, 'SA_Membership_Adapter::sign_in_allowed( $assertion, $completion )', 'legacy passkey sign-in bypasses canonical membership/completion admission' ),
    array( $runtime, 'SA_Membership_Adapter::sign_in_allowed( $assertion, $completion )', 'runtime passkey sign-in bypasses canonical membership/completion admission' ),
    array( $adapter, "'unknown' === \$result", 'canonical sign-in admission no longer fails closed on unknown membership' ),
    array( $adapter, "membership']['suspended']", 'canonical sign-in admission can override suspended membership denial' ),
    array( $adapter, "'membership_prerequisite_denied'", 'canonical sign-in admission can override arbitrary membership denial' ),
    array( $adapter, "'allow' === ( \$completion['result'] ?? '' )", 'canonical sign-in admission does not require File 00 completion allow' ),
    array( $adapter, "! empty( \$completion['missing_steps'] )", 'canonical sign-in admission does not require unfinished completion steps' ),
    array( $adapter, "! empty( \$completion['next_route'] )", 'canonical sign-in admission does not require a canonical completion route' ),
    array( $runtime, 'SAUTH_Passkeys::CONTRACT_VERSION === (string) ( $receipt[\'contract_version\'] ?? \'\' )', 'passkey assurance does not bind current contract version' ),
    array( $passkeys, '$schema_ready', 'passkey availability ignores schema marker' ),
    array( $passkeys, '$table_ready', 'passkey availability ignores physical table state' ),
    array( $passkeys, 'SELECT public_id,nickname,status,created_at,last_used_at,revoked_at', 'privacy export omits retained inactive passkeys' ),
    array( $passkeys, 'items_retained', 'passkey erasure cannot truthfully report retained data' ),
    array( $passkeys, 'SAUTH_Passkey_Runtime::invalidate_user_assurance', 'privacy erasure does not invalidate passkey assurance' ),
    array( $passkeys, 'SAUTH_Passkey_Runtime::EPOCH_META', 'privacy erasure does not clear passkey assurance epoch' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
if ( $fail ) { fwrite( STDERR, "R305 passkey lifecycle regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R305 passkey lifecycle regression PASS (' . count( $checks ) . " assertions).\n";
