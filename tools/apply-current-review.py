#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

# R315 — align every passkey runtime path with the canonical schema and preserve legacy credentials.
p='includes/class-sauth-passkeys.php'; s=read(p)
old="""\t\t$table = self::table(); $charset = $wpdb->get_charset_collate();
\t\t$sql = \"CREATE TABLE {$table} (
"""
new="""\t\t$table = self::table(); $charset = $wpdb->get_charset_collate();
\t\tif ( ! self::prepare_legacy_credential_columns( $table ) ) { delete_option( self::OPTION_SCHEMA_VERSION ); return false; }
\t\t$sql = \"CREATE TABLE {$table} (
"""
s=one(s,old,new,'legacy passkey schema preparation')
anchor="""\tprivate static function ensure_manager_page() {
"""
helper="""\tprivate static function prepare_legacy_credential_columns( $table ) {
\t\tglobal $wpdb;
\t\t$exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\tif ( ! $exists ) { return '' === (string) $wpdb->last_error; }
\t\t$columns = $wpdb->get_col( \"SHOW COLUMNS FROM `{$table}`\", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\tif ( ! is_array( $columns ) || '' !== (string) $wpdb->last_error ) { return false; }
\t\t$has_legacy_hash = in_array( 'credential_hash', $columns, true );
\t\t$has_legacy_cipher = in_array( 'credential_cipher', $columns, true );
\t\tif ( $has_legacy_hash && ! in_array( 'credential_lookup_hash', $columns, true ) ) {
\t\t\tif ( false === $wpdb->query( \"ALTER TABLE `{$table}` ADD COLUMN credential_lookup_hash char(64) NOT NULL\" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t}
\t\tif ( $has_legacy_cipher && ! in_array( 'credential_id_ciphertext', $columns, true ) ) {
\t\t\tif ( false === $wpdb->query( \"ALTER TABLE `{$table}` ADD COLUMN credential_id_ciphertext longtext NOT NULL\" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t}
\t\tif ( $has_legacy_hash ) {
\t\t\tif ( false === $wpdb->query( \"UPDATE `{$table}` SET credential_lookup_hash=credential_hash WHERE credential_lookup_hash='' AND credential_hash<>''\" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t}
\t\tif ( $has_legacy_cipher ) {
\t\t\tif ( false === $wpdb->query( \"UPDATE `{$table}` SET credential_id_ciphertext=credential_cipher WHERE credential_id_ciphertext='' AND credential_cipher<>''\" ) ) { return false; } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t}
\t\t$unmigrated = (int) $wpdb->get_var( \"SELECT COUNT(*) FROM `{$table}` WHERE credential_lookup_hash='' OR credential_id_ciphertext=''\" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\treturn '' === (string) $wpdb->last_error && 0 === $unmigrated;
\t}

"""+anchor
s=one(s,anchor,helper,'legacy migration helper anchor')
s=s.replace("$credential['credential_cipher']", "$credential['credential_id_ciphertext']")
s=s.replace("'credential_hash' => $credential_hash", "'credential_lookup_hash' => $credential_hash")
s=s.replace("'credential_cipher' => $cipher", "'credential_id_ciphertext' => $cipher")
s=s.replace('WHERE credential_hash=%s LIMIT 1', 'WHERE credential_lookup_hash=%s LIMIT 1')
old="""\t\t$schema_ready = self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' );
\t\t$table_ready = false;
\t\tif ( $schema_ready ) {
\t\t\tglobal $wpdb;
\t\t\t$table = self::table();
\t\t\t$table_ready = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\t}
"""
new="""\t\t$schema_ready = self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' );
\t\t$table_ready = $schema_ready && self::table_schema_ready();
"""
s=one(s,old,new,'material environment schema check')
write(p,s)

