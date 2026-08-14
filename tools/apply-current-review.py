#!/usr/bin/env python3
from pathlib import Path
import json, re

ROOT = Path(__file__).resolve().parents[1]
RELEASE = '1.2.2'
DB = '1.2.1'
PASSKEY_SCHEMA = '1.0.1'

def read(path): return (ROOT / path).read_text(encoding='utf-8')
def write(path, text): (ROOT / path).write_text(text, encoding='utf-8')
def replace(path, old, new, count=1):
    text = read(path)
    n = text.count(old)
    if n < count:
        raise SystemExit(f'{path}: patch point missing: {old[:100]!r}; found {n}')
    write(path, text.replace(old, new, count))
def replace_text(text, old, new, label, count=1):
    n=text.count(old)
    if n < count: raise SystemExit(f'{label}: expected >= {count}, found {n}')
    return text.replace(old,new,count)

# ---------------------------------------------------------------------------
# R320-1: truthful release/migration identity.
# ---------------------------------------------------------------------------
p='sabri-authentication.php'; s=read(p)
s=replace_text(s,' * Version: 1.2.1',' * Version: 1.2.2','plugin header')
s=replace_text(s,"define( 'SAUTH_VERSION', '1.2.1' );","define( 'SAUTH_VERSION', '1.2.2' );",'runtime version')
s=replace_text(s,"define( 'SAUTH_DB_VERSION', '1.2.0' );","define( 'SAUTH_DB_VERSION', '1.2.1' );",'db version')
write(p,s)

p='includes/class-sauth-passkeys.php'; s=read(p)
s=replace_text(s,"const SCHEMA_VERSION        = '1.0.0';","const SCHEMA_VERSION        = '1.0.1';",'passkey schema version')

# ---------------------------------------------------------------------------
# R320-2: material schema readiness must prove exact security-critical indexes.
# ---------------------------------------------------------------------------
old='''\tprivate static function table_schema_ready() {
\t\tglobal $wpdb; $table = self::table();
\t\t$exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\tif ( ! $exists || '' !== (string) $wpdb->last_error ) { return false; }
\t\t$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t$required = array( 'id','public_id','user_id','credential_lookup_hash','credential_id_ciphertext','public_key_pem','algorithm','sign_count','nickname','attachment','transports','discoverable','backup_eligible','backup_state','hardware_backed','status','created_at','last_used_at','revoked_at','updated_at' );
\t\treturn is_array( $columns ) && '' === (string) $wpdb->last_error && ! array_diff( $required, array_map( 'strval', $columns ) );
\t}
'''
new='''\tprivate static function table_schema_ready() {
\t\tglobal $wpdb; $table = self::table();
\t\t$exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
\t\tif ( ! $exists || '' !== (string) $wpdb->last_error ) { return false; }
\t\t$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\t$required = array( 'id','public_id','user_id','credential_lookup_hash','credential_id_ciphertext','public_key_pem','algorithm','sign_count','nickname','attachment','transports','discoverable','backup_eligible','backup_state','hardware_backed','status','created_at','last_used_at','revoked_at','updated_at' );
\t\tif ( ! is_array( $columns ) || '' !== (string) $wpdb->last_error || array_diff( $required, array_map( 'strval', $columns ) ) ) { return false; }
\t\t$required_indexes = array(
\t\t\t'PRIMARY'                => array( 0, array( 'id' ) ),
\t\t\t'public_id'              => array( 0, array( 'public_id' ) ),
\t\t\t'credential_lookup_hash' => array( 0, array( 'credential_lookup_hash' ) ),
\t\t\t'user_status'            => array( 1, array( 'user_id','status' ) ),
\t\t\t'revoked_at'             => array( 1, array( 'revoked_at' ) ),
\t\t);
\t\t$rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\tif ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) { return false; }
\t\t$actual = array();
\t\tforeach ( $rows as $row ) {
\t\t\t$name = (string) ( $row['Key_name'] ?? '' ); $seq = absint( $row['Seq_in_index'] ?? 0 );
\t\t\tif ( '' === $name || $seq < 1 ) { continue; }
\t\t\tif ( ! isset( $actual[ $name ] ) ) { $actual[ $name ] = array( 'non_unique'=>(int)( $row['Non_unique'] ?? 1 ), 'columns'=>array() ); }
\t\t\t$actual[ $name ]['columns'][ $seq ] = (string) ( $row['Column_name'] ?? '' );
\t\t}
\t\tforeach ( $required_indexes as $name => $spec ) {
\t\t\tif ( ! isset( $actual[ $name ] ) || (int) $actual[ $name ]['non_unique'] !== (int) $spec[0] ) { return false; }
\t\t\tksort( $actual[ $name ]['columns'] );
\t\t\tif ( array_values( $actual[ $name ]['columns'] ) !== $spec[1] ) { return false; }
\t\t}
\t\treturn true;
\t}
'''
s=replace_text(s,old,new,'passkey material index readiness')
write(p,s)

