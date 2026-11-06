<?php
/** F02-X24-012: generic recovery endpoint must not accept collision-resolution cases.
 * Source-level fail-first regression; does not substitute for WordPress/MariaDB E2E.
 */
$source = file_get_contents(__DIR__ . '/../../includes/class-sauth-security-orchestrator.php');
if ($source === false) { fwrite(STDERR, "FAIL: source unavailable\n"); exit(1); }
if (!preg_match('/public static function apply_recovery_change\s*\([^)]*\)\s*\{(.*?)\n\t\}/s', $source, $match)) {
    fwrite(STDERR, "FAIL: generic recovery handler not found\n"); exit(1);
}
$handler = $match[1];
if (!preg_match('/[\x27\x22]collision_resolution[\x27\x22]\s*===\s*\(string\)\s*\$row\[[\x27\x22]change_kind[\x27\x22]\].*?return new WP_Error\(/s', $handler)) {
    fwrite(STDERR, "FAIL: generic recovery handler does not reject collision cases before canonical owner dispatch\n"); exit(1);
}
echo "PASS: generic recovery handler rejects collision cases (static source gate)\n";
