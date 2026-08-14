#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(path): return (ROOT / path).read_text(encoding='utf-8')
def write(path, text): (ROOT / path).write_text(text, encoding='utf-8')
def one(pattern, text, label, flags=re.S):
    rx = re.compile(pattern, flags)
    found = list(rx.finditer(text))
    if len(found) != 1: raise SystemExit(f'{label}: expected 1 match, found {len(found)}')
    return rx

# SECURITY
p='includes/class-sa-security.php'; s=read(p)
if 'const NOTICE_TTL = 600;' not in s: s=s.replace('final class SA_Security {\n','final class SA_Security {\n\tconst NOTICE_TTL = 600;\n',1)
rate=one(r"(\tpublic static function rate_limited\(.*?)(\n\tpublic static function clear_rate_limit\()",s,'rate_limited')
block=rate.search(s).group(1)
old=one(r"\t\tif \( false === \$result \) \{.*?\t\t\$hits = \(int\) \$wpdb->get_var\( \$wpdb->prepare\( \"SELECT hits FROM \{\$table\} WHERE bucket_hash = %s\", \$bucket \) \);",block,'rate fallback')
replacement="""\t\tif ( false === $result ) {
\t\t\t$key = 'sauth_fallback_' . substr( $bucket, 0, 32 );
\t\t\t$state = get_transient( $key );
\t\t\t$now_ts = time();
\t\t\tif ( ! is_array( $state ) || empty( $state['expires'] ) || (int) $state['expires'] <= $now_ts ) { $state = array( 'hits' => 0, 'expires' => $now_ts + $window ); }
\t\t\t$state['hits']++;
\t\t\t$ttl = max( 1, (int) $state['expires'] - $now_ts );
\t\t\t$stored = set_transient( $key, $state, $ttl );
\t\t\t$verified = get_transient( $key );
\t\t\tif ( false === $stored || ! is_array( $verified ) || (int) ( $verified['hits'] ?? -1 ) !== (int) $state['hits'] || (int) ( $verified['expires'] ?? 0 ) !== (int) $state['expires'] ) { return true; }
\t\t\treturn $state['hits'] > $limit;
\t\t}
\t\t$hits_raw = $wpdb->get_var( $wpdb->prepare( \"SELECT hits FROM {$table} WHERE bucket_hash = %s\", $bucket ) );
\t\tif ( null === $hits_raw || '' !== (string) $wpdb->last_error ) { return true; }
\t\t$hits = (int) $hits_raw;"""
block=old.sub(replacement,block,count=1); s=rate.sub(block+r"\2",s,count=1)
notice=one(r"\tpublic static function message_url\(.*?(?=\n\tpublic static function page_url\()",s,'notice block')
notice_new="""\tpublic static function message_url( $page_key, $type, $message, array $extra = array() ) {
\t\treturn add_query_arg( array_merge( $extra, self::notice_query_args( $type, $message ) ), self::page_url( $page_key ) );
\t}

\tpublic static function notice_query_args( $type, $message ) {
\t\t$type = 'success' === sanitize_key( $type ) ? 'success' : 'error';
\t\t$message = sanitize_text_field( (string) $message );
\t\t$issued_at = time();
\t\treturn array( 'sa_notice' => $type, 'sa_msg' => $message, 'sa_iat' => $issued_at, 'sa_sig' => self::notice_signature( $type, $message, $issued_at ) );
\t}

\tpublic static function notice_valid( $type, $message, $signature, $issued_at = 0 ) {
\t\t$type = 'success' === sanitize_key( $type ) ? 'success' : 'error';
\t\t$message = sanitize_text_field( (string) $message );
\t\t$signature = sanitize_text_field( (string) $signature );
\t\t$issued_at = absint( $issued_at );
\t\t$age = time() - $issued_at;
\t\treturn 64 === strlen( $signature ) && $issued_at > 0 && $age >= 0 && $age <= self::NOTICE_TTL && hash_equals( self::notice_signature( $type, $message, $issued_at ), $signature );
\t}

\tpublic static function request_notice() {
\t\tif ( ! isset( $_GET['sa_notice'], $_GET['sa_msg'], $_GET['sa_sig'], $_GET['sa_iat'] ) ) { return array(); }
\t\t$type = 'success' === sanitize_key( wp_unslash( $_GET['sa_notice'] ) ) ? 'success' : 'error';
\t\t$message = sanitize_text_field( wp_unslash( $_GET['sa_msg'] ) );
\t\t$signature = sanitize_text_field( wp_unslash( $_GET['sa_sig'] ) );
\t\t$issued_at = absint( wp_unslash( $_GET['sa_iat'] ) );
\t\treturn self::notice_valid( $type, $message, $signature, $issued_at ) ? array( 'type' => $type, 'message' => $message ) : array();
\t}

\tprivate static function notice_signature( $type, $message, $issued_at ) {
\t\treturn hash_hmac( 'sha256', sanitize_key( $type ) . '|' . sanitize_text_field( (string) $message ) . '|' . absint( $issued_at ), wp_salt( 'nonce' ) );
\t}
"""
s=notice.sub(notice_new,s,count=1)
if 'public static function current_cipher_ready' not in s:
    anchor='\n\tprivate static function decrypt_gcm_payload( $payload, $key, $aad ) {'
    if anchor not in s: raise SystemExit('cipher helper anchor missing')
    s=s.replace(anchor,"\n\tpublic static function current_cipher_ready( $cipher ) { $cipher = (string) $cipher; return self::master_key_ready() && 0 === strpos( $cipher, 'v3:' ) && '' !== self::decrypt( $cipher ); }\n"+anchor,1)