p='includes/class-sa-activator.php'; s=read(p)
anchor='''\tprivate static function table_columns_ready( $table, array $required ) {
'''
helpers='''\tprivate static function required_table_indexes() {
\t\treturn array(
\t\t\t'rate limits' => array(
\t\t\t\t'PRIMARY'=>array(0,array('bucket_hash')), 'expires_at'=>array(1,array('expires_at')),
\t\t\t),
\t\t\t'event outbox' => array(
\t\t\t\t'PRIMARY'=>array(0,array('id')), 'event_id'=>array(0,array('event_id')), 'dispatch_due'=>array(1,array('status','available_at','id')), 'subject_event'=>array(1,array('subject_user_id','event_name','created_at')), 'trace_id'=>array(1,array('trace_id')),
\t\t\t),
\t\t\t'email verification' => array(
\t\t\t\t'PRIMARY'=>array(0,array('user_id')), 'expiry_status'=>array(1,array('status','expires_at')), 'email_hash'=>array(1,array('email_hash')),
\t\t\t),
\t\t\t'session registry' => array(
\t\t\t\t'PRIMARY'=>array(0,array('id')), 'public_id'=>array(0,array('public_id')), 'user_token'=>array(0,array('user_id','token_hash')), 'user_status_seen'=>array(1,array('user_id','status','last_seen_at')), 'expiry_status'=>array(1,array('expires_at','status')),
\t\t\t),
\t\t\t'trusted devices' => array(
\t\t\t\t'PRIMARY'=>array(0,array('id')), 'public_id'=>array(0,array('public_id')), 'user_fingerprint'=>array(0,array('user_id','fingerprint_hash')), 'user_network_status'=>array(1,array('user_id','network_hash','status')), 'last_seen_at'=>array(1,array('last_seen_at')),
\t\t\t),
\t\t\t'risk challenges' => array(
\t\t\t\t'PRIMARY'=>array(0,array('id')), 'public_id'=>array(0,array('public_id')), 'token_hash'=>array(0,array('token_hash')), 'subject_status'=>array(1,array('user_id','status','expires_at')), 'expiry_status'=>array(1,array('expires_at','status')),
\t\t\t),
\t\t\t'auth attempts' => array(
\t\t\t\t'PRIMARY'=>array(0,array('id')), 'public_id'=>array(0,array('public_id')), 'subject_time'=>array(1,array('user_id','created_at')), 'result_time'=>array(1,array('result','created_at')),
\t\t\t),
\t\t);
\t}

\tprivate static function table_indexes_ready( $table, array $required ) {
\t\tglobal $wpdb;
\t\t$rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
\t\tif ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) { return false; }
\t\t$actual = array();
\t\tforeach ( $rows as $row ) {
\t\t\t$name = (string) ( $row['Key_name'] ?? '' ); $seq = absint( $row['Seq_in_index'] ?? 0 );
\t\t\tif ( '' === $name || $seq < 1 ) { continue; }
\t\t\tif ( ! isset( $actual[ $name ] ) ) { $actual[ $name ]=array('non_unique'=>(int)( $row['Non_unique'] ?? 1 ),'columns'=>array()); }
\t\t\t$actual[ $name ]['columns'][ $seq ] = (string) ( $row['Column_name'] ?? '' );
\t\t}
\t\tforeach ( $required as $name => $spec ) {
\t\t\tif ( ! isset( $actual[ $name ] ) || (int) $actual[ $name ]['non_unique'] !== (int) $spec[0] ) { return false; }
\t\t\tksort( $actual[ $name ]['columns'] );
\t\t\tif ( array_values( $actual[ $name ]['columns'] ) !== $spec[1] ) { return false; }
\t\t}
\t\treturn true;
\t}

'''+anchor
s=replace_text(s,anchor,helpers,'base index readiness helpers')
s=replace_text(s,"\t\t$required_columns = self::required_table_columns();\n\t\tforeach ( self::required_tables() as $key => $table ) {","\t\t$required_columns = self::required_table_columns();\n\t\t$required_indexes = self::required_table_indexes();\n\t\tforeach ( self::required_tables() as $key => $table ) {",'storage index map')
old="""\t\t\tif ( $table !== (string) $exists || ! isset( $required_columns[ $key ] ) || ! self::table_columns_ready( $table, $required_columns[ $key ] ) ) {
"""
new="""\t\t\tif ( $table !== (string) $exists || ! isset( $required_columns[ $key ], $required_indexes[ $key ] ) || ! self::table_columns_ready( $table, $required_columns[ $key ] ) || ! self::table_indexes_ready( $table, $required_indexes[ $key ] ) ) {
"""
s=replace_text(s,old,new,'storage material index postcondition')
write(p,s)

