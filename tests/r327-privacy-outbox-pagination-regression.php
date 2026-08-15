<?php
$root = dirname( __DIR__ );
$privacy = file_get_contents( $root . '/includes/class-sa-privacy.php' );
$outbox = file_get_contents( $root . '/includes/class-sauth-event-outbox.php' );
$jobs = file_get_contents( $root . '/includes/class-sauth-privacy-jobs.php' );
$fail = array();
$checks = array(
    array( $privacy, '$more_rows = false;', 'privacy eraser lacks a unified bounded continuation state' ),
    array( $privacy, '$more_rows = true;', 'remaining File 02 rows are still treated only as fatal failure' ),
    array( $privacy, "'done' => false", 'privacy eraser cannot request the next WordPress erasure page' ),
    array( $privacy, 'authentication_privacy_erasure_continuation', 'privacy continuation lacks audit evidence' ),
    array( $jobs, 'begin_erasure', 'privacy asynchronous-job barrier missing' ),
    array( $jobs, 'valid_snapshot', 'queued jobs are not bound to privacy epoch' ),
    array( $outbox, 'actor_user_id', 'event outbox lost direct subject identifier field required for anonymization' ),
);
foreach ( $checks as $check ) {
    if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; }
}
if ( $fail ) { fwrite( STDERR, "R327 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R327 privacy/outbox pagination regression PASS (7 assertions).' . PHP_EOL;