write(p,s)
write('templates/partials/notice.php',"""<?php defined( 'ABSPATH' ) || exit; ?>
<?php $sauth_notice = SA_Security::request_notice(); ?>
<?php if ( ! empty( $sauth_notice ) ) : ?>
<div class="sa-notice sa-notice-<?php echo esc_attr( $sauth_notice['type'] ); ?>" role="status"><?php echo esc_html( $sauth_notice['message'] ); ?></div>
<?php endif; ?>
""")

# PASSKEY INSTALL/PAGE
p='includes/class-sauth-passkeys.php'; s=read(p)
rx=one(r"\tpublic static function maybe_install\( \$force = false \) \{.*?(?=\n\tprivate static function ensure_manager_page\()",s,'maybe_install')
install=r'''	public static function maybe_install( $force = false ) {
		if ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) { return false; }
		if ( ! $force && self::installation_ready() ) { return true; }
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table(); $charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			user_id bigint unsigned NOT NULL,
			credential_lookup_hash char(64) NOT NULL,
			credential_id_ciphertext longtext NOT NULL,
			public_key_pem longtext NOT NULL,
			algorithm varchar(20) NOT NULL DEFAULT 'ES256',
			sign_count bigint unsigned NOT NULL DEFAULT 0,
			nickname varchar(120) NOT NULL DEFAULT '',
			attachment varchar(32) NOT NULL DEFAULT '',
			transports text NOT NULL,
			discoverable tinyint(1) NOT NULL DEFAULT 1,
			backup_eligible tinyint(1) NOT NULL DEFAULT 0,
			backup_state tinyint(1) NOT NULL DEFAULT 0,
			hardware_backed tinyint(1) NOT NULL DEFAULT 0,
			status varchar(24) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			last_used_at datetime DEFAULT NULL,
			revoked_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), UNIQUE KEY credential_lookup_hash (credential_lookup_hash), KEY user_status (user_id,status), KEY revoked_at (revoked_at)
		) {$charset};";
		dbDelta( $sql ); self::ensure_manager_page();
		$table_ready = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $table_ready || ! self::manager_page_ready() ) { delete_option( self::OPTION_SCHEMA_VERSION ); return false; }
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
		if ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) { return false; }
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
			if ( false === $scheduled || is_wp_error( $scheduled ) ) { return false; }
		}
		return self::installation_ready();
	}

	public static function installation_ready() {
		if ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) || ! self::manager_page_ready() ) { return false; }
		global $wpdb; $table = self::table();
		$table_ready = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$cron_ready = function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( self::CLEANUP_HOOK );
		return $table_ready && $cron_ready;
	}
'''
s=rx.sub(install,s,count=1)
rx=one(r"\tprivate static function ensure_manager_page\(\) \{.*?(?=\n\tpublic static function manager_url\()",s,'manager page')
manager=r'''	private static function ensure_manager_page() {
		$map = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) );
		$page_id = isset( $map['passkeys'] ) ? absint( $map['passkeys'] ) : 0;
		$page = $page_id ? get_post( $page_id ) : null;
		if ( self::is_manager_page( $page ) ) { return $page_id; }
		$candidates = get_posts( array( 'post_type'=>'page', 'post_status'=>array('publish','draft','private','pending'), 'posts_per_page'=>50, 'orderby'=>'ID', 'order'=>'ASC', 'meta_query'=>array('relation'=>'OR', array('key'=>'_sauth_managed_page','value'=>'1'), array('key'=>'_sa_private_page','value'=>'1'), array('key'=>'_sauth_private_page','value'=>'1')) ) );
		foreach ( is_array( $candidates ) ? $candidates : array() as $candidate ) {
			if ( self::is_manager_page( $candidate, true ) ) { $page_id=absint($candidate->ID); self::mark_manager_page($page_id); $map['passkeys']=$page_id; update_option('sauth_page_map',$map,false); return $page_id; }
		}
		$page_id = wp_insert_post( array( 'post_title'=>'Passkeys & Security Keys', 'post_name'=>'account-passkeys', 'post_content'=>'[sabri_auth_passkeys]', 'post_status'=>'publish', 'post_type'=>'page', 'meta_input'=>array('_sauth_managed_page'=>'1','_sauth_private_page'=>'1') ), true );
		if ( is_wp_error($page_id) || !absint($page_id) ) { return 0; }
		$page_id=absint($page_id); self::mark_manager_page($page_id); $map['passkeys']=$page_id; update_option('sauth_page_map',$map,false); return $page_id;
	}
	private static function is_manager_page( $page, $legacy_allowed = false ) {
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status || '[sabri_auth_passkeys]' !== trim((string)$page->post_content) ) { return false; }
		$canonical='1'===(string)get_post_meta($page->ID,'_sauth_managed_page',true);
		$legacy='1'===(string)get_post_meta($page->ID,'_sa_private_page',true) || '1'===(string)get_post_meta($page->ID,'_sauth_private_page',true);
		return $canonical || ($legacy_allowed && $legacy);
	}
	private static function mark_manager_page( $page_id ) { $page_id=absint($page_id); if(!$page_id){return;} update_post_meta($page_id,'_sauth_managed_page','1'); update_post_meta($page_id,'_sauth_private_page','1'); delete_post_meta($page_id,'_sa_private_page'); }
	private static function manager_page_ready() { $map=(array)get_option('sauth_page_map',array()); $page_id=isset($map['passkeys'])?absint($map['passkeys']):0; return $page_id>0 && self::is_manager_page(get_post($page_id)); }
'''
s=rx.sub(manager,s,count=1); write(p,s)

