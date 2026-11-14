<?php
/**
 * V12.3 48-ID source-to-test/owner/gate register integrity.
 * Path existence and acceptance truth only: DOES NOT prove feature behavior.
 */
declare(strict_types=1);
$root = dirname( __DIR__, 2 );
$path = $root . '/review-evidence/F02-V12.3-2026-11-14-48-ID-SOURCE-TRACEABILITY.json';
$original = $root . '/review-evidence/F02-V12-2026-11-10-48-ACCEPTANCE-REGISTER.json';
function f02_trace_must( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL V12.3 trace: " . $message . PHP_EOL );
        exit( 1 );
    }
}
$data = json_decode( (string) file_get_contents( $path ), true );
$old = json_decode( (string) file_get_contents( $original ), true );
f02_trace_must( is_array( $data ) && is_array( $old ), 'registries must parse' );
f02_trace_must(
    ( $data['schema'] ?? '' ) === 'sabri.file02.v12.3.traceability.v1' &&
    ( $data['summary']['requirements'] ?? -1 ) === 48 &&
    ( $data['summary']['independently_verified_production_acceptances'] ?? -1 ) === 0,
    'schema, total and no fabricated production acceptance'
);
$old_by_id = array();
foreach ( $old['requirements'] as $item ) { $old_by_id[ $item['id'] ] = $item; }
$seen = array();
$classes = array( 'FR' => 0, 'NFR' => 0, 'CEN' => 0, 'X24' => 0 );
$states = array();
foreach ( (array) ( $data['requirements'] ?? array() ) as $row ) {
    f02_trace_must( is_array( $row ) && is_string( $row['id'] ?? null ), 'invalid trace row' );
    $id = $row['id'];
    f02_trace_must( ! isset( $seen[ $id ] ) && isset( $old_by_id[ $id ] ), 'duplicate or unsupported ' . $id );
    $seen[ $id ] = true;
    $previous = $old_by_id[ $id ];
    f02_trace_must(
        ( $row['class'] ?? '' ) === ( $previous['class'] ?? '' ) &&
        ( $row['source_state'] ?? '' ) === ( $previous['source'] ?? '' ) &&
        ( $row['acceptance'] ?? '' ) === 'not_verified' &&
        ( $previous['acceptance'] ?? '' ) === 'not_verified',
        'original class/source and acceptance truth ' . $id
    );
    $classes[ $row['class'] ]++;
    $states[ $row['source_state'] ] = ( $states[ $row['source_state'] ] ?? 0 ) + 1;
    f02_trace_must(
        isset( $row['source'], $row['test'], $row['gate'], $row['owner'] ) &&
        is_array( $row['source'] ) && count( $row['source'] ) > 0 &&
        is_array( $row['test'] ) && is_string( $row['gate'] ) && $row['gate'] !== '' &&
        is_string( $row['owner'] ) && $row['owner'] !== '',
        'owner, source, test and gate shape ' . $id
    );
    foreach ( array_merge( $row['source'], $row['test'] ) as $relative ) {
        f02_trace_must(
            is_string( $relative ) && strpos( $relative, '..' ) === false &&
            strpos( $relative, '/' ) !== 0 && is_file( $root . '/' . $relative ),
            'path is absent or unsafe for ' . $id . ': ' . (string) $relative
        );
    }
}
f02_trace_must( count( $seen ) === 48, 'wrong number of identities' );
f02_trace_must( $classes === array( 'FR' => 12, 'NFR' => 10, 'CEN' => 2, 'X24' => 24 ), 'wrong group counts' );
f02_trace_must(
    ( $states['implemented_candidate'] ?? 0 ) === 28 &&
    ( $states['partial_acceptance_controls'] ?? 0 ) === 10 &&
    ( $states['cross_file_contract_candidate'] ?? 0 ) === 2 &&
    ( $states['adapter_or_partial_candidate'] ?? 0 ) === 8,
    'misrepresented candidate source classes'
);
echo 'PASS V12.3 all 48 requirement source/test path pointers exist; owners and independent acceptances unclaimed.' . PHP_EOL;
