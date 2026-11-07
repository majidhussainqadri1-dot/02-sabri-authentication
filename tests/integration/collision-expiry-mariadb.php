<?php
/**
 * File 02 v12 F02-X24-012 + F02-NFR-002/003:
 * aged collision cases must become expired and eventually purge,
 * without cancelling generic recovery or an in-flight owner claim.
 * Disposable real WordPress/MariaDB fixture ONLY; never run on live.
 */
if ( ! defined( 'ABSPATH' ) || ! class_exists( 'SAUTH_Security_Orchestrator' ) ) {
    fwrite( STDERR, "FAIL: WordPress with File 02 required\n" );
    exit( 2 );
}
global $wpdb;
$table = SAUTH_Activator::table( 'recovery_changes' );
$GLOBALS['sauth_qa_expiry_ids'] = array();
function sauth_qa_expiry_case( $kind, $status, $created_at, $uid ) {
    global $wpdb;
    $table = SAUTH_Activator::table( 'recovery_changes' );
    $id = strtolower( wp_generate_uuid4() );
    $now = current_time( 'mysql', true );
    $saved = $wpdb->insert( $table, array(
        'public_id'=>$id, 'user_id'=>$uid, 'change_kind'=>$kind,
        'payload_ciphertext'=>SA_Security::encrypt( '{}' ),
        'owner_token_ciphertext'=>SA_Security::encrypt( 'ci-only-owner' ),
        'fingerprint_hash'=>str_repeat('1',64), 'status'=>$status,
        'apply_after'=>$now, 'created_at'=>$created_at, 'updated_at'=>$now,
    ), array( '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s' ) );
    if ( 1 !== (int)$saved ) { throw new RuntimeException('fixture insert failed: ' . $wpdb->last_error); }
    $GLOBALS['sauth_qa_expiry_ids'][] = $id;
    return $id;
}
function sauth_qa_expiry_status( $id ) {
    global $wpdb;
    $table = SAUTH_Activator::table( 'recovery_changes' );
    return (string)$wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE public_id=%s", $id ) );
}
try {
    $old = gmdate( 'Y-m-d H:i:s', time() - 1200 );
    $now = current_time( 'mysql', true );
    $expired = sauth_qa_expiry_case( 'collision_resolution', 'pending', $old, 0 );
    $fresh = sauth_qa_expiry_case( 'collision_resolution', 'pending', $now, 0 );
    $generic = sauth_qa_expiry_case( 'email_change', 'pending', $old, 1 );
    $claimed = sauth_qa_expiry_case( 'collision_resolution', 'processing', $old, 0 );
    SAUTH_Security_Orchestrator::cleanup();
    $states = array(
        'expired'=>sauth_qa_expiry_status( $expired ),
        'fresh'=>sauth_qa_expiry_status( $fresh ),
        'generic'=>sauth_qa_expiry_status( $generic ),
        'claimed'=>sauth_qa_expiry_status( $claimed ),
    );
    if ( $states !== array('expired'=>'expired','fresh'=>'pending','generic'=>'pending','claimed'=>'processing') ) {
        throw new RuntimeException('cleanup lifecycle mismatch: ' . wp_json_encode($states));
    }
    $old_update = gmdate('Y-m-d H:i:s', time() - 31 * DAY_IN_SECONDS);
    if ( 1 !== (int)$wpdb->update($table, array('updated_at'=>$old_update), array('public_id'=>$expired), array('%s'), array('%s')) ) {
        throw new RuntimeException('failed to age expired fixture');
    }
    SAUTH_Security_Orchestrator::cleanup();
    if ( '' !== sauth_qa_expiry_status( $expired ) ) {
        throw new RuntimeException('30-day expired collision record not purged');
    }
    echo "PASS: expired collision lifecycle, fresh/generic/processing isolation and retention cleanup (real MariaDB)\n";
} catch ( Throwable $error ) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
} finally {
    foreach ( $GLOBALS['sauth_qa_expiry_ids'] as $id ) { $wpdb->delete($table, array('public_id'=>$id), array('%s')); }
}
