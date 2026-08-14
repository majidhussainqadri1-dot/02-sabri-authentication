#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

def rep(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    n = text.count(old)
    if n != count:
        raise SystemExit(f'{path}: expected {count}, found {n}: {old[:140]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

# R305-A: management UI must not truncate server-accepted current passwords.
rep('includes/class-sauth-passkeys.php', 'id="sauth-passkey-password" type="password" autocomplete="current-password" maxlength="256"', 'id="sauth-passkey-password" type="password" autocomplete="current-password" maxlength="4096"')

# R305-B: a completion route may not override a non-active membership denial.
old_signin = """\tprivate static function sign_in_allowed( array $assertion, array $completion ) {
\t\tif ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || ! empty( $assertion['membership']['suspended'] ) ) { return false; }
\t\tif ( 'allow' === ( $assertion['result'] ?? '' ) ) { return true; }
\t\treturn 'allow' === ( $completion['result'] ?? '' ) && ! empty( $completion['missing_steps'] ) && ! empty( $completion['next_route'] );
\t}
"""
new_signin = """\tprivate static function sign_in_allowed( array $assertion, array $completion ) {
\t\tif ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || ! empty( $assertion['membership']['suspended'] ) ) { return false; }
\t\tif ( 'allow' === ( $assertion['result'] ?? '' ) ) { return true; }
\t\t$active = true === ( $assertion['membership']['active'] ?? false );
\t\treturn $active
\t\t\t&& 'allow' === ( $completion['result'] ?? '' )
\t\t\t&& ! empty( $completion['missing_steps'] )
\t\t\t&& ! empty( $completion['next_route'] );
\t}
"""
rep('includes/class-sauth-passkeys.php', old_signin, new_signin)
rep('includes/class-sauth-passkey-runtime.php', old_signin, new_signin)

# R305-C: stale/foreign assurance receipt versions must never satisfy current assurance.
rep(
    'includes/class-sauth-passkey-runtime.php',
    "\t\treturn is_array( $receipt )\n\t\t\t&& absint( $receipt['user_id'] ?? 0 ) === absint( $user_id )",
    "\t\treturn is_array( $receipt )\n\t\t\t&& SAUTH_Passkeys::CONTRACT_VERSION === (string) ( $receipt['contract_version'] ?? '' )\n\t\t\t&& absint( $receipt['user_id'] ?? 0 ) === absint( $user_id )"
)

# R305-D: passkey availability must prove its own schema/table, not just dependencies.
rep(
    'includes/class-sauth-passkeys.php',
    "\t\treturn '' !== $ctx['rp_id']\n\t\t\t&& ( 'https' === $ctx['scheme'] || ( $local && 'http' === $ctx['scheme'] ) )\n\t\t\t&& function_exists( 'openssl_verify' )\n\t\t\t&& SA_Membership_Adapter::available()\n\t\t\t&& SAUTH_Account_Contract::provider_available();",
    "\t\t$schema_ready = self::SCHEMA_VERSION === (string) get_option( self::OPTION_SCHEMA_VERSION, '' );\n\t\t$table_ready = false;\n\t\tif ( $schema_ready ) {\n\t\t\tglobal $wpdb;\n\t\t\t$table = self::table();\n\t\t\t$table_ready = $table === (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );\n\t\t}\n\t\treturn '' !== $ctx['rp_id']\n\t\t\t&& ( 'https' === $ctx['scheme'] || ( $local && 'http' === $ctx['scheme'] ) )\n\t\t\t&& function_exists( 'openssl_verify' )\n\t\t\t&& $schema_ready\n\t\t\t&& $table_ready\n\t\t\t&& SA_Membership_Adapter::available()\n\t\t\t&& SAUTH_Account_Contract::provider_available();"
)

# R305-E: privacy export must include retained inactive credentials and erasure
# must report retention truthfully, clear user-handle + assurance epoch, and
# verify postconditions.
start = "\tpublic static function privacy_export( $email_address, $page = 1 ) {"
end = "\n\tpublic static function register_eraser( $erasers ) {"
p = ROOT / 'includes/class-sauth-passkeys.php'
text = p.read_text(encoding='utf-8')
a = text.find(start)
b = text.find(end, a)
if a < 0 or b < 0:
    raise SystemExit('privacy_export block not found')
new_export = r'''	public static function privacy_export( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$page = max( 1, absint( $page ) );
		$limit = 50;
		$offset = ( $page - 1 ) * $limit;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT public_id,nickname,status,created_at,last_used_at,revoked_at,attachment,backup_eligible FROM ' . self::table() . ' WHERE user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d',
				$user->ID,
				$limit,
				$offset
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();
		$data = array();
		foreach ( $rows as $credential ) {
			$data[] = array(
				'group_id' => 'sabri-authentication-passkeys',
				'group_label' => __( 'Passkeys', 'sabri-authentication' ),
				'item_id' => 'passkey-' . sanitize_key( $credential['public_id'] ),
				'data' => array(
					array( 'name' => 'Nickname', 'value' => (string) $credential['nickname'] ),
					array( 'name' => 'Status', 'value' => (string) $credential['status'] ),
					array( 'name' => 'Created', 'value' => (string) $credential['created_at'] ),
					array( 'name' => 'Last used', 'value' => (string) $credential['last_used_at'] ),
					array( 'name' => 'Revoked', 'value' => (string) $credential['revoked_at'] ),
					array( 'name' => 'Authenticator type', 'value' => (string) $credential['attachment'] ),
					array( 'name' => 'Sync capable', 'value' => ! empty( $credential['backup_eligible'] ) ? 'Yes' : 'No' ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $rows ) < $limit );
	}
'''
text = text[:a] + new_export + text[b:]
p.write_text(text, encoding='utf-8')

start = "\tpublic static function privacy_erase( $email_address, $page = 1 ) {"
p = ROOT / 'includes/class-sauth-passkeys.php'
text = p.read_text(encoding='utf-8')
a = text.find(start)
if a < 0:
    raise SystemExit('privacy_erase start not found')
# class closes immediately after this method in current file.
b = text.rfind("\n}")
if b <= a:
    raise SystemExit('privacy_erase end not found')
new_erase = r'''	public static function privacy_erase( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user instanceof WP_User ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$user_id = absint( $user->ID );
		global $wpdb;
		$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d', $user_id ) );
		$deleted = $wpdb->delete( self::table(), array( 'user_id' => $user_id ), array( '%d' ) );
		delete_user_meta( $user_id, self::USER_HANDLE_META );
		if ( class_exists( 'SAUTH_Passkey_Runtime' ) ) {
			SAUTH_Passkey_Runtime::invalidate_user_assurance( $user_id );
		}
		delete_user_meta( $user_id, SAUTH_Passkey_Runtime::EPOCH_META );
		self::clear_assurance_for_user( $user_id );

		$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d', $user_id ) );
		$handle_retained = function_exists( 'metadata_exists' ) && metadata_exists( 'user', $user_id, self::USER_HANDLE_META );
		$epoch_retained = class_exists( 'SAUTH_Passkey_Runtime' ) && function_exists( 'metadata_exists' ) && metadata_exists( 'user', $user_id, SAUTH_Passkey_Runtime::EPOCH_META );
		$failed = false === $deleted || $remaining > 0 || $handle_retained || $epoch_retained;
		return array(
			'items_removed' => ! $failed && $before > 0,
			'items_retained' => $failed,
			'messages' => $failed ? array( __( 'Some passkey authentication data could not be erased. An administrator must review the retained data before the request is considered complete.', 'sabri-authentication' ) ) : array(),
			'done' => true,
		);
	}
'''
text = text[:a] + new_erase + text[b:]
p.write_text(text, encoding='utf-8')

print('R305 passkey corrections staged.')
