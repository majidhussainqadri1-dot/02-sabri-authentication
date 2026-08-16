<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$repair_source = file_get_contents( $root . '/includes/class-sauth-passkey-index-reconciler.php' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };

$req( false !== strpos( $main, "require_once SAUTH_DIR . 'includes/class-sauth-passkey-index-reconciler.php';" ), 'passkey index reconciler is not loaded' );
$dependency_hook = strpos( $main, "register_activation_hook( SAUTH_FILE, 'sauth_validate_activation_dependencies' )" );
$repair_hook = strpos( $main, "register_activation_hook( SAUTH_FILE, 'sauth_reconcile_activation_schema' )" );
$activator_hook = strpos( $main, "register_activation_hook( SAUTH_FILE, array( 'SAUTH_Activator', 'activate' ) )" );
$req( false !== $dependency_hook && false !== $repair_hook && false !== $activator_hook && $dependency_hook < $repair_hook && $repair_hook < $activator_hook, 'activation ordering does not keep dependency gate before bounded reconciliation before owner migration' );
$req( false !== strpos( $main, 'SAUTH_Membership_Adapter::available() && SAUTH_Account_Contract::provider_available()' ) && false !== strpos( $main, 'SAUTH_Passkey_Index_Reconciler::repair()' ), 'already-active upgrade path does not guard reconciliation on File 00 readiness' );
$req( false !== strpos( $repair_source, "array( 'user_id', 'status', 'updated_at' )" ), 'proven stale live user_status shape is not bounded explicitly' );
$req( false !== strpos( $repair_source, "array( 'user_id', 'status' )" ), 'canonical user_status shape is not explicit' );
$req( false !== strpos( $repair_source, 'DROP INDEX `user_status`, ADD KEY `user_status` (`user_id`,`status`)' ), 'stale user_status index is not atomically contracted to canonical shape' );
$req( false !== strpos( $repair_source, 'if ( ! $known_stale )' ) && false !== strpos( $repair_source, 'return false;' ), 'unknown user_status shapes do not fail closed' );
$req( false === stripos( $repair_source, 'DROP TABLE' ) && false === stripos( $repair_source, 'DELETE FROM' ), 'bounded reconciler contains destructive table/data operations' );

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $value ) { return abs( (int) $value ); } }

final class R338_Fake_WPDB {
	public $prefix = 'wp_';
	public $last_error = '';
	public $table_exists = true;
	public $rows = array();
	public $queries = array();
	public $fail_query = false;

	public function esc_like( $value ) { return (string) $value; }
	public function prepare( $query ) { return (string) $query; }
	public function get_var( $query ) { return $this->table_exists ? $this->prefix . 'sauth_passkeys' : null; }
	public function get_results( $query, $format ) { return $this->rows; }
	public function query( $sql ) {
		$this->queries[] = (string) $sql;
		if ( $this->fail_query ) { $this->last_error = 'forced query failure'; return false; }
		if ( false !== strpos( (string) $sql, 'DROP INDEX `user_status`, ADD KEY `user_status` (`user_id`,`status`)' ) ) {
			$this->rows = array(
				array( 'Key_name'=>'user_status', 'Seq_in_index'=>1, 'Column_name'=>'user_id', 'Non_unique'=>1 ),
				array( 'Key_name'=>'user_status', 'Seq_in_index'=>2, 'Column_name'=>'status', 'Non_unique'=>1 ),
			);
		}
		return 0;
	}
}

require_once $root . '/includes/class-sauth-passkey-index-reconciler.php';

$wpdb = new R338_Fake_WPDB();
$wpdb->table_exists = false;
$req( true === SAUTH_Passkey_Index_Reconciler::repair(), 'fresh install without passkey table should be a no-op success' );
$req( empty( $wpdb->queries ), 'fresh install path unexpectedly mutates schema' );

$wpdb = new R338_Fake_WPDB();
$wpdb->rows = array(
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>1, 'Column_name'=>'user_id', 'Non_unique'=>1 ),
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>2, 'Column_name'=>'status', 'Non_unique'=>1 ),
);
$req( true === SAUTH_Passkey_Index_Reconciler::repair(), 'already canonical user_status index should pass' );
$req( empty( $wpdb->queries ), 'already canonical index should not be rewritten' );

$wpdb = new R338_Fake_WPDB();
$wpdb->rows = array(
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>1, 'Column_name'=>'user_id', 'Non_unique'=>1 ),
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>2, 'Column_name'=>'status', 'Non_unique'=>1 ),
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>3, 'Column_name'=>'updated_at', 'Non_unique'=>1 ),
);
$req( true === SAUTH_Passkey_Index_Reconciler::repair(), 'proven live stale user_status shape was not repaired' );
$req( 1 === count( $wpdb->queries ), 'proven stale shape should require exactly one atomic ALTER TABLE' );
$req( false !== strpos( $wpdb->queries[0], 'DROP INDEX `user_status`, ADD KEY `user_status` (`user_id`,`status`)' ), 'live repair SQL does not match bounded canonical contraction' );

$wpdb = new R338_Fake_WPDB();
$wpdb->rows = array(
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>1, 'Column_name'=>'user_id', 'Non_unique'=>1 ),
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>2, 'Column_name'=>'updated_at', 'Non_unique'=>1 ),
);
$req( false === SAUTH_Passkey_Index_Reconciler::repair(), 'unknown user_status shape should fail closed' );
$req( empty( $wpdb->queries ), 'unknown user_status shape must not be heuristically rewritten' );

$wpdb = new R338_Fake_WPDB();
$wpdb->rows = array(
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>1, 'Column_name'=>'user_id', 'Non_unique'=>1 ),
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>2, 'Column_name'=>'status', 'Non_unique'=>1 ),
	array( 'Key_name'=>'user_status', 'Seq_in_index'=>3, 'Column_name'=>'updated_at', 'Non_unique'=>1 ),
);
$wpdb->fail_query = true;
$req( false === SAUTH_Passkey_Index_Reconciler::repair(), 'ALTER TABLE failure should fail closed' );

if ( $fail ) {
	fwrite( STDERR, "R338 live passkey user_status index regressions:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}

echo 'R338 live passkey user_status index reconciliation PASS (' . 18 . ' assertions).' . PHP_EOL;
