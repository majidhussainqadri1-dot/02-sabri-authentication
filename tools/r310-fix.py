#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

def write(path, text):
    (ROOT / path).write_text(text, encoding='utf-8')

def rep(path, old, new, count=1):
    text = read(path)
    actual = text.count(old)
    if actual != count:
        raise SystemExit(f'{path}: expected {count}, found {actual}: {old[:180]!r}')
    write(path, text.replace(old, new, count))

# -------------------------------------------------------------------------
# R310-01/02/03 — shared security primitives:
# - auth rate limiting must fail closed if neither SQL nor transient state can
#   be durably read/written;
# - signed status notices are short-lived, not replayable forever;
# - only the dedicated-key v3 Google-secret envelope is current runtime state.
# -------------------------------------------------------------------------
p = 'includes/class-sa-security.php'
text = read(p)
text = text.replace("final class SA_Security {\n", "final class SA_Security {\n\tconst NOTICE_TTL = 600;\n", 1)
old = """\t\tif ( false === $result ) {
\t\t\t$key    = 'sauth_fallback_' . substr( $bucket, 0, 32 );
\t\t\t$state  = get_transient( $key );
\t\t\t$now_ts = time();
\t\t\tif ( ! is_array( $state ) || empty( $state['expires'] ) || (int) $state['expires'] <= $now_ts ) {
\t\t\t\t$state = array( 'hits' => 0, 'expires' => $now_ts + $window );
\t\t\t}
\t\t\t$state['hits']++;
\t\t\t$ttl = max( 1, (int) $state['expires'] - $now_ts );
\t\t\tset_transient( $key, $state, $ttl );
\t\t\treturn $state['hits'] > $limit;
\t\t}
\t\t$hits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM {$table} WHERE bucket_hash = %s", $bucket ) );
"""
new = """\t\tif ( false === $result ) {
\t\t\t$key    = 'sauth_fallback_' . substr( $bucket, 0, 32 );
\t\t\t$state  = get_transient( $key );
\t\t\t$now_ts = time();
\t\t\tif ( ! is_array( $state ) || empty( $state['expires'] ) || (int) $state['expires'] <= $now_ts ) {
\t\t\t\t$state = array( 'hits' => 0, 'expires' => $now_ts + $window );
\t\t\t}
\t\t\t$state['hits']++;
\t\t\t$ttl = max( 1, (int) $state['expires'] - $now_ts );
\t\t\t$stored = set_transient( $key, $state, $ttl );
\t\t\t$verified = get_transient( $key );
\t\t\tif ( false === $stored || ! is_array( $verified ) || (int) ( $verified['hits'] ?? -1 ) !== (int) $state['hits'] || (int) ( $verified['expires'] ?? 0 ) !== (int) $state['expires'] ) {
\t\t\t\treturn true; // No durable throttle state: fail closed against brute force.
\t\t\t}
\t\t\treturn $state['hits'] > $limit;
\t\t}
\t\t$hits_raw = $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM {$table} WHERE bucket_hash = %s", $bucket ) );
\t\tif ( null === $hits_raw || '' !== (string) $wpdb->last_error ) {
\t\t\treturn true;
\t\t}
\t\t$hits = (int) $hits_raw;
"""
if old not in text: raise SystemExit('security rate-limit block not found')
text = text.replace(old, new, 1)
old = """\tpublic static function message_url( $page_key, $type, $message, array $extra = array() ) {
\t\t$type = 'success' === sanitize_key( $type ) ? 'success' : 'error';
\t\t$message = sanitize_text_field( (string) $message );
\t\t$args = array_merge(
\t\t\t$extra,
\t\t\tarray(
\t\t\t\t'sa_notice' => $type,
\t\t\t\t'sa_msg'    => $message,
\t\t\t\t'sa_sig'    => self::notice_signature( $type, $message ),
\t\t\t)
\t\t);
\t\treturn add_query_arg( $args, self::page_url( $page_key ) );
\t}

\tpublic static function notice_valid( $type, $message, $signature ) {
\t\t$type = 'success' === sanitize_key( $type ) ? 'success' : 'error';
\t\t$message = sanitize_text_field( (string) $message );
\t\t$signature = sanitize_text_field( (string) $signature );
\t\treturn 64 === strlen( $signature ) && hash_equals( self::notice_signature( $type, $message ), $signature );
\t}

\tprivate static function notice_signature( $type, $message ) {
\t\treturn hash_hmac( 'sha256', sanitize_key( $type ) . '|' . sanitize_text_field( (string) $message ), wp_salt( 'nonce' ) );
\t}
"""
new = """\tpublic static function message_url( $page_key, $type, $message, array $extra = array() ) {
\t\treturn add_query_arg( array_merge( $extra, self::notice_query_args( $type, $message ) ), self::page_url( $page_key ) );
\t}

\tpublic static function notice_query_args( $type, $message ) {
\t\t$type = 'success' === sanitize_key( $type ) ? 'success' : 'error';
\t\t$message = sanitize_text_field( (string) $message );
\t\t$issued_at = time();
\t\treturn array(
\t\t\t'sa_notice' => $type,
\t\t\t'sa_msg'    => $message,
\t\t\t'sa_iat'    => $issued_at,
\t\t\t'sa_sig'    => self::notice_signature( $type, $message, $issued_at ),
\t\t);
\t}

\tpublic static function notice_valid( $type, $message, $signature, $issued_at = 0 ) {
\t\t$type = 'success' === sanitize_key( $type ) ? 'success' : 'error';
\t\t$message = sanitize_text_field( (string) $message );
\t\t$signature = sanitize_text_field( (string) $signature );
\t\t$issued_at = absint( $issued_at );
\t\t$age = time() - $issued_at;
\t\treturn 64 === strlen( $signature )
\t\t\t&& $issued_at > 0
\t\t\t&& $age >= 0
\t\t\t&& $age <= self::NOTICE_TTL
\t\t\t&& hash_equals( self::notice_signature( $type, $message, $issued_at ), $signature );
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
if old not in text: raise SystemExit('security notice block not found')
text = text.replace(old, new, 1)
anchor = "\n\tprivate static function decrypt_gcm_payload( $payload, $key, $aad ) {"
helper = """

