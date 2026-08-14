#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

def rep(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    n = text.count(old)
    if n != count:
        raise SystemExit(f'{path}: expected {count}, found {n}: {old[:180]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

# R308-A: canonical route migration must never forge successful runtime/schema
# markers; those belong exclusively to the R302 postconditioned activator path.
rep(
    'includes/class-sauth-canonical-routes.php',
    "\t\t\t$page_map = (array) get_option( 'sa_page_map', array() );",
    "\t\t\t$page_map = (array) get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) );"
)
rep(
    'includes/class-sauth-canonical-routes.php',
    "\t\tupdate_option( 'sauth_version', SAUTH_VERSION, false );\n\t\tupdate_option( 'sauth_db_version', SAUTH_DB_VERSION, false );\n",
    "\t\t/* Runtime/schema version markers are intentionally not written here.\n\t\t * SAUTH_Activator::repair() publishes them only after storage postconditions. */\n"
)

# R308-B: the canonical /account/sessions/ route is a File 02 private surface
# even though it is rewrite-backed rather than a WordPress page.
rep(
    'includes/class-sa-access-control.php',
    "\tpublic static function is_file02_page() {\n\t\tif ( ! is_singular( 'page' ) ) { return false; }",
    "\tpublic static function is_file02_page() {\n\t\tif ( class_exists( 'SAUTH_Canonical_Routes' )\n\t\t\t&& SAUTH_Canonical_Routes::SESSIONS === (string) get_query_var( SAUTH_Canonical_Routes::QUERY_VAR ) ) {\n\t\t\treturn true;\n\t\t}\n\t\tif ( ! is_singular( 'page' ) ) { return false; }"
)

# R308-C: notices are evidence-bearing UI. A user-supplied query string cannot
# manufacture an authoritative success/error message.
p = ROOT / 'includes/class-sa-security.php'
text = p.read_text(encoding='utf-8')
old = """\tpublic static function message_url( $page_key, $type, $message, array $extra = array() ) {
\t\t$args = array_merge(
\t\t\t$extra,
\t\t\tarray(
\t\t\t\t'sa_notice' => sanitize_key( $type ),
\t\t\t\t'sa_msg'    => rawurlencode( $message ),
\t\t\t)
\t\t);
\t\treturn add_query_arg( $args, self::page_url( $page_key ) );
\t}
"""
new = """\tpublic static function message_url( $page_key, $type, $message, array $extra = array() ) {
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
if old not in text: raise SystemExit('message_url block missing')
text = text.replace(old, new, 1)
p.write_text(text, encoding='utf-8')

p = ROOT / 'templates/partials/notice.php'
p.write_text("""<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( isset( $_GET['sa_notice'], $_GET['sa_msg'], $_GET['sa_sig'] ) ) : ?>
\t<?php
\t$notice_type = 'success' === sanitize_key( wp_unslash( $_GET['sa_notice'] ) ) ? 'success' : 'error';
\t$notice_text = sanitize_text_field( wp_unslash( $_GET['sa_msg'] ) );
\t$notice_sig  = sanitize_text_field( wp_unslash( $_GET['sa_sig'] ) );
\t?>
\t<?php if ( SA_Security::notice_valid( $notice_type, $notice_text, $notice_sig ) ) : ?>
\t\t<div class="sa-notice sa-notice-<?php echo esc_attr( $notice_type ); ?>" role="status"><?php echo esc_html( $notice_text ); ?></div>
\t<?php endif; ?>
<?php endif; ?>
""", encoding='utf-8')

# R308-D: Google Client Secret encryption must require a dedicated File 02
# master key. v2/legacy ciphertext can be read only during a configured-key
# migration and all newly written secrets use v3 dedicated-key encryption.
p = ROOT / 'includes/class-sa-security.php'
text = p.read_text(encoding='utf-8')
start = text.index("\tprivate static function encryption_key() {")
end = text.index("\n\tpublic static function page_url", start)
crypto = r'''	public static function master_key_ready() {
		return defined( 'SA_MASTER_KEY' ) && is_string( SA_MASTER_KEY ) && strlen( SA_MASTER_KEY ) >= 32;
	}

	private static function encryption_key() {
		if ( ! self::master_key_ready() ) { return ''; }
		return hash( 'sha256', 'sabri-authentication|dedicated-master|v3|' . SA_MASTER_KEY, true );
	}

	private static function legacy_v2_key_from_master() {
		if ( ! self::master_key_ready() ) { return ''; }
		return hash( 'sha256', 'sabri-authentication|v2|' . SA_MASTER_KEY, true );
	}

	private static function legacy_v2_key_from_auth_salt() {
		return hash( 'sha256', 'sabri-authentication|v2|' . wp_salt( 'auth' ), true );
	}

	public static function encrypt( $plain ) {
		$key = self::encryption_key();
		if ( '' === $plain || '' === $key || ! function_exists( 'openssl_encrypt' ) ) { return ''; }
		try { $iv = random_bytes( 12 ); } catch ( Exception $exception ) { return ''; }
		$tag = '';
		$out = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'sa-google-secret-v3' );
		return false === $out ? '' : 'v3:' . base64_encode( $iv . $tag . $out );
	}

	public static function decrypt( $cipher ) {
		if ( '' === $cipher || ! self::master_key_ready() || ! function_exists( 'openssl_decrypt' ) ) { return ''; }
		if ( 0 === strpos( $cipher, 'v3:' ) ) {
			return self::decrypt_gcm_payload( substr( $cipher, 3 ), self::encryption_key(), 'sa-google-secret-v3' );
		}
		if ( 0 === strpos( $cipher, 'v2:' ) ) {
			$payload = substr( $cipher, 3 );
			$out = self::decrypt_gcm_payload( $payload, self::legacy_v2_key_from_master(), 'sa-google-secret-v2' );
			if ( '' !== $out ) { return $out; }
			/* Migration-only compatibility for historical v2 ciphertext that was
			 * derived from WordPress auth salt. Runtime still requires SA_MASTER_KEY. */
			return self::decrypt_gcm_payload( $payload, self::legacy_v2_key_from_auth_salt(), 'sa-google-secret-v2' );
		}
		return self::decrypt_legacy( $cipher );
	}

	private static function decrypt_gcm_payload( $payload, $key, $aad ) {
		if ( '' === (string) $key ) { return ''; }
		$raw = base64_decode( (string) $payload, true );
		if ( false === $raw || strlen( $raw ) < 29 ) { return ''; }
		$iv = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$out = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad );
		return false === $out ? '' : $out;
	}

	private static function decrypt_legacy( $cipher ) {
		/* Pre-v2 ciphertext is readable only while a dedicated master key is
		 * configured, solely so activation can migrate it to v3. */
		if ( ! self::master_key_ready() ) { return ''; }
		$raw = base64_decode( $cipher, true );
		if ( false === $raw || strlen( $raw ) < 29 ) { return ''; }
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$out = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false === $out ? '' : $out;
	}
'''
text = text[:start] + crypto + text[end:]
p.write_text(text, encoding='utf-8')

rep(
    'includes/class-sa-activator.php',
    "\t\tif ( '' === $cipher || 0 === strpos( $cipher, 'v2:' ) ) {\n\t\t\treturn;\n\t\t}",
    "\t\tif ( '' === $cipher || 0 === strpos( $cipher, 'v3:' ) || ! SA_Security::master_key_ready() ) {\n\t\t\treturn;\n\t\t}"
)

# R308-E: evidence-honest settings success receipt, current ownership copy and
# canonical-first option reads.
p = ROOT / 'includes/class-sa-plugin.php'
text = p.read_text(encoding='utf-8')
old = "\t\tSAUTH_Provider_Health::reset( 'google' );\n\t\twp_safe_redirect( add_query_arg( 'updated', '1', self::settings_url() ) );\n\t\texit;"
new = "\t\tSAUTH_Provider_Health::reset( 'google' );\n\t\t$receipt = SA_Security::random_token( 16 );\n\t\t$receipt_key = 'sauth_settings_saved_' . get_current_user_id();\n\t\t$receipt_hash = hash( 'sha256', $receipt );\n\t\tset_transient( $receipt_key, $receipt_hash, 120 );\n\t\tif ( '' === $receipt || ! hash_equals( $receipt_hash, (string) get_transient( $receipt_key ) ) ) {\n\t\t\twp_safe_redirect( add_query_arg( 'error', 'settings_receipt_failed', self::settings_url() ) );\n\t\t\texit;\n\t\t}\n\t\twp_safe_redirect( add_query_arg( 'updated_token', $receipt, self::settings_url() ) );\n\t\texit;"
if old not in text: raise SystemExit('settings success redirect anchor missing')
p.write_text(text.replace(old, new, 1), encoding='utf-8')

p = ROOT / 'admin/account-settings.php'
text = p.read_text(encoding='utf-8')
text = text.replace("\t<?php if ( isset( $_GET['updated'] ) ) : ?><div class=\"notice notice-success\"><p>Settings saved.</p></div><?php endif; ?>", "\t<?php\n\t$settings_saved = false;\n\tif ( isset( $_GET['updated_token'] ) ) {\n\t\t$receipt = sanitize_text_field( wp_unslash( $_GET['updated_token'] ) );\n\t\t$key = 'sauth_settings_saved_' . get_current_user_id();\n\t\t$expected = (string) get_transient( $key );\n\t\t$settings_saved = '' !== $receipt && '' !== $expected && hash_equals( $expected, hash( 'sha256', $receipt ) );\n\t\tif ( $settings_saved ) { delete_transient( $key ); }\n\t}\n\t?>\n\t<?php if ( $settings_saved ) : ?><div class=\"notice notice-success\"><p>Settings saved and verified.</p></div><?php endif; ?>", 1)
text = text.replace("\t\t\t\t'not_ready'         => 'Google sign-in cannot be enabled until Membership Core, HTTPS, Client ID, and encrypted Client Secret are ready.',", "\t\t\t\t'not_ready'         => 'Google sign-in cannot be enabled until Membership Core, HTTPS, Client ID, a dedicated SA_MASTER_KEY, and encrypted Client Secret are ready.',\n\t\t\t\t'dependency_unavailable' => 'Membership Core/account-contract readiness is unavailable; settings were not changed.',\n\t\t\t\t'settings_store_failed' => 'The complete Google settings unit could not be stored and was rolled back.',\n\t\t\t\t'settings_receipt_failed' => 'Settings may have been written, but the success receipt could not be verified. Reload and inspect the stored values before relying on them.',", 1)
text = text.replace("File 00 owns registration, membership profiles, roles, identity evidence, institutional verification, and mandatory two-factor authentication. File 02 only adds explicit Google linking, Google sign-in after Membership Core 2FA, account recovery routing, and access-page integration.", "File 00 owns membership, verified identity, guardian status, roles/capabilities, verification and eligibility. File 02 owns registration orchestration, password authentication, Google OIDC/linking, passkeys, authentication assurance, recovery, risk and sessions. Retired File 00 authenticator/recovery codes are not File 02 authentication factors.", 1)
text = text.replace("get_option( 'sa_google_enabled', '0' )", "get_option( 'sauth_google_enabled', get_option( 'sa_google_enabled', '0' ) )")
text = text.replace("get_option( 'sa_google_client_id', '' )", "get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) )")
text = text.replace("Stored with authenticated AES-256-GCM encryption. Define a strong <code>SA_MASTER_KEY</code> in <code>wp-config.php</code> for a dedicated key; otherwise WordPress authentication salts are used.", "Stored with authenticated AES-256-GCM encryption only when a strong dedicated <code>SA_MASTER_KEY</code> (32+ characters) is defined in <code>wp-config.php</code>. WordPress authentication salts are not accepted as the current secret-encryption authority.", 1)
text = text.replace("Access and refresh tokens are not retained. A linked Membership Core account and a current Authenticator or recovery code remain mandatory.", "Access and refresh tokens are not retained. A linked eligible Membership Core account is required; sensitive Google link/unlink changes require a fresh File 02 passkey assurance.", 1)
text = text.replace("Link, unlink, linked login, 2FA challenge, recovery code, logout, and privacy erasure must pass staging tests.", "Link, unlink, linked login, passkey step-up, password recovery, logout, and privacy erasure must pass staging tests.", 1)
p.write_text(text, encoding='utf-8')

# R308-F: restore 44x44 touch-target/accessibility gate and align login bounds.
p = ROOT / 'assets/css/authentication.css'
text = p.read_text(encoding='utf-8')
text = text.replace(".sa-show-password{position:absolute;inset-inline-end:5px;top:5px;min-height:36px;", ".sa-show-password{position:absolute;inset-inline-end:1px;top:1px;min-width:56px;min-height:44px;", 1)
text = text.replace(".sa-check{display:flex!important;align-items:flex-start;gap:8px;font-weight:500!important}.sa-check input{width:17px!important;min-height:17px!important;margin-top:2px}", ".sa-check{display:flex!important;align-items:flex-start;gap:8px;min-height:44px;padding:10px 4px;font-weight:500!important;cursor:pointer}.sa-check input{width:20px!important;min-width:20px!important;height:20px!important;min-height:20px!important;margin-top:1px}", 1)
p.write_text(text, encoding='utf-8')

rep('templates/login.php', 'name="user_login" autocomplete="username" autocapitalize="none" required', 'name="user_login" autocomplete="username" autocapitalize="none" maxlength="320" required')
rep('templates/login.php', 'name="password" autocomplete="current-password" minlength="12" required', 'name="password" autocomplete="current-password" minlength="12" maxlength="4096" required')
rep('templates/login.php', 'Google sign-in works only after explicit same-email linking to an eligible Membership Core account and the required step-up verification.', 'Google sign-in works only after explicit same-email linking to an eligible Membership Core account. Elevated device/network risk may require a separate File 02 passkey sign-in.')
rep('templates/access-required.php', 'Membership Core sign-in is required for comments, saving, following, messaging, publishing, and personal services.', 'Sabri Authentication sign-in is required for comments, saving, following, messaging, publishing, and personal services; Membership Core remains the membership and eligibility authority.')
rep('templates/google-verify.php', 'Current Google sign-in uses verified Google OIDC directly; Google link and unlink changes require a fresh File 02 passkey instead.', 'Current Google sign-in uses verified Google OIDC and the File 02 risk policy; elevated risk may require a separate File 02 passkey sign-in. Google link and unlink changes require a fresh File 02 passkey assurance.')

print('R308 corrections staged.')