# ---------------------------------------------------------------------------
# R320-3/4: permanent release CI and real upgrade integration follow current truth.
# ---------------------------------------------------------------------------
p='.github/workflows/baseline-integrity.yml'; s=read(p)
s=s.replace("RELEASE_VERSION: '1.2.1'","RELEASE_VERSION: '1.2.2'")
s=s.replace("DB_VERSION: '1.2.0'","DB_VERSION: '1.2.1'")
s=s.replace("release = '1.2.1'","release = '1.2.2'")
s=s.replace("db = '1.2.0'","db = '1.2.1'")
s=s.replace("'passkey_schema_version': '1.0.0'","'passkey_schema_version': '1.0.1'")
s=s.replace("'tests/r308-route-ui-regression.php','tests/r309-release-truth-regression.php'","'tests/r308-route-ui-regression.php','tests/r309-release-truth-regression.php','tests/r320-final-release-regression.php'")
needle='''            tests/r309-release-truth-regression.php
          )
          for test_file in "${tests[@]}"; do php "$test_file"; done
'''
replacement='''            tests/r309-release-truth-regression.php
          )
          for extra in tests/r31*-regression.php tests/r32*-regression.php; do
            [ -e "$extra" ] && tests+=("$extra")
          done
          declare -A seen=()
          for test_file in "${tests[@]}"; do
            if [ -z "${seen[$test_file]+x}" ]; then php "$test_file"; seen[$test_file]=1; fi
          done
'''
s=replace_text(s,needle,replacement,'baseline cumulative R31-R32 suites')
write(p,s)

p='.github/workflows/canonical-storage-and-docs.yml'; s=read(p)
s=s.replace("php tests/r309-release-truth-regression.php","php tests/r309-release-truth-regression.php\n          for extra in tests/r31*-regression.php tests/r32*-regression.php; do [ -e \"$extra\" ] && php \"$extra\"; done")
s=s.replace("'README.md': ('1.2.1','review/file02-r301-r310-2026-08-14'","'README.md': ('1.2.2','review/file02-r311-r320-2026-08-14'")
s=s.replace("'STATUS.md': ('1.2.1','review/file02-r301-r310-2026-08-14'","'STATUS.md': ('1.2.2','review/file02-r311-r320-2026-08-14'")
s=s.replace("'RELEASE-MANIFEST.md': ('1.2.1'","'RELEASE-MANIFEST.md': ('1.2.2'")
s=s.replace("'ARCHITECTURE.md': ('1.2.1'","'ARCHITECTURE.md': ('1.2.2'")
s=s.replace("'CONTRACTS.md': ('1.2.1'","'CONTRACTS.md': ('1.2.2'")
s=s.replace("'MIGRATION.md': ('1.2.1','Fresh installation of 1.2.1'","'MIGRATION.md': ('1.2.2','Fresh installation of 1.2.2'")
s=s.replace("'STAGING-ACCEPTANCE.md': ('1.2.1'","'STAGING-ACCEPTANCE.md': ('1.2.2'")
write(p,s)

