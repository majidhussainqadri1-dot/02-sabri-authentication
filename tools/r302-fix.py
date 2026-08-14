#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
p = ROOT / 'includes/class-sa-activator.php'
text = p.read_text(encoding='utf-8')

def rep(old, new, count=1):
    global text
    actual = text.count(old)
    if actual != count:
        raise SystemExit(f'expected {count}, found {actual}: {old[:100]!r}')
    text = text.replace(old, new, count)

rep(
"\t\t\t\tesc_html__( 'Sabri Authentication requires File 00 — Sabri Membership Core 1.2.11 or later with smc.authentication-account 1.1.0 and the approved assurance contract. No account, role, guardian or verification authority will be created independently.', 'sabri-authentication' ),",
"\t\t\t\tesc_html__( 'Sabri Authentication requires File 00 — Sabri Membership Core 1.2.43 or later with its current database migration complete, Safe Mode clear, smc.authentication-account 1.1.0 and the current membership-assurance contract. No account, role, guardian or verification authority will be created independently.', 'sabri-authentication' ),"
)
rep(
"\t\tself::repair();\n\t\tadd_option( 'sauth_google_enabled', '0', '', false );",
"\t\tif ( ! self::repair() ) {\n\t\t\tupdate_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false );\n\t\t\tdeactivate_plugins( plugin_basename( SAUTH_FILE ) );\n\t\t\twp_die(\n\t\t\t\tesc_html__( 'File 02 activation stopped because its database/page migration postconditions were not satisfied. Safe Mode was enabled and no successful version marker was published.', 'sabri-authentication' ),\n\t\t\t\tesc_html__( 'Authentication migration incomplete', 'sabri-authentication' ),\n\t\t\t\tarray( 'back_link' => true )\n\t\t\t);\n\t\t}\n\t\tadd_option( 'sauth_google_enabled', '0', '', false );"
)
rep(
"\t\tif ( SAUTH_DB_VERSION !== $stored_db || SAUTH_VERSION !== $stored ) {\n\t\t\tself::repair();\n\t\t}",
"\t\tif ( SAUTH_DB_VERSION !== $stored_db || SAUTH_VERSION !== $stored ) {\n\t\t\tif ( ! self::repair() ) {\n\t\t\t\tupdate_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false );\n\t\t\t}\n\t\t}"
)
rep(
"\t\tself::migrate_legacy_tables();\n\t\tself::create_pages();\n\t\tself::migrate_google_secret();\n\t\tself::ensure_dummy_password_hash();\n\t\tupdate_option( 'sauth_version', SAUTH_VERSION, false );\n\t\tupdate_option( 'sauth_db_version', SAUTH_DB_VERSION, false );\n\t\t/* Compatibility mirrors are retained only for old integrations. */\n\t\tupdate_option( 'sa_version', SAUTH_VERSION, false );\n\t\tupdate_option( 'sa_db_version', SAUTH_DB_VERSION, false );\n\t\treturn true;",
"\t\t$migration_ok = self::migrate_legacy_tables();\n\t\tself::create_pages();\n\t\tself::migrate_google_secret();\n\t\tself::ensure_dummy_password_hash();\n\n\t\t/* Never publish a successful runtime/schema marker merely because dbDelta\n\t\t * returned. Prove every required table and managed page exists first and\n\t\t * preserve retryability on partial/failed deployment. */\n\t\tif ( ! $migration_ok || ! self::storage_ready() ) {\n\t\t\treturn false;\n\t\t}\n\n\t\tupdate_option( 'sauth_version', SAUTH_VERSION, false );\n\t\tupdate_option( 'sauth_db_version', SAUTH_DB_VERSION, false );\n\t\tif ( SAUTH_VERSION !== (string) get_option( 'sauth_version', '' ) || SAUTH_DB_VERSION !== (string) get_option( 'sauth_db_version', '' ) ) {\n\t\t\treturn false;\n\t\t}\n\t\t/* Compatibility mirrors are retained only for old integrations; they are\n\t\t * not accepted as proof of a completed canonical migration. */\n\t\tupdate_option( 'sa_version', SAUTH_VERSION, false );\n\t\tupdate_option( 'sa_db_version', SAUTH_DB_VERSION, false );\n\t\treturn true;"
)
rep(
"\tpublic static function migrate_legacy_tables() {\n\t\tglobal $wpdb;",
"\tpublic static function migrate_legacy_tables() {\n\t\tglobal $wpdb;\n\t\t$ok = true;"
)
rep(
"\t\t\t$wpdb->query( \"INSERT IGNORE INTO {$canonical} ({$column_list}) SELECT {$column_list} FROM {$legacy}\" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared\n\t\t}\n\t\tupdate_option( 'sauth_legacy_table_migration_version', SAUTH_DB_VERSION, false );\n\t}",
"\t\t\t$result = $wpdb->query( \"INSERT IGNORE INTO {$canonical} ({$column_list}) SELECT {$column_list} FROM {$legacy}\" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared\n\t\t\tif ( false === $result ) {\n\t\t\t\t$ok = false;\n\t\t\t}\n\t\t}\n\t\tif ( $ok ) {\n\t\t\tupdate_option( 'sauth_legacy_table_migration_version', SAUTH_DB_VERSION, false );\n\t\t}\n\t\treturn $ok;\n\t}"
)
marker = "\n\tpublic static function create_pages() {"
storage = r'''

	/**
	 * Canonical migration postcondition. Version markers are evidence only after
	 * every File 02 table and every managed core page is materially present.
	 */
	public static function storage_ready() {
		global $wpdb;
		foreach ( self::required_tables() as $table ) {
			if ( '' === $table ) {
				return false;
			}
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $table !== (string) $exists ) {
				return false;
			}
		}
		$map = (array) get_option( 'sauth_page_map', array() );
		foreach ( self::page_specs() as $key => $spec ) {
			$page_id = isset( $map[ $key ] ) ? absint( $map[ $key ] ) : 0;
			$page = $page_id ? get_post( $page_id ) : null;
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'trash' === $page->post_status || ! self::is_owned_page( $page ) || ! self::exact_shortcode_page( $page, $spec['shortcode'] ) ) {
				return false;
			}
		}
		return true;
	}
'''
if marker not in text:
    raise SystemExit('create_pages marker not found')
text = text.replace(marker, storage + marker, 1)
p.write_text(text, encoding='utf-8')

(ROOT / 'tests/r302-migration-postcondition-regression.php').write_text(r'''<?php
$root = dirname( __DIR__ );
$activator = file_get_contents( $root . '/includes/class-sa-activator.php' );
$fail = array();
$checks = array(
    'activation migration failure is contained' => "if ( ! self::repair() )",
    'upgrade migration failure enables Safe Mode' => "update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false );",
    'repair proves storage before markers' => "! self::storage_ready()",
    'canonical version marker is read back' => "SAUTH_VERSION !== (string) get_option( 'sauth_version', '' )",
    'legacy copy failure is observed' => "if ( false === $result )",
    'legacy migration marker waits for success' => "if ( $ok )",
    'table postcondition exists' => "SHOW TABLES LIKE %s",
    'page postcondition exists' => "! self::exact_shortcode_page( $page, $spec['shortcode'] )",
    'minimum File 00 version copy is current' => "Membership Core 1.2.43 or later",
);
foreach ( $checks as $label => $needle ) {
    if ( false === strpos( $activator, $needle ) ) { $fail[] = $label; }
}
if ( $fail ) { fwrite( STDERR, "R302 migration regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo "R302 migration postcondition regression PASS (" . count( $checks ) . " assertions).\n";
''', encoding='utf-8')
print('R302 corrections staged.')