# ACTIVATOR
p='includes/class-sa-activator.php'; s=read(p)
repair=one(r"\tpublic static function repair\(\) \{.*?(?=\n\tprivate static function acquire_migration_lock\()",s,'repair')
rblock=repair.search(s).group(0)
if '$google_secret_ok' not in rblock:
    rblock=rblock.replace("\t\tself::migrate_google_secret();\n\t\tself::ensure_dummy_password_hash();", "\t\t$google_secret_ok = self::migrate_google_secret();\n\t\tself::ensure_dummy_password_hash();\n\t\t$passkey_ok = class_exists( 'SAUTH_Passkeys' ) && SAUTH_Passkeys::maybe_install( true );",1)
    rblock=rblock.replace("if ( ! $migration_ok || ! self::storage_ready() )", "if ( ! $migration_ok || ! $google_secret_ok || ! $passkey_ok || ! self::storage_ready() )",1)
s=repair.sub(rblock,s,count=1)
storage=one(r"\tpublic static function storage_ready\(\) \{.*?\n\t\}",s,'storage_ready')
sblock=storage.search(s).group(0)
if 'SAUTH_Passkeys::installation_ready()' not in sblock:
    pos=sblock.rfind("\t\treturn true;")
    if pos<0: raise SystemExit('storage_ready final return missing')
    sblock=sblock[:pos]+"\t\tif ( ! class_exists( 'SAUTH_Passkeys' ) || ! SAUTH_Passkeys::installation_ready() ) { return false; }\n"+sblock[pos:]