p='.github/workflows/file00-1.2.43-real-integration.yml'; s=read(p)
s=s.replace("FILE02_VERSION: '1.2.1'","FILE02_VERSION: '1.2.2'")
s=s.replace('!== "1.2.1" || (string)get_option("sauth_db_version","") !== "1.2.0"','!== "1.2.2" || (string)get_option("sauth_db_version","") !== "1.2.1"')
s=s.replace('SAUTH_VERSION !== "1.2.1"','SAUTH_VERSION !== "1.2.2"')
probe="""          /tmp/wp eval '$checks=SAUTH_Operations::system_check(); if(!is_array($checks)){exit(1);} echo "reload-2-ok\\n";' --path=/tmp/wordpress

      - name: Prove current File 00/File 02 boundaries
"""
upgrade="""          /tmp/wp eval '$checks=SAUTH_Operations::system_check(); if(!is_array($checks)){exit(1);} echo "reload-2-ok\\n";' --path=/tmp/wordpress

      - name: Rehearse legacy 1.2.1 passkey-column upgrade on real MariaDB
        run: |
          set -euo pipefail
          /tmp/wp eval 'global $wpdb; $t=$wpdb->prefix."sauth_passkeys"; $now=current_time("mysql",true); $wpdb->insert($t,array("public_id"=>"11111111-1111-4111-8111-111111111111","user_id"=>1,"credential_lookup_hash"=>str_repeat("a",64),"credential_id_ciphertext"=>"legacy-cipher","public_key_pem"=>"legacy-public-key","algorithm"=>"ES256","sign_count"=>0,"nickname"=>"Legacy credential","attachment"=>"platform","transports"=>"[]","discoverable"=>1,"backup_eligible"=>0,"backup_state"=>0,"hardware_backed"=>0,"status"=>"active","created_at"=>$now,"updated_at"=>$now)); if($wpdb->last_error){fwrite(STDERR,$wpdb->last_error."\\n");exit(1);}' --path=/tmp/wordpress
          /tmp/wp plugin deactivate sabri-authentication --path=/tmp/wordpress
          /tmp/wp eval 'global $wpdb; $t=$wpdb->prefix."sauth_passkeys"; if(false===$wpdb->query("ALTER TABLE `{$t}` CHANGE credential_lookup_hash credential_hash char(64) NOT NULL, CHANGE credential_id_ciphertext credential_cipher longtext NOT NULL")){fwrite(STDERR,$wpdb->last_error."\\n");exit(1);} update_option("sauth_version","1.2.1",false); update_option("sauth_db_version","1.2.0",false); update_option("sauth_passkey_schema_version","1.0.0",false);' --path=/tmp/wordpress
          /tmp/wp plugin activate sabri-authentication --path=/tmp/wordpress
          /tmp/wp eval 'global $wpdb; $t=$wpdb->prefix."sauth_passkeys"; $row=$wpdb->get_row("SELECT credential_lookup_hash,credential_id_ciphertext FROM `{$t}` WHERE public_id=\"11111111-1111-4111-8111-111111111111\"",ARRAY_A); if(!is_array($row)||$row["credential_lookup_hash"]!==str_repeat("a",64)||$row["credential_id_ciphertext"]!=="legacy-cipher"){fwrite(STDERR,"legacy passkey row not reconciled\\n");exit(1);} if((string)get_option("sauth_version","")!=="1.2.2"||(string)get_option("sauth_db_version","")!=="1.2.1"||(string)get_option("sauth_passkey_schema_version","")!=="1.0.1"){fwrite(STDERR,"upgraded markers invalid\\n");exit(1);} if(!SAUTH_Activator::storage_ready()||!SAUTH_Passkeys::installation_ready()){fwrite(STDERR,"upgraded material postconditions failed\\n");exit(1);}' --path=/tmp/wordpress

      - name: Prove current File 00/File 02 boundaries
"""
s=replace_text(s,probe,upgrade,'real MariaDB legacy passkey upgrade rehearsal')
s=s.replace('if (!defined("SAUTH_VERSION") || SAUTH_VERSION !== "1.2.2") { exit(1); } if (!defined("SAUTH_PASSKEY_CONTRACT_VERSION") || SAUTH_PASSKEY_CONTRACT_VERSION !== "1.0.0") { exit(1); }','if (!defined("SAUTH_VERSION") || SAUTH_VERSION !== "1.2.2") { exit(1); } if (!defined("SAUTH_DB_VERSION") || SAUTH_DB_VERSION !== "1.2.1") { exit(1); } if (SAUTH_Passkeys::SCHEMA_VERSION !== "1.0.1") { exit(1); } if (!defined("SAUTH_PASSKEY_CONTRACT_VERSION") || SAUTH_PASSKEY_CONTRACT_VERSION !== "1.0.0") { exit(1); }')
write(p,s)

