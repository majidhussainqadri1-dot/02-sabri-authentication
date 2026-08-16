<?php
$root = dirname( __DIR__ );
$privacy = file_get_contents( $root . '/includes/class-sa-privacy.php' );
$outbox = file_get_contents( $root . '/includes/class-sauth-event-outbox.php' );
$ops = file_get_contents( $root . '/includes/class-sauth-operations.php' );
$fail = array();
$checks = array(
    array( $privacy, 'SAUTH_Passkeys::privacy_export( sanitize_email( $email_address ), $page )', 'privacy exporter still calls stale passkey API' ),
    array( $privacy, 'SAUTH_Passkeys::privacy_erase( sanitize_email( $email_address ), $page )', 'privacy eraser still calls stale passkey API' ),
    array( $privacy, '$offset = ( $page - 1 ) * self::EXPORT_LIMIT', 'privacy exporter silently caps at first page' ),
    array( $privacy, '\'done\' => $done', 'privacy exporter always claims completion' ),
    array( $outbox, '\'producer_version\' => (string) $event[\'producer_version\']', 'outbox does not preserve occurrence producer version' ),
    array( $outbox, '\'occurred_at\' => (string) $event[\'occurred_at\']', 'outbox does not preserve occurrence timestamp' ),
    array( $outbox, 'unset( $decoded[\'sauth_event_meta\'] )', 'reserved outbox metadata leaks into domain payload' ),
    array( $ops, '$core_repaired = SAUTH_Activator::repair()', 'guarded repair ignores core repair result' ),
    array( $ops, 'SAUTH_Activator::storage_ready()', 'guarded repair does not prove core storage postconditions' ),
    array( $ops, 'SAUTH_Passkeys::authentication_ready()', 'system check does not prove passkey runtime readiness' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
if ( $fail ) { fwrite( STDERR, "R307 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R307 privacy/operations regression PASS (' . count( $checks ) . " assertions).\n";