s=storage.sub(sblock,s,count=1)
mig=one(r"\tprivate static function migrate_google_secret\(\) \{.*?(?=\n\tprivate static function ensure_dummy_password_hash\()",s,'migrate_google_secret')
mig_new=r'''	private static function migrate_google_secret() {
		$cipher=(string)get_option('sauth_google_client_secret',get_option('sa_google_client_secret',''));
		if(''===$cipher){return true;} if(SA_Security::current_cipher_ready($cipher)){return true;} if(!SA_Security::master_key_ready()){return false;}
		$plain=SA_Security::decrypt($cipher); if(''===$plain){return false;} $encrypted=SA_Security::encrypt($plain); $plain=''; if(!SA_Security::current_cipher_ready($encrypted)){return false;}
		update_option('sauth_google_client_secret',$encrypted,false); update_option('sa_google_client_secret',$encrypted,false);
		$canonical=(string)get_option('sauth_google_client_secret',''); $mirror=(string)get_option('sa_google_client_secret','');
		return hash_equals($encrypted,$canonical) && hash_equals($encrypted,$mirror) && SA_Security::current_cipher_ready($canonical);
	}
'''
s=mig.sub(mig_new,s,count=1); write(p,s)

p='sabri-authentication.php'; s=read(p); s=s.replace("register_activation_hook( SAUTH_FILE, array( 'SAUTH_Passkeys', 'maybe_install' ) );\n",'',1); write(p,s)

# GOOGLE CONFIG + SETTINGS
p='includes/class-sa-google-oauth.php'; s=read(p)
conf=one(r"\tpublic static function configured\(\) \{.*?\n\t\}",s,'google configured')
cblock=conf.search(s).group(0)
if 'current_cipher_ready' not in cblock:
    cblock=cblock.replace("&& '' !== self::client_secret();", "&& SA_Security::current_cipher_ready( (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) ) )\n\t\t\t&& '' !== self::client_secret();",1)
s=conf.sub(cblock,s,count=1); write(p,s)
p='includes/class-sa-plugin.php'; s=read(p); s=s.replace("'' === $encrypted || '' === SA_Security::decrypt( $encrypted )", "! SA_Security::current_cipher_ready( $encrypted )",1); write(p,s)

