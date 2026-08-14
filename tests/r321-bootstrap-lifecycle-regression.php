<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$activator = file_get_contents( $root . '/includes/class-sa-activator.php' );
$operations = file_get_contents( $root . '/includes/class-sauth-operations.php' );
$plugin = file_get_contents( $root . '/includes/class-sa-plugin.php' );
$fail = array();
$checks = array(
  array($main, 'static $started = false;', 'bootstrap lacks idempotency guard'),
  array($activator, 'return false;', 'upgrade path does not expose repair failure'),
  array($activator, 'SAUTH_Operations::enter_safe_mode();', 'activator automatic Safe Mode entry lacks revocation epoch authority'),
  array($operations, 'public static function enter_safe_mode()', 'central Safe Mode entry helper missing'),
  array($operations, 'self::safe_mode() && ! self::safe_mode_entered_at()', 'legacy Safe Mode state lacks startup epoch reconciliation'),
  array($plugin, 'if ( ! SA_Activator::maybe_upgrade() )', 'plugin does not stop provider/settings hooks after failed reconciliation'),
  array($plugin, 'migration_notice', 'failed migration lacks explicit admin evidence'),
);
foreach ($checks as $c) { if (false === strpos($c[0], $c[1])) $fail[] = $c[2]; }
if (substr_count($main, 'static $started = false;') !== 1) $fail[] = 'bootstrap idempotency guard count invalid';
if (false !== strpos($activator, "update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false )")) $fail[] = 'activator still bypasses Safe Mode epoch authority';
if ($fail) { fwrite(STDERR, "R321 regressions:\n- " . implode("\n- ", $fail) . "\n"); exit(1); }
echo "R321 bootstrap/lifecycle regression PASS (" . count($checks) . " assertions).\n";