# Permanent review gate must reject correction machinery after final closure.
p='.github/workflows/review-branch-integrity.yml'; s=read(p)
needle="""          echo 'Review head contains no installable archive artifact.'
"""
replacement="""          echo 'Review head contains no installable archive artifact.'
          if find .github/workflows -maxdepth 1 -type f -name '*correction*.yml' | grep -q .; then
            echo 'Temporary write-capable correction workflow remains in final review source.' >&2
            exit 1
          fi
          if [ -d .github/forty-round-payload ]; then
            echo 'Historical corrective payload directory remains in final review source.' >&2
            exit 1
          fi
"""
s=replace_text(s,needle,replacement,'permanent correction-artifact boundary')
write(p,s)

# ---------------------------------------------------------------------------
# R320-5: current release-facing truth.
# ---------------------------------------------------------------------------
p='RELEASE-LOCK.json'; data=json.loads(read(p))
data['release_version']=RELEASE; data['database_version']=DB; data['passkey_schema_version']=PASSKEY_SCHEMA
data['package_name']=f'02-sabri-authentication-{RELEASE}-SOURCE-CANDIDATE.zip'
data['manifest_name']=f'02-sabri-authentication-{RELEASE}-MANIFEST.json'
data['review_line']='R311-R320-complete'
data['r320_review_baseline']='fe66788cb6c12e966bb198b361347cb74ec490b0'
data['status']['coded']='review_candidate_r320_corrected'
write(p,json.dumps(data,indent=2,ensure_ascii=False)+'\n')

p='README.md'; s=read(p)
s=s.replace('**Source candidate:** `1.2.1`','**Source candidate:** `1.2.2`')
s=s.replace('**Database schema:** `1.2.0`','**Database schema:** `1.2.1`')
s=s.replace('## Version 1.2.1 completion scope','## Version 1.2.2 completion scope')
old='Version 1.2.1 preserves the complete 1.2.0 four-plan feature set and corrects one WordPress-integration-proven bootstrap defect: `SAUTH_Storage_Router::init()` was invoked after activation while `includes/class-sauth-storage-router.php` had not been required by the main plugin bootstrap. The storage-router source file is now loaded before `sauth_start_plugin()` can run, and permanent exact-head/package/cross-repository checks cover the boundary.'
new='Version 1.2.2 carries the 1.2.1 bootstrap correction plus the sequential R311–R320 security, migration, passkey, provider, session, privacy, route/UI, dependency-contract and release-truth hardening. DB identity advances to `1.2.1` and passkey schema identity to `1.0.1` so supported upgrades explicitly reconcile canonical columns and security-critical indexes before successful markers are published.'
s=replace_text(s,old,new,'README current release scope')
write(p,s)

p='STATUS.md'; s=read(p)
s=s.replace('# File 02 Status — Version 1.2.1','# File 02 Status — Version 1.2.2')
s=s.replace('- Version/schema: `1.2.1 / 1.2.0`; passkey table schema `1.0.0`','- Version/schema: `1.2.2 / 1.2.1`; passkey table schema `1.0.1`')
s=s.replace('- File 02 1.2.1 loads `class-sauth-storage-router.php` before `sauth_start_plugin()` can invoke `SAUTH_Storage_Router::init()`.','- File 02 1.2.2 retains the 1.2.1 storage-router bootstrap correction and adds R311–R320 fail-closed, migration/index, passkey, provider, privacy, session, UI and release-truth hardening.')
s=s.replace('R319–R320 are the current closing review line.','R319 completed dependency/release-truth hardening; R320 completed the final adversarial review and corrective release-identity/index/CI cleanup.')
write(p,s)

p='PLAN-TRACEABILITY.md'; s=read(p)
s=s.replace('**Candidate version/schema:** `1.2.1 / 1.2.0`; passkey schema `1.0.0`','**Candidate version/schema:** `1.2.2 / 1.2.1`; passkey schema `1.0.1`')
write(p,s)

