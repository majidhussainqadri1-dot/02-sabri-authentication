#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

# R317 ledger 1: privacy export must never report completion after a DB read failure.
p='includes/class-sa-privacy.php'; s=read(p)
old="""\t\t$sessions = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,device_label,network_label,risk_level,status,last_seen_at,expires_at,revoked_at,revocation_reason FROM ' . SAUTH_Activator::table( 'auth_sessions' ) . ' WHERE user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
\t\t$sessions = is_array( $sessions ) ? $sessions : array();
"""
new="""\t\t$sessions = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,device_label,network_label,risk_level,status,last_seen_at,expires_at,revoked_at,revocation_reason FROM ' . SAUTH_Activator::table( 'auth_sessions' ) . ' WHERE user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
\t\tif ( ! is_array( $sessions ) || '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
"""
s=one(s,old,new,'privacy session export DB failure')
old="""\t\t$email_row = 1 === $page ? $wpdb->get_row( $wpdb->prepare( 'SELECT status,attempts,sent_at,expires_at,verified_at,created_at,updated_at FROM ' . SAUTH_Activator::table( 'email_verifications' ) . ' WHERE user_id=%d', $user_id ), ARRAY_A ) : null;
\t\tif ( is_array( $email_row ) ) {
"""
new="""\t\t$email_row = 1 === $page ? $wpdb->get_row( $wpdb->prepare( 'SELECT status,attempts,sent_at,expires_at,verified_at,created_at,updated_at FROM ' . SAUTH_Activator::table( 'email_verifications' ) . ' WHERE user_id=%d', $user_id ), ARRAY_A ) : null;
\t\tif ( 1 === $page && '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
\t\tif ( is_array( $email_row ) ) {
"""
s=one(s,old,new,'privacy email export DB failure')
old="""\t\t$attempts = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,result,reason_code,risk_score,created_at FROM ' . SAUTH_Activator::table( 'auth_attempts' ) . ' WHERE user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
\t\t$attempts = is_array( $attempts ) ? $attempts : array();
"""
new="""\t\t$attempts = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,result,reason_code,risk_score,created_at FROM ' . SAUTH_Activator::table( 'auth_attempts' ) . ' WHERE user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
\t\tif ( ! is_array( $attempts ) || '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
"""
s=one(s,old,new,'privacy attempts export DB failure')
old="""\t\t$events = $wpdb->get_results( $wpdb->prepare( 'SELECT event_id,event_name,privacy_class,trace_id,status,created_at,published_at FROM ' . SAUTH_Activator::table( 'auth_outbox' ) . ' WHERE actor_user_id=%d OR subject_user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
\t\t$events = is_array( $events ) ? $events : array();
"""
new="""\t\t$events = $wpdb->get_results( $wpdb->prepare( 'SELECT event_id,event_name,privacy_class,trace_id,status,created_at,published_at FROM ' . SAUTH_Activator::table( 'auth_outbox' ) . ' WHERE actor_user_id=%d OR subject_user_id=%d ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, $user_id, self::EXPORT_LIMIT, $offset ), ARRAY_A );
\t\tif ( ! is_array( $events ) || '' !== (string) $wpdb->last_error ) { return array( 'data' => $data, 'done' => false ); }
"""
s=one(s,old,new,'privacy events export DB failure')

