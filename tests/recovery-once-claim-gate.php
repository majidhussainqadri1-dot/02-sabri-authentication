<?php
/**
 * F02-X24-011/012 fail-first source gate.
 * Run: php tests/recovery-once-claim-gate.php
 *
 * This is a structural regression only. A green result MUST NOT replace
 * WordPress/MariaDB re-entrant and two-connection integration testing.
 */
declare(strict_types=1);
$source = file_get_contents(__DIR__ . '/../includes/class-sauth-security-orchestrator.php');
if ($source === false) { fwrite(STDERR, "Cannot read security orchestrator\n"); exit(2); }
$failures = [];
foreach ([
    'apply_recovery_change' => 'sauth_apply_recovery_change_v1',
    'resolve_collision_case' => 'sauth_resolve_collision_case_v1',
] as $method => $hook) {
    $start = strpos($source, 'public static function ' . $method . '(');
    if ($start === false) { $failures[] = "$method missing"; continue; }
    $next = strpos($source, '\n\tpublic static function ', $start + 1);
    $body = substr($source, $start, $next === false ? null : $next - $start);
    $hookPos = strpos($body, "apply_filters( '" . $hook . "'");
    if ($hookPos === false) {
        $hookPos = strpos($body, "apply_filters('" . $hook . "'");
    }
    if ($hookPos === false) { $failures[] = "$method canonical hook missing"; continue; }
    $before = substr($body, 0, $hookPos);
    // Require an actual conditional database claim BEFORE side-effect-capable
    // owner invocation, not just a later pending->applied UPDATE.
    $hasClaim = preg_match('/\\$wpdb->(?:query|update)\\s*\\(/', $before) === 1
        && strpos($before, "'pending'") !== false
        && (strpos($before, "'processing'") !== false || strpos($before, "'claimed'") !== false);
    if (!$hasClaim) {
        $failures[] = "$method invokes canonical owner before atomic pending->processing claim";
    }
}
if ($failures) {
    foreach ($failures as $failure) { fwrite(STDERR, "FAIL: $failure\n"); }
    exit(1);
}
fwrite(STDOUT, "Structural one-time claim ordering gate passed; runtime idempotency remains unverified.\n");
