#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding='utf-8')
def write(p,s): (ROOT/p).write_text(s,encoding='utf-8')
def one(s,old,new,label):
    n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected 1 patch point, found {n}')
    return s.replace(old,new,1)

# R312: migration postconditions must prove material schema, not table-name existence.
p='includes/class-sa-activator.php'; s=read(p)
anchor="""\tpublic static function create_rate_limit_table() {
"""
insert="""\tprivate static function required_table_columns() {
\t\treturn array(
\t\t\t'rate limits'        => array( 'bucket_hash','hits','window_started','expires_at','updated_at' ),
\t\t\t'event outbox'       => array( 'id','event_id','event_name','schema_version','privacy_class','actor_user_id','subject_user_id','trace_id','payload_json','status','attempts','available_at','published_at','last_error','created_at','updated_at' ),
\t\t\t'email verification' => array( 'user_id','email_hash','token_hash','status','attempts','sent_at','expires_at','verified_at','created_at','updated_at' ),
\t\t\t'session registry'   => array( 'id','public_id','user_id','token_hash','device_hash','device_label','network_label','risk_level','status','revocation_reason','created_at','last_seen_at','expires_at','revoked_at','updated_at' ),
\t\t\t'trusted devices'    => array( 'id','public_id','user_id','fingerprint_hash','network_hash','device_label','network_label','status','risk_score','first_seen_at','last_seen_at','last_login_at','updated_at' ),
\t\t\t'risk challenges'    => array( 'id','public_id','token_hash','user_id','fingerprint_hash','risk_score','reason_code','remember_session','destination','completion_json','status','attempts','expires_at','consumed_at','created_at','updated_at' ),
\t\t\t'auth attempts'      => array( 'id','public_id','user_id','fingerprint_hash','network_hash','result','reason_code','risk_score','created_at' ),
\t\t);
\t}

\tprivate static function table_columns_ready( $table, array $required ) {
\t\tglobal $wpdb;
\t\tif ( '' === (string) $table ) { return false; }
\t\t$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\tif ( ! is_array( $columns ) || '' !== (string) $wpdb->last_error ) { return false; }
\t\treturn ! array_diff( $required, array_map( 'strval', $columns ) );
\t}

"""+anchor
s=one(s,anchor,insert,'base schema helper anchor')
old="""\t\tif ( $ok ) {
\t\t\tupdate_option( 'sauth_legacy_table_migration_version', SAUTH_DB_VERSION, false );
\t\t}
\t\treturn $ok;
"""
new="""\t\tif ( $ok ) {
\t\t\tupdate_option( 'sauth_legacy_table_migration_version', SAUTH_DB_VERSION, false );
\t\t\t$ok = SAUTH_DB_VERSION === (string) get_option( 'sauth_legacy_table_migration_version', '' );
\t\t}
\t\treturn $ok;
"""
s=one(s,old,new,'legacy migration marker postcondition')
old="""\t\tforeach ( self::required_tables() as $table ) {
\t\t\tif ( '' === $table ) {
\t\t\t\treturn false;
\t\t\t}
\t\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\t\tif ( $table !== (string) $exists ) {
\t\t\t\treturn false;
\t\t\t}
\t\t}
"""
new="""\t\t$required_columns = self::required_table_columns();
\t\tforeach ( self::required_tables() as $key => $table ) {
\t\t\tif ( '' === $table ) {
\t\t\t\treturn false;
\t\t\t}
\t\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\t\tif ( $table !== (string) $exists || ! isset( $required_columns[ $key ] ) || ! self::table_columns_ready( $table, $required_columns[ $key ] ) ) {
\t\t\t\treturn false;
\t\t\t}
\t\t}
"""
s=one(s,old,new,'storage schema postcondition')
write(p,s)

p='includes/class-sauth-passkeys.php'; s=read(p)
old="""\t\t$table_ready = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\tif ( ! $table_ready || ! self::manager_page_ready() ) { delete_option( self::OPTION_SCHEMA_VERSION ); return false; }
"""
new="""\t\t$table_ready = self::table_schema_ready();
\t\tif ( ! $table_ready || ! self::manager_page_ready() ) { delete_option( self::OPTION_SCHEMA_VERSION ); return false; }
"""
s=one(s,old,new,'passkey installation table postcondition')
old="""\tpublic static function installation_ready() {
\t\tif ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) || ! self::manager_page_ready() ) { return false; }
\t\tglobal $wpdb; $table = self::table();
\t\t$table_ready = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\t$cron_ready = function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( self::CLEANUP_HOOK );
\t\treturn $table_ready && $cron_ready;
\t}
"""
new="""\tpublic static function installation_ready() {
\t\tif ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) || ! self::manager_page_ready() ) { return false; }
\t\t$cron_ready = function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( self::CLEANUP_HOOK );
\t\treturn self::table_schema_ready() && $cron_ready;
\t}

\tprivate static function table_schema_ready() {
\t\tglobal $wpdb; $table = self::table();
\t\t$exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\tif ( ! $exists || '' !== (string) $wpdb->last_error ) { return false; }
\t\t$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t$required = array( 'id','public_id','user_id','credential_lookup_hash','credential_id_ciphertext','public_key_pem','algorithm','sign_count','nickname','attachment','transports','discoverable','backup_eligible','backup_state','hardware_backed','status','created_at','last_used_at','revoked_at','updated_at' );
\t\treturn is_array( $columns ) && '' === (string) $wpdb->last_error && ! array_diff( $required, array_map( 'strval', $columns ) );
\t}
"""
s=one(s,old,new,'passkey schema readiness')
write(p,s)

write('tests/r312-schema-postcondition-regression.php',r'''<?php
$root=dirname(__DIR__); $a=file_get_contents($root.'/includes/class-sa-activator.php'); $p=file_get_contents($root.'/includes/class-sauth-passkeys.php'); $fail=array();
$checks=array(
 array($a,'required_table_columns','base table schemas have no material-column postcondition'),
 array($a,'SHOW COLUMNS FROM','base storage readiness still proves names only'),
 array($a,"sauth_legacy_table_migration_version', ''","legacy migration marker write is not verified"),
 array($p,'table_schema_ready','passkey schema marker still proves table name only'),
 array($p,'credential_id_ciphertext','passkey schema does not require encrypted credential id column'),
 array($p,'hardware_backed','passkey schema does not require modern security metadata columns')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if($fail){fwrite(STDERR,"R312 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R312 schema postcondition regression PASS ('.count($checks)." assertions).\n";
''')
print('R312 frozen ledger corrections applied')
