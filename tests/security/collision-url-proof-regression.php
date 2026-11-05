<?php
/**
 * F02-X24-012 fail-first source regression: collision bearer secrets must not
 * appear in navigable URLs or be accepted through query parameters.
 *
 * Run: php tests/security/collision-url-proof-regression.php
 * This is a source-level guard, NOT WordPress/MariaDB or browser acceptance.
 */
declare(strict_types=1);
$source = file_get_contents(__DIR__ . '/../../includes/class-sauth-security-orchestrator.php');
if ($source === false) { fwrite(STDERR, "Cannot read orchestrator source\n"); exit(2); }
$failures = [];
if (preg_match('/add_query_arg\s*\(\s*array\s*\([^)]*[\x27\x22]proof[\x27\x22]\s*=>/s', $source)) {
    $failures[] = 'Collision creation passes bearer proof into a URL parameter map';
}
if (preg_match('/\$_GET\s*\[\s*[\x27\x22]proof[\x27\x22]\s*\]/', $source)) {
    $failures[] = 'Collision resolution accepts bearer proof from GET';
}
if ($failures) {
    foreach ($failures as $failure) { fwrite(STDERR, "FAIL: $failure\n"); }
    exit(1);
}
echo "PASS: no collision bearer proof in query transport\n";