# OPERATIONS
p='includes/class-sauth-operations.php'; s=read(p)
run=one(r"\tpublic static function run_repair\(\) \{.*?(?=\n\t/\*\* Current source/runtime checks only; staging/live acceptance is separate\. \*/)",s,'run_repair')
rblock=run.search(s).group(0)
try_i=rblock.find("\n\t\ttry {"); fin_i=rblock.find("\n\t\t} finally {")
if try_i<0 or fin_i<0: raise SystemExit('run_repair try/finally missing')
pre=rblock[:try_i]; mid=rblock[try_i:fin_i]; post=rblock[fin_i:]
mid=re.sub(r"(?<!self::release_repair_lock\( \$lock \); )self::redirect\(", "self::release_repair_lock( $lock ); self::redirect(", mid)
rblock=pre+mid+post; s=run.sub(rblock,s,count=1)
redir=one(r"\tprivate static function redirect\( \$type, \$message \) \{.*?\}",s,'operations redirect')
s=redir.sub("\tprivate static function redirect( $type, $message ) { $target = admin_url( 'admin.php?page=sabri-authentication-system-check' ); wp_safe_redirect( add_query_arg( SA_Security::notice_query_args( $type, $message ), $target ) ); exit; }",s,count=1)
if 'SA_Security::request_notice(); if ( ! empty( $sauth_notice ) )' not in s:
    h="\t\t\t<h1><?php echo esc_html__( 'Sabri Authentication — System Check', 'sabri-authentication' ); ?></h1>\n"
    if h not in s: raise SystemExit('system check heading missing')
    s=s.replace(h,h+"\t\t\t<?php $sauth_notice = SA_Security::request_notice(); if ( ! empty( $sauth_notice ) ) : ?><div class=\"notice notice-<?php echo 'success' === $sauth_notice['type'] ? 'success' : 'error'; ?>\"><p><?php echo esc_html( $sauth_notice['message'] ); ?></p></div><?php endif; ?>\n",1)
write(p,s)

# SECURITY UNIT
p='tests/security-unit.php'; s=read(p)
s=s.replace("SA_Security::notice_valid( $notice_args['sa_notice'] ?? '', $notice_args['sa_msg'] ?? '', $notice_args['sa_sig'] ?? '' )", "SA_Security::notice_valid( $notice_args['sa_notice'] ?? '', $notice_args['sa_msg'] ?? '', $notice_args['sa_sig'] ?? '', $notice_args['sa_iat'] ?? 0 )")
s=s.replace("SA_Security::notice_valid( 'success', 'Forged success', $notice_args['sa_sig'] ?? '' )", "SA_Security::notice_valid( 'success', 'Forged success', $notice_args['sa_sig'] ?? '', $notice_args['sa_iat'] ?? 0 )")
if 'current_cipher_ready( $cipher )' not in s:
    a="sa_test_assert( $plain === SA_Security::decrypt( $cipher ), 'AES-256-GCM dedicated-key round trip failed' );\n"; s=s.replace(a,a+"sa_test_assert( SA_Security::current_cipher_ready( $cipher ), 'v3 dedicated-key ciphertext was not accepted as current' );\n",1)
write(p,s)

write('tests/r310-final-adversarial-regression.php',r'''<?php
$root=dirname(__DIR__); $files=array('security'=>file_get_contents($root.'/includes/class-sa-security.php'),'ops'=>file_get_contents($root.'/includes/class-sauth-operations.php'),'passkeys'=>file_get_contents($root.'/includes/class-sauth-passkeys.php'),'activator'=>file_get_contents($root.'/includes/class-sa-activator.php'),'main'=>file_get_contents($root.'/sabri-authentication.php'),'google'=>file_get_contents($root.'/includes/class-sa-google-oauth.php'),'plugin'=>file_get_contents($root.'/includes/class-sa-plugin.php'),'notice'=>file_get_contents($root.'/templates/partials/notice.php')); $fail=array();
$checks=array(array('security','const NOTICE_TTL = 600;'),array('security',"'sa_iat'"),array('security','current_cipher_ready'),array('notice','request_notice'),array('ops','notice_query_args'),array('ops','release_repair_lock( $lock ); self::redirect'),array('passkeys','installation_ready'),array('passkeys',"'_sauth_managed_page'"),array('activator','$passkey_ok'),array('activator','$google_secret_ok'),array('google','current_cipher_ready'),array('plugin','current_cipher_ready'));
foreach($checks as $c){if(false===strpos($files[$c[0]],$c[1]))$fail[]=$c[0].':'.$c[1];} if(false!==strpos($files['main'],"register_activation_hook( SAUTH_FILE, array( 'SAUTH_Passkeys', 'maybe_install' ) );"))$fail[]='independent passkey activation hook'; if($fail){fwrite(STDERR,"R310 failures:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R310 final adversarial regression PASS ('.(count($checks)+1)." assertions).\n";
''')
print('R310 v3 corrections applied')
