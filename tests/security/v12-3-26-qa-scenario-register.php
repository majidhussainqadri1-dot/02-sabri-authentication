<?php
/**
 * V12.3 mandatory 26-scenario acceptance map integrity, source ONLY.
 * Staging/real-browser/provider/owner acceptance remains explicitly unverified.
 */
declare(strict_types=1);
$root = dirname( __DIR__, 2 );
function f02_qa_must( $ok, $label ) {
    if ( ! $ok ) { fwrite( STDERR, 'FAIL V12.3 26-scenario register: ' . $label . PHP_EOL ); exit( 1 ); }
}
$doc = json_decode(
    (string) file_get_contents( $root . '/review-evidence/F02-V12.3-2026-11-14-26-QA-SCENARIOS.json' ),
    true
);
$plan = json_decode(
    (string) file_get_contents( $root . '/review-evidence/F02-V12-2026-11-10-48-ACCEPTANCE-REGISTER.json' ),
    true
);
f02_qa_must( is_array( $doc ) && is_array( $plan ), 'cannot read required registers' );
f02_qa_must(
    ( $doc['schema'] ?? '' ) === 'sabri.file02.v12.3.qa-scenarios.v1' &&
    ( $doc['summary']['tests'] ?? -1 ) === 26 &&
    ( $doc['summary']['production_accepted'] ?? -1 ) === 0 &&
    ( $doc['summary']['real_staging_accepted'] ?? -1 ) === 0,
    'misleading acceptance summary'
);
$valid_requirements = array();
foreach ( $plan['requirements'] as $item ) { $valid_requirements[ $item['id'] ] = true; }
$seen = array();
foreach ( (array) ( $doc['scenarios'] ?? array() ) as $row ) {
    f02_qa_must( is_array( $row ) && is_string( $row['id'] ?? null ), 'row missing ID' );
    $id = $row['id'];
    f02_qa_must( ! isset( $seen[ $id ] ) && preg_match( '/^T(?:0[1-9]|1[0-9]|2[0-6])$/D', $id ), 'duplicate/unknown ID ' . $id );
    $seen[ $id ] = true;
    f02_qa_must(
        ( $row['production_acceptance'] ?? '' ) === 'not_verified' &&
        is_string( $row['remaining_gate'] ?? null ) && '' !== $row['remaining_gate'] &&
        is_string( $row['scope'] ?? null ) && '' !== $row['scope'],
        'false accepted claim or missing gate ' . $id
    );
    foreach ( (array) ( $row['requirement_ids'] ?? array() ) as $req ) {
        f02_qa_must( is_string( $req ) && isset( $valid_requirements[ $req ] ), 'unknown plan ID ' . $id );
    }
    foreach ( (array) ( $row['test_paths'] ?? array() ) as $path ) {
        f02_qa_must( is_string( $path ) && strpos( $path, '..' ) === false && strpos( $path, '/' ) !== 0 && is_file( $root . '/' . $path ), 'test pointer unavailable ' . $id );
    }
}
for ( $i = 1; $i <= 26; $i++ ) {
    $id = 'T' . str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
    f02_qa_must( isset( $seen[ $id ] ), 'missing ' . $id );
}
f02_qa_must( count( $seen ) === 26, 'not exactly 26 cases' );
echo 'PASS all 26 V12.3 QA scenario pointers with 0 invented staging/live acceptance.' . PHP_EOL;