\tpublic static function current_cipher_ready( $cipher ) {
\t\t$cipher = (string) $cipher;
\t\treturn self::master_key_ready() && 0 === strpos( $cipher, 'v3:' ) && '' !== self::decrypt( $cipher );
\t}
"""
if anchor not in text: raise SystemExit('security cipher helper anchor missing')
text = text.replace(anchor, helper + anchor, 1)
write(p, text)

# Centralize notice parsing in the partial so every front-end notice obeys TTL.
write('templates/partials/notice.php', """<?php defined( 'ABSPATH' ) || exit; ?>
<?php $sauth_notice = SA_Security::request_notice(); ?>
<?php if ( ! empty( $sauth_notice ) ) : ?>
\t<div class="sa-notice sa-notice-<?php echo esc_attr( $sauth_notice['type'] ); ?>" role="status"><?php echo esc_html( $sauth_notice['message'] ); ?></div>
<?php endif; ?>
""")

# -------------------------------------------------------------------------
# R310-04/05/06 — passkey install must be one atomic File 02 migration:
# postconditioned table/page/schema/cron, no unrelated-page hijack, retryable
# cleanup scheduling, and a boolean result consumed by the core activator.
# -------------------------------------------------------------------------
p = 'includes/class-sauth-passkeys.php'
text = read(p)
start = text.index("\tpublic static function maybe_install( $force = false ) {")
end = text.index("\n\tpublic static function deactivate()", start)
new_install = r'''	public static function maybe_install( $force = false ) {
		if ( ! SA_Membership_Adapter::available() || ! SAUTH_Account_Contract::provider_available() ) {
			return false;
		}
		if ( ! $force && self::installation_ready() ) {
			return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			public_id varchar(64) NOT NULL,
			user_id bigint unsigned NOT NULL,
			credential_lookup char(64) NOT NULL,
			credential_ciphertext longtext NOT NULL,
			public_key_pem longtext NOT NULL,
			sign_count bigint unsigned NOT NULL DEFAULT 0,
			nickname varchar(120) NOT NULL DEFAULT '',
			attachment varchar(32) NOT NULL DEFAULT '',
			backup_eligible tinyint unsigned NOT NULL DEFAULT 0,
			status varchar(24) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			last_used_at datetime DEFAULT NULL,
			revoked_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY credential_lookup (credential_lookup),
			KEY user_status (user_id,status),
			KEY revoked_at (revoked_at)
		) {$charset};";
		dbDelta( $sql );
		self::ensure_manager_page();

		$table_exists = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $table_exists || ! self::manager_page_ready() ) {
			delete_option( self::OPTION_SCHEMA_VERSION );
			return false;
		}
		update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION, false );
		if ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) ) {
			return false;
		}
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			$scheduled = wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
			if ( false === $scheduled || is_wp_error( $scheduled ) ) { return false; }
		}
		return self::installation_ready();
	}

	public static function installation_ready() {
		if ( self::SCHEMA_VERSION !== (string) get_option( self::OPTION_SCHEMA_VERSION, '' ) || ! self::manager_page_ready() ) { return false; }
		global $wpdb;
		$table = self::table();
		$table_ready = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		$cron_ready = function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( self::CLEANUP_HOOK );
		return $table_ready && $cron_ready;
	}
'''
text = text[:start] + new_install + text[end:]
start = text.index("\tprivate static function ensure_manager_page() {")
end = text.index("\n\tpublic static function manager_url()", start)
new_page = r'''	private static function ensure_manager_page() {
		$map = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) );
		$page_id = isset( $map['passkeys'] ) ? absint( $map['passkeys'] ) : 0;
		$page = $page_id ? get_post( $page_id ) : null;
		if ( self::is_manager_page( $page ) ) {
			self::mark_manager_page( $page_id );
			return $page_id;
		}

		/* Only adopt an historical File 02 page when the exact shortcode and a
		 * legacy/private File 02 marker are both present. Never hijack an unrelated
		 * page merely because it owns the account-passkeys slug. */
		$candidates = get_posts( array(
			'post_type' => 'page',
			'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => 50,
			'orderby' => 'ID',
			'order' => 'ASC',
			'meta_query' => array( 'relation' => 'OR',
				array( 'key' => '_sauth_managed_page', 'value' => '1' ),
				array( 'key' => '_sa_private_page', 'value' => '1' ),
				array( 'key' => '_sauth_private_page', 'value' => '1' ),
			),
		) );
		foreach ( is_array( $candidates ) ? $candidates : array() as $candidate ) {
			if ( self::is_manager_page( $candidate, true ) ) {
				$page_id = absint( $candidate->ID );
				self::mark_manager_page( $page_id );
				$map['passkeys'] = $page_id;
				update_option( 'sauth_page_map', $map, false );
				return $page_id;
			}
		}

		$page_id = wp_insert_post( array(
			'post_title' => 'Passkeys & Security Keys',
			'post_name' => 'account-passkeys',
			'post_content' => '[sabri_auth_passkeys]',
			'post_status' => 'publish',
			'post_type' => 'page',
		), true );
		if ( is_wp_error( $page_id ) || ! absint( $page_id ) ) { return 0; }
		$page_id = absint( $page_id );
		self::mark_manager_page( $page_id );
		$map['passkeys'] = $page_id;
		update_option( 'sauth_page_map', $map, false );
		return $page_id;
	}

	private static function is_manager_page( $page, $legacy_allowed = false ) {
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status || '[sabri_auth_passkeys]' !== trim( (string) $page->post_content ) ) { return false; }
		$canonical = '1' === (string) get_post_meta( $page->ID, '_sauth_managed_page', true );
		$legacy = '1' === (string) get_post_meta( $page->ID, '_sa_private_page', true ) || '1' === (string) get_post_meta( $page->ID, '_sauth_private_page', true );
		return $canonical || ( $legacy_allowed && $legacy );
	}

	private static function mark_manager_page( $page_id ) {
		$page_id = absint( $page_id );
		if ( ! $page_id ) { return; }
		update_post_meta( $page_id, '_sauth_managed_page', '1' );
		update_post_meta( $page_id, '_sauth_private_page', '1' );
		delete_post_meta( $page_id, '_sa_private_page' );
	}

	private static function manager_page_ready() {
		$map = (array) get_option( 'sauth_page_map', array() );
		$page_id = isset( $map['passkeys'] ) ? absint( $map['passkeys'] ) : 0;
		return $page_id > 0 && self::is_manager_page( get_post( $page_id ) );
	}
'''
text = text[:start] + new_page + text[end:]
write(p, text)

# -------------------------------------------------------------------------
# R310-07 — core activation/version markers include the mandatory passkey
# migration, and provider-secret migration is verified before publishing a
# successful File 02 version state.
# -------------------------------------------------------------------------
p = 'includes/class-sa-activator.php'
text = read(p)
text = text.replace("\t\tself::migrate_google_secret();\n\t\tself::ensure_dummy_password_hash();\n\n\t\t/* Never publish", "\t\t$google_secret_ok = self::migrate_google_secret();\n\t\tself::ensure_dummy_password_hash();\n\t\t$passkey_ok = class_exists( 'SAUTH_Passkeys' ) && SAUTH_Passkeys::maybe_install( true );\n\n\t\t/* Never publish", 1)
text = text.replace("\t\tif ( ! $migration_ok || ! self::storage_ready() ) {", "\t\tif ( ! $migration_ok || ! $google_secret_ok || ! $passkey_ok || ! self::storage_ready() ) {", 1)
text = text.replace("\t\t}\n\t\treturn true;\n\t}\n\n\tpublic static function create_pages()", "\t\t}\n\t\tif ( ! class_exists( 'SAUTH_Passkeys' ) || ! SAUTH_Passkeys::installation_ready() ) { return false; }\n\t\treturn true;\n\t}\n\n\tpublic static function create_pages()", 1)
start = text.index("\tprivate static function migrate_google_secret() {")
end = text.index("\n\tprivate static function ensure_dummy_password_hash()", start)
new_migrate = r'''	private static function migrate_google_secret() {
		$cipher = (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) );
		if ( '' === $cipher ) { return true; }
		if ( SA_Security::current_cipher_ready( $cipher ) ) { return true; }
		if ( ! SA_Security::master_key_ready() ) { return false; }
		$plain = SA_Security::decrypt( $cipher );
		if ( '' === $plain ) { return false; }
		$encrypted = SA_Security::encrypt( $plain );
		$plain = '';
		if ( ! SA_Security::current_cipher_ready( $encrypted ) ) { return false; }
		update_option( 'sauth_google_client_secret', $encrypted, false );
		update_option( 'sa_google_client_secret', $encrypted, false );
		$canonical = (string) get_option( 'sauth_google_client_secret', '' );
		$legacy_mirror = (string) get_option( 'sa_google_client_secret', '' );
		return hash_equals( $encrypted, $canonical )
			&& hash_equals( $encrypted, $legacy_mirror )
			&& SA_Security::current_cipher_ready( $canonical );
	}
'''
text = text[:start] + new_migrate + text[end:]
write(p, text)

# The activator now owns the atomic passkey migration; avoid a second independent
# activation hook that could leave ambiguous partial-success semantics.
rep('sabri-authentication.php', "register_activation_hook( SAUTH_FILE, array( 'SAUTH_Passkeys', 'maybe_install' ) );\n", '')

# Runtime Google configuration may never accept an old salt-derived envelope.
rep(
    'includes/class-sa-google-oauth.php',
    "\t\t\t&& '' !== self::client_id()\n\t\t\t&& '' !== self::client_secret();",
    "\t\t\t&& '' !== self::client_id()\n\t\t\t&& SA_Security::current_cipher_ready( (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) ) )\n\t\t\t&& '' !== self::client_secret();"
)
rep(
    'includes/class-sa-plugin.php',
    "\t\tif ( $enable && ( SAUTH_Operations::safe_mode() || ! is_ssl() || '' === $client_id || '' === $encrypted || '' === SA_Security::decrypt( $encrypted ) ) ) {",
    "\t\tif ( $enable && ( SAUTH_Operations::safe_mode() || ! is_ssl() || '' === $client_id || ! SA_Security::current_cipher_ready( $encrypted ) ) ) {"
)

# -------------------------------------------------------------------------
# R310-08 — guarded-repair lock must be released explicitly before every
# exit-based redirect; PHP process termination must not be relied on to run
# finally cleanup. Also redirect to the intended admin System Check page with
# correctly typed, short-lived signed notice arguments.
# -------------------------------------------------------------------------
p = 'includes/class-sauth-operations.php'
text = read(p)
start = text.index("\tpublic static function run_repair() {")
end = text.index("\n\t/** Current source/runtime checks only; staging/live acceptance is separate. */", start)
block = text[start:end]
block = block.replace("self::redirect( 'error', 'File 00 readiness/contracts are unavailable; repair stopped before mutation.' );", "self::release_repair_lock( $lock ); self::redirect( 'error', 'File 00 readiness/contracts are unavailable; repair stopped before mutation.' );")
block = block.replace("self::redirect( 'error', 'Guarded repair could not enter Safe Mode.' );", "self::release_repair_lock( $lock ); self::redirect( 'error', 'Guarded repair could not enter Safe Mode.' );")
block = block.replace("self::redirect( 'error', 'Guarded repair could not prove all core/passkey storage postconditions. Safe Mode remains enabled.' );", "self::release_repair_lock( $lock ); self::redirect( 'error', 'Guarded repair could not prove all core/passkey storage postconditions. Safe Mode remains enabled.' );")
block = block.replace("self::redirect( 'error', 'Guarded repair completed with unresolved checks. Safe Mode remains enabled.' );", "self::release_repair_lock( $lock ); self::redirect( 'error', 'Guarded repair completed with unresolved checks. Safe Mode remains enabled.' );")
block = block.replace("self::redirect( 'success', 'Guarded repair completed and all checked postconditions passed.' );", "self::release_repair_lock( $lock ); self::redirect( 'success', 'Guarded repair completed and all checked postconditions passed.' );")
block = block.replace("self::redirect( 'error', 'Guarded repair failed safely. Safe Mode remains enabled.' );", "self::release_repair_lock( $lock ); self::redirect( 'error', 'Guarded repair failed safely. Safe Mode remains enabled.' );")
text = text[:start] + block + text[end:]
old_redirect = "\tprivate static function redirect( $type, $message ) { wp_safe_redirect( SA_Security::message_url( 'system_check', $type, $message, admin_url( 'admin.php?page=sabri-authentication-system-check' ) ) ); exit; }"
new_redirect = "\tprivate static function redirect( $type, $message ) { $target = admin_url( 'admin.php?page=sabri-authentication-system-check' ); wp_safe_redirect( add_query_arg( SA_Security::notice_query_args( $type, $message ), $target ) ); exit; }"
if old_redirect not in text: raise SystemExit('operations redirect block missing')
text = text.replace(old_redirect, new_redirect, 1)
# Render the same validated signed notice on the admin System Check screen.
anchor = "\t\t\t<h1><?php echo esc_html__( 'Sabri Authentication — System Check', 'sabri-authentication' ); ?></h1>\n"
notice = "\t\t\t<h1><?php echo esc_html__( 'Sabri Authentication — System Check', 'sabri-authentication' ); ?></h1>\n\t\t\t<?php $sauth_notice = SA_Security::request_notice(); if ( ! empty( $sauth_notice ) ) : ?><div class=\"notice notice-<?php echo 'success' === $sauth_notice['type'] ? 'success' : 'error'; ?>\"><p><?php echo esc_html( $sauth_notice['message'] ); ?></p></div><?php endif; ?>\n"
if anchor not in text: raise SystemExit('operations render notice anchor missing')
text = text.replace(anchor, notice, 1)
write(p, text)

# Update security fixture for notice TTL/current-cipher semantics.
p = 'tests/security-unit.php'
text = read(p)
text = text.replace("SA_Security::notice_valid( $notice_args['sa_notice'] ?? '', $notice_args['sa_msg'] ?? '', $notice_args['sa_sig'] ?? '' )", "SA_Security::notice_valid( $notice_args['sa_notice'] ?? '', $notice_args['sa_msg'] ?? '', $notice_args['sa_sig'] ?? '', $notice_args['sa_iat'] ?? 0 )")
text = text.replace("SA_Security::notice_valid( 'success', 'Forged success', $notice_args['sa_sig'] ?? '' )", "SA_Security::notice_valid( 'success', 'Forged success', $notice_args['sa_sig'] ?? '', $notice_args['sa_iat'] ?? 0 )")
insert = "sa_test_assert( $plain === SA_Security::decrypt( $cipher ), 'AES-256-GCM dedicated-key round trip failed' );\n"
if insert not in text: raise SystemExit('security fixture cipher anchor missing')
text = text.replace(insert, insert + "sa_test_assert( SA_Security::current_cipher_ready( $cipher ), 'v3 dedicated-key ciphertext was not accepted as current' );\n", 1)
write(p, text)

# Permanent final adversarial regression for every R310 ledger item.
write('tests/r310-final-adversarial-regression.php', r'''<?php
$root = dirname( __DIR__ );
$security = file_get_contents( $root . '/includes/class-sa-security.php' );
$ops = file_get_contents( $root . '/includes/class-sauth-operations.php' );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$activator = file_get_contents( $root . '/includes/class-sa-activator.php' );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$google = file_get_contents( $root . '/includes/class-sa-google-oauth.php' );
$plugin = file_get_contents( $root . '/includes/class-sa-plugin.php' );
$partial = file_get_contents( $root . '/templates/partials/notice.php' );
$fail = array();
$checks = array(
  array($security, 'No durable throttle state: fail closed against brute force.', 'rate-limit storage failure still fails open'),
  array($security, 'const NOTICE_TTL = 600;', 'signed notices have no bounded lifetime'),
  array($security, "'sa_iat'", 'signed notice lacks issued-at binding'),
  array($partial, 'SA_Security::request_notice()', 'front-end notice bypasses TTL/signature parser'),
  array($ops, "admin.php?page=sabri-authentication-system-check", 'operations redirect does not target System Check'),
  array($ops, 'SA_Security::notice_query_args', 'operations redirect passes an invalid message_url argument'),
  array($ops, 'self::release_repair_lock( $lock ); self::redirect', 'guarded-repair redirect can leak its lock'),
  array($passkeys, 'public static function installation_ready()', 'passkey install exposes no full postcondition'),
  array($passkeys, 'return self::installation_ready();', 'passkey install can claim success before cron/page/table readiness'),
  array($passkeys, "'_sauth_managed_page'", 'passkey page lacks canonical ownership marker'),
  array($passkeys, 'Never hijack an unrelated', 'passkey installer can adopt unrelated slug page'),
  array($activator, '$passkey_ok = class_exists', 'core activation does not include passkey migration'),
  array($activator, '$google_secret_ok = self::migrate_google_secret()', 'core activation ignores Google-secret migration result'),
  array($activator, 'SA_Security::current_cipher_ready( $canonical )', 'Google-secret migration has no persistence/decrypt postcondition'),
  array($security, 'public static function current_cipher_ready', 'current Google cipher format is not explicit'),
  array($google, 'SA_Security::current_cipher_ready', 'Google runtime accepts legacy cipher envelopes'),
  array($plugin, '! SA_Security::current_cipher_ready( $encrypted )', 'admin can re-enable a legacy cipher envelope'),
);
foreach ($checks as $c) { if (false === strpos($c[0], $c[1])) $fail[] = $c[2]; }
if (false !== strpos($main, "register_activation_hook( SAUTH_FILE, array( 'SAUTH_Passkeys', 'maybe_install' ) );")) $fail[] = 'independent passkey activation hook still permits ambiguous partial activation';
if ($fail) { fwrite(STDERR, "R310 regressions:\n- ".implode("\n- ",$fail)."\n"); exit(1); }
echo 'R310 final adversarial regression PASS ('.(count($checks)+1)." assertions).\n";
''')

print('R310 frozen defect ledger corrections staged.')
