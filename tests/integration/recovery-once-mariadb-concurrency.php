<?php
/**
 * File 02 v12 F02-X24-011: real WordPress/MariaDB two-process atomic owner
 * claim smoke test. Modes: seed, worker, verify. CI fixture ONLY, never live.
 * Uses a local test-only canonical owner callback (does not certify File00).
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'SAUTH_Security_Orchestrator' ) ) {
    fwrite( STDERR, "FAIL: File02 active WordPress environment required\n" );
    exit( 2 );
}
global $wpdb;
$mode = isset( $args[0] ) ? (string) $args[0] : '';
$table = SAUTH_Activator::table( 'recovery_changes' );
$option = 'sauth_qa_recovery_race_case';
function sauth_qa_recovery_race_token( $case ) {
    return hash_hmac( 'sha256', 'ci-race-token|' . $case, wp_salt( 'auth' ) );
}
function sauth_qa_recovery_race_fail( $reason ) {
    fwrite( STDERR, 'FAIL: ' . $reason . "\n" );
    exit( 1 );
}
if ( 'seed' === $mode ) {
    $old = get_option( $option, '' );
    if ( is_string( $old ) && '' !== $old ) {
        sauth_qa_recovery_race_fail( 'stale QA fixture exists' );
    }
    $case = strtolower( wp_generate_uuid4() );
    $token = sauth_qa_recovery_race_token( $case );
    $encrypted = SA_Security::encrypt( $token );
    $payload = SA_Security::encrypt( wp_json_encode( array( 'next_email'=>'ci-only@example.invalid' ) ) );
    if ( '' === $encrypted || '' === $payload || ! hash_equals( $token, SA_Security::decrypt( $encrypted ) ) ) {
        sauth_qa_recovery_race_fail( 'File02 crypto fixture failed' );
    }
    $now = current_time( 'mysql', true );
    $saved = $wpdb->insert( $table, array(
        'public_id'=>$case, 'user_id'=>1, 'change_kind'=>'email_change',
        'payload_ciphertext'=>$payload, 'owner_token_ciphertext'=>$encrypted,
        'fingerprint_hash'=>'ci-only-fingerprint', 'status'=>'pending',
        'apply_after'=>gmdate( 'Y-m-d H:i:s', time()-60 ),
        'created_at'=>$now, 'updated_at'=>$now,
    ), array( '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s' ) );
    if ( 1 !== (int)$saved || ! add_option( $option, $case, '', false ) ) {
        sauth_qa_recovery_race_fail( 'fixture insertion failed: ' . $wpdb->last_error );
    }
    echo "PASS: real MariaDB pending recovery fixture created\n";
    exit;
}
$case = get_option( $option, '' );
if ( ! is_string( $case ) || ! preg_match( '/^[0-9a-f-]{36}$/', $case ) ) {
    sauth_qa_recovery_race_fail( 'fixture ID unavailable' );
}
if ( 'worker' === $mode ) {
    $token = sauth_qa_recovery_race_token( $case );
    add_filter( 'sauth_apply_recovery_change_v1', static function( $default, $kind, $uid, $payload ) {
        if ( 'email_change' !== $kind || 1 !== (int)$uid || ! is_array( $payload ) ) {
            sauth_qa_recovery_race_fail( 'owner received unexpected claim' );
        }
        if ( ! add_option( 'sauth_qa_recovery_race_owner_' . getmypid(), 'called', '', false ) ) {
            sauth_qa_recovery_race_fail( 'owner call receipt not stored' );
        }
        usleep( 750000 ); // allow the competing process to race while processing.
        return true;
    }, 99, 4 );
    $result = SAUTH_Security_Orchestrator::apply_recovery_change( $case, $token );
    if ( true === $result ) {
        echo "WIN: atomic claim and one canonical-owner callback\n";
        exit;
    }
    if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'sauth_recovery_state_race','sauth_recovery_not_ready' ), true ) ) {
        echo "LOSE: concurrent owner callback blocked: " . $result->get_error_code() . "\n";
        exit;
    }
    sauth_qa_recovery_race_fail( is_wp_error( $result ) ? $result->get_error_code() : 'unexpected apply result' );
}
if ( 'verify' === $mode ) {
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM {$table} WHERE public_id=%s", $case ), ARRAY_A );
    $receipts = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'sauth_qa_recovery_race_owner_%'" );
    if ( ! is_array( $row ) || 'applied' !== $row['status'] || ! is_array( $receipts ) || 1 !== count( $receipts ) ) {
        sauth_qa_recovery_race_fail( 'unexpected end state or callback count=' . ( is_array( $receipts ) ? count( $receipts ) : -1 ) );
    }
    if ( 1 !== (int)$wpdb->delete( $table, array( 'public_id'=>$case ), array('%s') ) ) {
        sauth_qa_recovery_race_fail( 'fixture cleanup failed' );
    }
    delete_option( $option );
    foreach ( $receipts as $receipt ) { delete_option( $receipt ); }
    echo "PASS: one owner invocation, one applied record, no replay; fixture cleaned\n";
    exit;
}
sauth_qa_recovery_race_fail( 'unknown test mode' );