p='includes/class-sauth-passkey-runtime.php'; s=read(p)
# Canonical physical columns.
s=s.replace("'credential_hash' => $credential_hash", "'credential_lookup_hash' => $credential_hash")
s=s.replace("'credential_cipher' => $cipher", "'credential_id_ciphertext' => $cipher")
s=s.replace('WHERE credential_hash=%s LIMIT 1', 'WHERE credential_lookup_hash=%s LIMIT 1')
s=s.replace('SELECT user_id,credential_hash,status FROM ', 'SELECT user_id,credential_lookup_hash,status FROM ')
s=s.replace("$check['credential_hash']", "$check['credential_lookup_hash']")
# Fail closed on registration count/duplicate lookup uncertainty.
old="""\t\t\t$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . \" WHERE user_id=%d AND status='active'\", $user_id ) );
\t\t\tif ( $count >= self::MAX_CREDENTIALS ) {
"""
new="""\t\t\t$count_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . \" WHERE user_id=%d AND status='active'\", $user_id ) );
\t\t\tif ( null === $count_raw || '' !== (string) $wpdb->last_error ) { self::json_error( 'credential_store_unavailable' ); }
\t\t\t$count = (int) $count_raw;
\t\t\tif ( $count >= self::MAX_CREDENTIALS ) {
"""
s=one(s,old,new,'credential count DB uncertainty')
old="""\t\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE credential_lookup_hash=%s LIMIT 1', $credential_hash ) );
\t\t\tif ( $exists ) {
"""
new="""\t\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE credential_lookup_hash=%s LIMIT 1', $credential_hash ) );
\t\t\tif ( '' !== (string) $wpdb->last_error ) { self::json_error( 'credential_store_unavailable' ); }
\t\t\tif ( $exists ) {
"""
s=one(s,old,new,'credential duplicate DB uncertainty')
# Already-issued ceremonies must not mutate/authenticate after Safe Mode is raised.
old="""\tpublic static function finish_registration() {
\t\tself::require_authenticated_ajax();
"""
new="""\tpublic static function finish_registration() {
\t\tself::require_authenticated_ajax();
\t\tif ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) { self::json_error( 'passkeys_unavailable', 503 ); }
"""
s=one(s,old,new,'finish registration Safe Mode gate')
old="""\tpublic static function finish_authentication() {
\t\tif ( is_user_logged_in() ) {
"""
new="""\tpublic static function finish_authentication() {
\t\tif ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) { self::json_error( 'passkeys_unavailable', 503 ); }
\t\tif ( is_user_logged_in() ) {
"""
s=one(s,old,new,'finish authentication Safe Mode gate')
# Credential lookup DB uncertainty is distinct from an unknown credential.
old="""\t\t$credential = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE credential_lookup_hash=%s LIMIT 1', $credential_hash ), ARRAY_A );
\t\tif ( ! is_array( $credential ) || 'active' !== (string) $credential['status'] ) {
"""
new="""\t\t$credential = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE credential_lookup_hash=%s LIMIT 1', $credential_hash ), ARRAY_A );
\t\tif ( '' !== (string) $wpdb->last_error ) { self::authentication_failure( 0, 'credential_store_unavailable' ); }
\t\tif ( ! is_array( $credential ) || 'active' !== (string) $credential['status'] ) {
"""
s=one(s,old,new,'authentication credential DB uncertainty')
# Counter regression must materially quarantine the credential before returning.
old="""\t\tif ( $stored_count > 0 && $new_count > 0 && $new_count <= $stored_count ) {
\t\t\t$wpdb->update( self::table(), array( 'status' => 'compromised', 'revoked_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $credential['id'] ), 'status' => 'active' ), array( '%s','%s','%s' ), array( '%d','%s' ) );
\t\t\tself::invalidate_user_assurance( $user_id );
\t\t\tself::authentication_failure( $user_id, 'signature_counter_regression' );
\t\t}
"""
new="""\t\tif ( $stored_count > 0 && $new_count > 0 && $new_count <= $stored_count ) {
\t\t\t$quarantined = $wpdb->update( self::table(), array( 'status' => 'compromised', 'revoked_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $credential['id'] ), 'status' => 'active' ), array( '%s','%s','%s' ), array( '%d','%s' ) );
\t\t\t$status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE id=%d', absint( $credential['id'] ) ) );
\t\t\t$invalidated = self::invalidate_user_assurance( $user_id );
\t\t\tif ( 1 !== (int) $quarantined || 'compromised' !== $status || ! $invalidated ) {
\t\t\t\tif ( class_exists( 'SAUTH_Operations' ) ) { update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false ); }
\t\t\t\tif ( class_exists( 'WP_Session_Tokens' ) ) { WP_Session_Tokens::get_instance( $user_id )->destroy_all(); }
\t\t\t\tself::authentication_failure( $user_id, 'credential_quarantine_failed' );
\t\t\t}
\t\t\tself::authentication_failure( $user_id, 'signature_counter_regression' );
\t\t}
"""
s=one(s,old,new,'counter-regression quarantine postcondition')
write(p,s)