p='RELEASE-MANIFEST.md'; s=read(p)
s=s.replace('# File 02 — Release Manifest — 1.2.1','# File 02 — Release Manifest — 1.2.2')
s=s.replace('Candidate version/schema: `1.2.1 / 1.2.0`','Candidate version/schema: `1.2.2 / 1.2.1`')
s=s.replace('Passkey schema/assurance: `1.0.0 / 1.0.0`','Passkey schema/assurance: `1.0.1 / 1.0.0`')
s=s.replace('02-sabri-authentication-1.2.1-SOURCE-CANDIDATE.zip','02-sabri-authentication-1.2.2-SOURCE-CANDIDATE.zip')
s=s.replace('02-sabri-authentication-1.2.1-MANIFEST.json','02-sabri-authentication-1.2.2-MANIFEST.json')
s=s.replace('build the 1.2.1 package twice','build the 1.2.2 package twice')
s=s.replace('The 1.2.1 source candidate preserves','The 1.2.2 source candidate preserves')
marker='## Patch correction\n'
summary='''## Current hardening release\n\nVersion 1.2.2 is the repository/source identity for the completed R311–R320 corrective line. It advances DB identity to 1.2.1 and passkey schema identity to 1.0.1, requires material columns plus security-critical indexes before migration readiness, reconciles legacy passkey columns non-destructively, and carries the permanent regressions and real File 00/MariaDB upgrade gate. This source identity is not a staging/live claim.\n\n'''
s=replace_text(s,marker,summary+marker,'manifest R320 release summary')
write(p,s)

p='ARCHITECTURE.md'; s=read(p)
s=s.replace('# File 02 Architecture — Authentication and Accounts 1.2.1','# File 02 Architecture — Authentication and Accounts 1.2.2')
s=s.replace('passkey table has its own additive schema version `1.0.0`','passkey table has its own additive schema version `1.0.1`')
s=s.replace('not the active 1.2.0 source of truth','not the active DB 1.2.1 source of truth')
write(p,s)

p='STAGING-ACCEPTANCE.md'; s=read(p).replace('# File 02 Staging Acceptance — Version 1.2.1','# File 02 Staging Acceptance — Version 1.2.2',1); write(p,s)

p='MIGRATION.md'; s=read(p)
s=s.replace('# File 02 Migration Guide — 1.2.1','# File 02 Migration Guide — 1.2.2')
old='File 02 migration remains additive, idempotent and non-destructive. The 1.2.1 candidate preserves the seven canonical authentication tables from 1.1.0 and the additive `sauth_passkeys` schema `1.0.0` introduced in 1.2.0, while correcting bootstrap and review-discovered lifecycle controls without changing DB schema 1.2.0.'
new='File 02 migration remains additive, idempotent and non-destructive. The 1.2.2 candidate advances File 02 DB identity to `1.2.1` and passkey schema identity to `1.0.1`; migration readiness now proves required columns and security-critical indexes, and legacy passkey credential columns are reconciled before successful markers are published.'
s=replace_text(s,old,new,'migration current identity')
s=s.replace('Fresh installation of 1.2.1','Fresh installation of 1.2.2')
s=s.replace('to 1.2.1.','to 1.2.2.')
s=s.replace('to 1.2.1 with','to 1.2.2 with')
s=s.replace('deterministic 1.2.1 package','deterministic 1.2.2 package')
s=s.replace('`sauth_version` is `1.2.1`, `sauth_db_version` remains `1.2.0`, and `sauth_passkey_schema_version` is `1.0.0`','`sauth_version` is `1.2.2`, `sauth_db_version` is `1.2.1`, and `sauth_passkey_schema_version` is `1.0.1`')
write(p,s)

for p in ('DATA-DICTIONARY.md','CONTRACTS.md','BACKUP-RESTORE.md','ROLLBACK.md','THREAT-MODEL.md','PRIVACY-RETENTION.md'):
    s=read(p)
    # Only promote a title/current-candidate occurrence; historical version mentions remain historical.
    lines=s.splitlines()
    if lines and '1.2.1' in lines[0]: lines[0]=lines[0].replace('1.2.1','1.2.2')
    s='\n'.join(lines)+('\n' if read(p).endswith('\n') else '')
    if p=='DATA-DICTIONARY.md': s=s.replace('schema version `1.0.0`','schema version `1.0.1`')
    write(p,s)