# R317 ledger 2: destructive erasure postconditions must not cast DB uncertainty to zero/success.
old="""\t\t\t$passkey_table = $wpdb->prefix . 'sauth_passkeys';
\t\t\t$passkey_count = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$passkey_table} WHERE user_id=%d\", $user_id ) );
\t\t\tif ( $passkey_count > 0 ) { $removed = true; }
"""
new="""\t\t\t$passkey_table = $wpdb->prefix . 'sauth_passkeys';
\t\t\t$passkey_count_raw = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$passkey_table} WHERE user_id=%d\", $user_id ) );
\t\t\tif ( null === $passkey_count_raw || '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_passkey_count_failed' ); }
\t\t\t$passkey_count = (int) $passkey_count_raw;
\t\t\tif ( $passkey_count > 0 ) { $removed = true; }
"""
s=one(s,old,new,'privacy passkey pre-count')
old="""\t\t\tif ( 0 !== (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$passkey_table} WHERE user_id=%d\", $user_id ) ) ) { $failed = true; }
"""
new="""\t\t\t$passkey_remaining = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM {$passkey_table} WHERE user_id=%d\", $user_id ) );
\t\t\tif ( null === $passkey_remaining || '' !== (string) $wpdb->last_error || 0 !== (int) $passkey_remaining ) { $failed = true; }
"""
s=one(s,old,new,'privacy passkey post-count')
old="""\t\t\t\t\t$count = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d\", $user_id ) );
\t\t\t\t\tif ( $count > 0 ) { $removed = true; }
\t\t\t\t\t$result = $wpdb->query( $wpdb->prepare( \"DELETE FROM `{$table}` WHERE user_id=%d\", $user_id ) );
\t\t\t\t\tif ( false === $result || 0 !== (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d\", $user_id ) ) ) { $failed = true; }
"""
new="""\t\t\t\t\t$count_raw = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d\", $user_id ) );
\t\t\t\t\tif ( null === $count_raw || '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_table_count_failed' ); }
\t\t\t\t\tif ( (int) $count_raw > 0 ) { $removed = true; }
\t\t\t\t\t$result = $wpdb->query( $wpdb->prepare( \"DELETE FROM `{$table}` WHERE user_id=%d\", $user_id ) );
\t\t\t\t\t$remaining_raw = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM `{$table}` WHERE user_id=%d\", $user_id ) );
\t\t\t\t\tif ( false === $result || null === $remaining_raw || '' !== (string) $wpdb->last_error || 0 !== (int) $remaining_raw ) { $failed = true; }
"""
s=one(s,old,new,'privacy table pair postconditions')
old="""\t\t\t\t$rows = $wpdb->get_results( $wpdb->prepare( \"SELECT id,payload_json FROM `{$table}` WHERE actor_user_id=%d OR subject_user_id=%d LIMIT %d\", $user_id, $user_id, 5000 ), ARRAY_A );
\t\t\t\tforeach ( is_array( $rows ) ? $rows : array() as $row ) {
"""
new="""\t\t\t\t$rows = $wpdb->get_results( $wpdb->prepare( \"SELECT id,payload_json FROM `{$table}` WHERE actor_user_id=%d OR subject_user_id=%d LIMIT %d\", $user_id, $user_id, 5000 ), ARRAY_A );
\t\t\t\tif ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_outbox_read_failed' ); }
\t\t\t\tforeach ( $rows as $row ) {
"""
s=one(s,old,new,'privacy outbox row read')
old="""\t\t\t\t$remaining = (int) $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM `{$table}` WHERE actor_user_id=%d OR subject_user_id=%d\", $user_id, $user_id ) );
\t\t\t\tif ( $remaining > 0 ) { $failed = true; }
"""
new="""\t\t\t\t$remaining_raw = $wpdb->get_var( $wpdb->prepare( \"SELECT COUNT(*) FROM `{$table}` WHERE actor_user_id=%d OR subject_user_id=%d\", $user_id, $user_id ) );
\t\t\t\tif ( null === $remaining_raw || '' !== (string) $wpdb->last_error || (int) $remaining_raw > 0 ) { $failed = true; }
"""
s=one(s,old,new,'privacy outbox anonymization postcondition')
old="""\tprivate static function table_exists( $table ) {
\t\tglobal $wpdb;
\t\t$table = (string) $table;
\t\treturn '' !== $table && $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t}
"""
new="""\tprivate static function table_exists( $table ) {
\t\tglobal $wpdb;
\t\t$table = (string) $table;
\t\tif ( '' === $table ) { return false; }
\t\t$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\tif ( '' !== (string) $wpdb->last_error ) { throw new RuntimeException( 'privacy_table_probe_failed' ); }
\t\treturn $table === (string) $found;
\t}
"""
s=one(s,old,new,'privacy table probe DB uncertainty')
write(p,s)

# R317 ledger 3: operational system check must prove material storage and dispatch scheduling, not markers/names only.
p='includes/class-sauth-operations.php'; s=read(p)
old="""\t\t$checks[] = self::check( 'Database schema marker', SAUTH_DB_VERSION === (string) get_option( 'sauth_db_version', '' ), 'Expected=' . SAUTH_DB_VERSION . '; stored=' . (string) get_option( 'sauth_db_version', '' ) );
\t\tforeach ( SAUTH_Activator::required_tables() as $name => $table ) {
"""
new="""\t\t$checks[] = self::check( 'Database schema marker', SAUTH_DB_VERSION === (string) get_option( 'sauth_db_version', '' ), 'Expected=' . SAUTH_DB_VERSION . '; stored=' . (string) get_option( 'sauth_db_version', '' ) );
\t\t$core_storage_ready = SAUTH_Activator::storage_ready();
\t\t$checks[] = self::check( 'Material File 02 storage postconditions', $core_storage_ready, $core_storage_ready ? 'Required tables, columns, managed pages and passkey installation postconditions are materialized.' : 'One or more material storage/page/passkey postconditions are incomplete.' );
\t\t$outbox_scheduled = class_exists( 'SAUTH_Event_Outbox' ) && function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( SAUTH_Event_Outbox::CRON_HOOK );
\t\t$checks[] = self::check( 'Authentication event outbox dispatch schedule', $outbox_scheduled, $outbox_scheduled ? 'Authentication event dispatch cron is scheduled.' : 'Authentication event dispatch cron is not scheduled.' );
\t\tforeach ( SAUTH_Activator::required_tables() as $name => $table ) {
"""
s=one(s,old,new,'operations material storage/cron checks')
write(p,s)

write('tests/r317-privacy-integrity-regression.php',r'''<?php
$root=dirname(__DIR__); $p=file_get_contents($root.'/includes/class-sa-privacy.php'); $o=file_get_contents($root.'/includes/class-sauth-operations.php'); $u=file_get_contents($root.'/uninstall.php'); $fail=array();
$checks=array(
 array($p,"return array( 'data' => $data, 'done' => false )",'privacy DB export failures can still be reported complete'),
 array($p,'privacy_passkey_count_failed','passkey erasure precondition DB failure can collapse to zero'),
 array($p,'null === $passkey_remaining','passkey erasure postcondition DB failure can collapse to zero'),
 array($p,'privacy_table_count_failed','table erasure precondition DB failure can collapse to zero'),
 array($p,'null === $remaining_raw','destructive erasure postconditions do not distinguish DB uncertainty'),
 array($p,'privacy_outbox_read_failed','outbox anonymization can silently skip a failed read'),
 array($p,'privacy_table_probe_failed','table probe DB failure can be mistaken for an absent table'),
 array($o,'Material File 02 storage postconditions','system check can be green on marker/table-name evidence only'),
 array($o,'Authentication event outbox dispatch schedule','system check ignores a missing event-dispatch scheduler'),
 array($u,'Intentionally no destructive action','uninstall silently destroys retained authentication evidence')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if($fail){fwrite(STDERR,"R317 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R317 privacy integrity regression PASS ('.count($checks)." assertions).\n";
''')
print('R317 frozen ledger corrections applied')
