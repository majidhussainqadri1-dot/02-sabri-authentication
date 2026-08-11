<?php
/**
 * No-network regression checks for the canonical `sauth_*` storage migration.
 */

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

final class SAUTH_Activator {
	private static $canonical = array(
		'rate_limits'         => 'sauth_rate_limits',
		'auth_outbox'         => 'sauth_auth_outbox',
		'email_verifications' => 'sauth_email_verifications',
		'auth_sessions'       => 'sauth_auth_sessions',
		'auth_devices'        => 'sauth_auth_devices',
		'risk_challenges'     => 'sauth_auth_risk_challenges',
		'auth_attempts'       => 'sauth_auth_attempts',
	);
	private static $legacy = array(
		'rate_limits'         => 'sa_rate_limits',
		'auth_outbox'         => 'sa_auth_outbox',
		'email_verifications' => 'sa_email_verifications',
		'auth_sessions'       => 'sa_auth_sessions',
		'auth_devices'        => 'sa_auth_devices',
		'risk_challenges'     => 'sa_auth_risk_challenges',
		'auth_attempts'       => 'sa_auth_attempts',
	);
	public static function table( $key ) {
		global $wpdb;
		return $wpdb->prefix . self::$canonical[ $key ];
	}
	public static function legacy_table( $key ) {
		global $wpdb;
		return $wpdb->prefix . self::$legacy[ $key ];
	}
	public static function required_tables() {
		$output = array();
		foreach ( array_keys( self::$canonical ) as $key ) {
			$output[ $key ] = self::table( $key );
		}
		return $output;
	}
}

$wpdb = (object) array( 'prefix' => 'wp_' );

function sauth_storage_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-sauth-storage-router.php';

$select = "SELECT * FROM wp_sa_auth_sessions WHERE user_id=9";
$routed = SAUTH_Storage_Router::canonicalize_query( $select );
sauth_storage_assert( false !== strpos( $routed, 'wp_sauth_auth_sessions' ), 'legacy session query was not routed to canonical storage' );
sauth_storage_assert( false === strpos( $routed, 'wp_sa_auth_sessions' ), 'legacy session name remained in ordinary runtime query' );

$quoted = "SELECT * FROM `wp_sa_email_verifications` WHERE user_id=9";
$routed = SAUTH_Storage_Router::canonicalize_query( $quoted );
sauth_storage_assert( false !== strpos( $routed, '`wp_sauth_email_verifications`' ), 'quoted legacy email table was not routed' );

$migration = 'INSERT IGNORE INTO wp_sauth_auth_devices (id,public_id) SELECT id,public_id FROM wp_sa_auth_devices';
$routed = SAUTH_Storage_Router::canonicalize_query( $migration );
sauth_storage_assert( $migration === $routed, 'one-way legacy migration was rewritten into a canonical self-copy' );

$canonical = 'UPDATE wp_sauth_auth_outbox SET status="published" WHERE id=1';
sauth_storage_assert( $canonical === SAUTH_Storage_Router::canonicalize_query( $canonical ), 'canonical query was changed unexpectedly' );

$unrelated = 'SELECT * FROM wp_posts';
sauth_storage_assert( $unrelated === SAUTH_Storage_Router::canonicalize_query( $unrelated ), 'unrelated WordPress query was changed' );

$tables = SAUTH_Storage_Router::canonical_tables();
sauth_storage_assert( 7 === count( $tables ), 'canonical table inventory is incomplete' );
foreach ( $tables as $table ) {
	sauth_storage_assert( 0 === strpos( $table, 'wp_sauth_' ), 'non-canonical table found in inventory' );
}

echo "File 02 canonical storage migration checks passed.\n";