p='readme.txt'; s=read(p)
s=s.replace('Stable tag: 1.2.1','Stable tag: 1.2.2')
old='Version 1.2.1 contains the repository correction for a bootstrap defect proven by a real File 00/File 02 WordPress integration run against 1.2.0. A real File 00/File 02 WordPress integration run proved that 1.2.0 could pass its File 00 dependency activation gate and then fatal on the next request because `SAUTH_Storage_Router::init()` was called without loading `class-sauth-storage-router.php`. Version 1.2.1 corrects that exact bootstrap defect without changing the File 02 DB schema or ownership contracts. Source completion and automated QA do not by themselves prove Hostinger staging acceptance, live deployment or operational acceptance.'
new='Version 1.2.2 is the current R311–R320 repository/source candidate. It retains the 1.2.1 storage-router bootstrap correction and adds fail-closed risk/provider/privacy/session hardening, canonical passkey migration with material index postconditions, hardened assurance consumption, route/UI corrections and current release/integration gates. DB identity is 1.2.1 and passkey schema identity is 1.0.1. Source/CI completion does not by itself prove Hostinger staging, deployment or operations.'
s=replace_text(s,old,new,'readme current release status')
marker='= 1.2.1 =\n'
section='''= 1.2.2 =\n* Completes the sequential R311–R320 review/fix/retest line.\n* Advances File 02 DB identity to 1.2.1 and passkey schema identity to 1.0.1 so the hardened migration is explicitly identifiable.\n* Proves required authentication/passkey columns and security-critical indexes before migration readiness.\n* Reconciles legacy `credential_hash` / `credential_cipher` passkey rows into canonical columns non-destructively and adds a real MariaDB/File 00 upgrade rehearsal.\n* Hardens risk, provider HTTPS/circuit behavior, email recovery, passkey lifecycle, assurance/session, privacy, routes/UI and release-truth gates.\n* Removes temporary write-capable correction workflows/payloads from the final review candidate.\n* Preserves the external File 00 taxonomy/provider-vocabulary blocker as an explicit owner-side release gate; File 02 performs no lossy remap.\n\n'''
s=replace_text(s,marker,section+marker,'readme 1.2.2 changelog')
write(p,s)

p='CHANGELOG.md'; s=read(p)
marker='## 1.2.1 — WordPress-Integration-Proven Storage Router Bootstrap Correction\n'
section='''## 1.2.2 — R311–R320 Final Corrective Hardening Candidate\n\n### Corrected\n\n- Fail-closed risk-storage and provider-HTTPS boundaries; email/recovery provider-circuit behavior; Google half-open probe ownership.\n- Material DB/page/passkey migration postconditions, including canonical passkey-column reconciliation and security-critical index/uniqueness proof.\n- Hardened passkey runtime, Safe Mode ceremony completion, credential quarantine and epoch-aware assurance consumption.\n- Session-revocation, privacy export/erasure/anonymization and operational-system-check postconditions.\n- Canonical route/redirect encoding, evidence-honest provider UI, touch targets and release/dependency documentation truth.\n- Permanent PHP 7.4/8.3 cumulative regressions plus current File 00/real MariaDB fresh-install and legacy passkey upgrade integration.\n- Removal of temporary correction workflows and historical corrective payload files from the final candidate.\n\n### Identity\n\n- Runtime: `1.2.2`.\n- File 02 DB schema identity: `1.2.1`.\n- Passkey schema identity: `1.0.1`; passkey assurance contract remains `1.0.0`.\n- Staging/live/operational completion remains unclaimed.\n\n'''
s=replace_text(s,marker,section+marker,'CHANGELOG 1.2.2 section')
write(p,s)

p='SBOM.spdx.json'; data=json.loads(read(p))
data['name']='Sabri Authentication and Accounts 1.2.2 SBOM'
data['documentNamespace']='https://sabrihomeopathy.com/spdx/file02-authentication-and-accounts/1.2.2/2026-08-14'
data['creationInfo']['created']='2026-08-14T14:30:00Z'
for package in data.get('packages',[]):
    if package.get('SPDXID')=='SPDXRef-Package-File02':
        package['versionInfo']='1.2.2'
        for ref in package.get('externalRefs',[]):
            if ref.get('referenceType')=='purl': ref['referenceLocator']='pkg:wordpress/sabri-authentication@1.2.2'
        package['comment']='R311–R320 repository/source hardening candidate. Runtime 1.2.2 / DB 1.2.1 / passkey schema 1.0.1; includes canonical legacy-passkey migration, material index postconditions and current exact-head/integration gates. Repository CI evidence is not a staging or live-deployment claim.'
write(p,json.dumps(data,indent=2,ensure_ascii=False)+'\n')

p='REVIEW-REPORT.md'; s=read(p)
banner='''> **Historical evidence notice:** This root report records the earlier 1.1.0 four-round candidate and is retained for provenance only. It is **not** the current release/review status. Current source truth is the R311–R320 review line and version 1.2.2 / DB 1.2.1 / passkey schema 1.0.1, subject to the separate release/staging/live gates.\n\n'''
if not s.startswith('> **Historical evidence notice:**'): s=banner+s
write(p,s)

