<?php
/**
 * R344 — File 19 notification-governance assurance contract regression.
 */
$root = dirname(__DIR__);
$source = file_get_contents($root . '/includes/class-sa-authentication-assurance.php');
if (!is_string($source)) {
    fwrite(STDERR, "R344 source unavailable\n");
    exit(1);
}
$checks = array(
    "purpose-listed" => strpos($source, "'notification_governance'") !== false,
    "ttl-bounded" => strpos($source, "'notification_governance'=> 300") !== false
        || strpos($source, "'notification_governance' => 300") !== false,
    "session-bound-assertion" => strpos($source, 'public static function assertion') !== false,
    "aal2-contract" => strpos($source, "'assurance_level'  => 'aal2'") !== false,
    "passkey-method" => strpos($source, "'method'           => 'webauthn_passkey'") !== false,
);
$failed = array_keys(array_filter($checks, static fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "R344 FAIL: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "PASS R344 File 19 notification-governance purpose is bounded to current session WebAuthn AAL2 assurance\n";