# Remove randomness from the EC fixture: WebAuthn P-256 COSE coordinates are exactly 32-byte unsigned values.
p='tests/passkey-webauthn-unit.php'; s=read(p)
old="""$details = openssl_pkey_get_details( $key );
sauth_pk_assert( is_array( $details ) && ! empty( $details['key'] ) && ! empty( $details['ec']['x'] ) && ! empty( $details['ec']['y'] ), 'test EC public coordinates obtained' );

$cose = sauth_test_cbor_map(
"""
new="""$details = openssl_pkey_get_details( $key );
sauth_pk_assert( is_array( $details ) && ! empty( $details['key'] ) && ! empty( $details['ec']['x'] ) && ! empty( $details['ec']['y'] ), 'test EC public coordinates obtained' );
sauth_pk_assert( strlen( $details['ec']['x'] ) <= 32 && strlen( $details['ec']['y'] ) <= 32, 'test EC coordinates fit P-256 width' );
$test_x = str_pad( (string) $details['ec']['x'], 32, "\\x00", STR_PAD_LEFT );
$test_y = str_pad( (string) $details['ec']['y'], 32, "\\x00", STR_PAD_LEFT );

$cose = sauth_test_cbor_map(
"""
s=one(s,old,new,'deterministic EC coordinate fixture')
s=s.replace("sauth_test_cbor_bytes( $details['ec']['x'] )", "sauth_test_cbor_bytes( $test_x )")
s=s.replace("sauth_test_cbor_bytes( $details['ec']['y'] )", "sauth_test_cbor_bytes( $test_y )")
write(p,s)

write('tests/r315-passkey-canonical-schema-regression.php',r'''<?php
$root=dirname(__DIR__); $p=file_get_contents($root.'/includes/class-sauth-passkeys.php'); $r=file_get_contents($root.'/includes/class-sauth-passkey-runtime.php'); $t=file_get_contents($root.'/tests/passkey-webauthn-unit.php'); $fail=array();
$checks=array(
 array($p,'prepare_legacy_credential_columns','legacy passkey credentials have no canonical-column migration'),
 array($p,'credential_lookup_hash','historical passkey surface is not using canonical lookup column'),
 array($p,'credential_id_ciphertext','historical passkey surface is not using canonical ciphertext column'),
 array($p,'$schema_ready && self::table_schema_ready()','passkey environment readiness still proves table name only'),
 array($r,'credential_lookup_hash','hardened passkey runtime is not using canonical lookup column'),
 array($r,'credential_id_ciphertext','hardened passkey runtime is not using canonical ciphertext column'),
 array($r,"credential_store_unavailable",'passkey DB read uncertainty does not fail closed'),
 array($r,"credential_quarantine_failed",'counter regression quarantine has no containment failure path'),
 array($r,"SAUTH_Operations::safe_mode()",'issued passkey ceremony can finish after Safe Mode is raised'),
 array($t,'str_pad( (string) $details[\'ec\'][\'x\'], 32','WebAuthn EC fixture remains random-width/flaky')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
foreach(array($p,$r) as $src){if(false!==strpos($src,'WHERE credential_hash=%s')||false!==strpos($src,"'credential_hash' =>")||false!==strpos($src,"'credential_cipher' =>"))$fail[]='legacy physical passkey column name remains in active runtime';}
if($fail){fwrite(STDERR,"R315 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R315 passkey canonical-schema regression PASS ('.(count($checks)+2)." assertions).\n";
''')
print('R315 frozen ledger corrections applied')