# ---------------------------------------------------------------------------
# R320-6: remove temporary write-capable correction machinery/payloads.
# ---------------------------------------------------------------------------
for rel in (
    '.github/workflows/r310-batch-correction.yml',
    '.github/workflows/r310-v3-correction.yml',
    '.github/workflows/r311-r320-correction.yml',
    '.github/forty-round-payload/part-01.txt',
    '.github/forty-round-payload/small-01.txt',
    '.github/forty-round-payload/small-02.txt',
):
    path=ROOT/rel
    if path.exists(): path.unlink()

# ---------------------------------------------------------------------------
# R320-7: durable final regression.
# ---------------------------------------------------------------------------
write('tests/r320-final-release-regression.php',r'''<?php
$root=dirname(__DIR__); $main=file_get_contents($root.'/sabri-authentication.php'); $a=file_get_contents($root.'/includes/class-sa-activator.php'); $p=file_get_contents($root.'/includes/class-sauth-passkeys.php'); $baseline=file_get_contents($root.'/.github/workflows/baseline-integrity.yml'); $docs=file_get_contents($root.'/.github/workflows/canonical-storage-and-docs.yml'); $integration=file_get_contents($root.'/.github/workflows/file00-1.2.43-real-integration.yml'); $review=file_get_contents($root.'/.github/workflows/review-branch-integrity.yml'); $lock=file_get_contents($root.'/RELEASE-LOCK.json'); $readme=file_get_contents($root.'/readme.txt'); $report=file_get_contents($root.'/REVIEW-REPORT.md'); $fail=array();
$checks=array(
 array($main,"Version: 1.2.2",'plugin header release identity stale'), array($main,"SAUTH_VERSION', '1.2.2",'runtime release identity stale'), array($main,"SAUTH_DB_VERSION', '1.2.1",'DB migration identity stale'),
 array($p,"SCHEMA_VERSION        = '1.0.1'",'passkey migration identity stale'), array($p,'SHOW INDEX FROM','passkey readiness does not prove indexes'), array($p,"'credential_lookup_hash' => array( 0",'passkey lookup uniqueness is not a material postcondition'),
 array($a,'required_table_indexes','base storage does not define material index postconditions'), array($a,'table_indexes_ready','base storage does not verify indexes'), array($a,"'user_token'=>array(0",'session token uniqueness is not a material postcondition'), array($a,"'token_hash'=>array(0",'risk token uniqueness is not a material postcondition'),
 array($baseline,"RELEASE_VERSION: '1.2.2'",'release CI version stale'), array($baseline,"DB_VERSION: '1.2.1'",'release CI DB version stale'), array($baseline,'tests/r31*-regression.php','release CI omits current corrective line'),
 array($docs,"review/file02-r311-r320-2026-08-14",'documentation CI branch truth stale'), array($docs,"'1.2.2'",'documentation CI version truth stale'),
 array($integration,"FILE02_VERSION: '1.2.2'",'real integration version stale'), array($integration,'Rehearse legacy 1.2.1 passkey-column upgrade on real MariaDB','real integration lacks supported legacy passkey upgrade rehearsal'), array($integration,'sauth_passkey_schema_version","1.0.0','legacy passkey schema input is not exercised'),
 array($review,'Temporary write-capable correction workflow remains','permanent review gate does not reject correction machinery'), array($lock,'"release_version": "1.2.2"','release lock version stale'), array($lock,'"database_version": "1.2.1"','release lock DB version stale'), array($lock,'"passkey_schema_version": "1.0.1"','release lock passkey schema stale'),
 array($readme,'Stable tag: 1.2.2','WordPress readme stable tag stale'), array($report,'Historical evidence notice','historical root review report can be mistaken for current evidence')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
foreach(array('.github/workflows/r310-batch-correction.yml','.github/workflows/r310-v3-correction.yml','.github/workflows/r311-r320-correction.yml') as $f){if(file_exists($root.'/'.$f))$fail[]='temporary correction workflow remains: '.$f;}
if(is_dir($root.'/.github/forty-round-payload'))$fail[]='historical correction payload directory remains';
if($fail){fwrite(STDERR,"R320 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R320 final release regression PASS ('.(count($checks)+4)." assertions).\n";
''')

print('R320 frozen final ledger corrections applied')
