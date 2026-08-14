#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

def write(path, text):
    (ROOT / path).write_text(text, encoding='utf-8')

def replace_once(text, old, new, label):
    if text.count(old) != 1:
        raise SystemExit(f'{label}: expected exactly one patch point, found {text.count(old)}')
    return text.replace(old, new, 1)

# R311 ledger item 1: authentication risk reads must fail closed on DB uncertainty.
p = 'includes/class-sauth-login-risk.php'
s = read(p)
old = '''\t\t$device = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::device_table() . " WHERE user_id=%d AND fingerprint_hash=%s AND status='trusted'", $user_id, SA_Security::client_fingerprint() ), ARRAY_A );
\t\tif ( ! is_array( $device ) || empty( $device['last_seen_at'] ) || strtotime( (string) $device['last_seen_at'] ) < time() - self::TRUST_TTL ) { $score += 50; $reasons[] = 'new_device'; }
\t\t$network_hash = self::network_hash();
\t\t$known_network = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::device_table() . " WHERE user_id=%d AND network_hash=%s AND status='trusted'", $user_id, $network_hash ) );
\t\tif ( 0 === (int) $known_network ) { $score += 20; $reasons[] = 'new_network'; }
\t\t$recent_failures = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::attempt_table() . " WHERE user_id=%d AND result IN ('failure','denied') AND created_at >= %s", $user_id, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
\t\tif ( (int) $recent_failures >= 5 ) { $score += 35; $reasons[] = 'recent_failures'; }
'''
new = '''\t\t$device = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::device_table() . " WHERE user_id=%d AND fingerprint_hash=%s AND status='trusted'", $user_id, SA_Security::client_fingerprint() ), ARRAY_A );
\t\tif ( '' !== (string) $wpdb->last_error ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'risk_storage_unavailable' ); }
\t\tif ( ! is_array( $device ) || empty( $device['last_seen_at'] ) || strtotime( (string) $device['last_seen_at'] ) < time() - self::TRUST_TTL ) { $score += 50; $reasons[] = 'new_device'; }
\t\t$network_hash = self::network_hash();
\t\t$known_network = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::device_table() . " WHERE user_id=%d AND network_hash=%s AND status='trusted'", $user_id, $network_hash ) );
\t\tif ( null === $known_network && '' !== (string) $wpdb->last_error ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'risk_storage_unavailable' ); }
\t\tif ( 0 === (int) $known_network ) { $score += 20; $reasons[] = 'new_network'; }
\t\t$recent_failures = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::attempt_table() . " WHERE user_id=%d AND result IN ('failure','denied') AND created_at >= %s", $user_id, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
\t\tif ( null === $recent_failures && '' !== (string) $wpdb->last_error ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'risk_storage_unavailable' ); }
\t\tif ( (int) $recent_failures >= 5 ) { $score += 35; $reasons[] = 'recent_failures'; }
'''
s = replace_once(s, old, new, 'risk read fail-closed block')
old = '''\t\tif ( self::has_active_passkey( $user_id ) ) { return array( 'action' => 'challenge', 'score' => $score, 'reason_code' => $reason ); }
\t\t/* A first/new network alone may produce medium risk before a user has ever
'''
new = '''\t\t$has_active_passkey = self::has_active_passkey( $user_id );
\t\tif ( null === $has_active_passkey ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'passkey_status_unavailable' ); }
\t\tif ( $has_active_passkey ) { return array( 'action' => 'challenge', 'score' => $score, 'reason_code' => $reason ); }
\t\t/* A first/new network alone may produce medium risk before a user has ever
'''
s = replace_once(s, old, new, 'passkey decision block')
old = '''\tprivate static function has_active_passkey( $user_id ) {
\t\tglobal $wpdb;
\t\treturn 0 < (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sauth_passkeys WHERE user_id=%d AND status='active'", absint( $user_id ) ) );
\t}
'''
new = '''\tprivate static function has_active_passkey( $user_id ) {
\t\tglobal $wpdb;
\t\t$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sauth_passkeys WHERE user_id=%d AND status='active'", absint( $user_id ) ) );
\t\tif ( null === $count && '' !== (string) $wpdb->last_error ) { return null; }
\t\treturn 0 < (int) $count;
\t}
'''
s = replace_once(s, old, new, 'active passkey lookup')
write(p, s)

# R311 ledger item 2: approved Google provider hosts must never travel over cleartext HTTP.
p = 'includes/class-sauth-provider-http-guard.php'
s = read(p)
old = '''\t\tif ( '' === $provider ) {
\t\t\treturn $preempt;
\t\t}
\t\tif ( SAUTH_Operations::safe_mode() ) {
'''
new = '''\t\tif ( '' === $provider ) {
\t\t\treturn $preempt;
\t\t}
\t\t$scheme = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_SCHEME ) );
\t\tif ( 'https' !== $scheme ) {
\t\t\treturn new WP_Error( 'sauth_provider_https_required', 'Authentication provider calls require HTTPS.' );
\t\t}
\t\tif ( SAUTH_Operations::safe_mode() ) {
'''
s = replace_once(s, old, new, 'provider HTTPS boundary')
write(p, s)

# Permanent regression gate for the frozen R311 ledger.
write('tests/r311-security-boundary-regression.php', r'''<?php
$root = dirname( __DIR__ );
$risk = file_get_contents( $root . '/includes/class-sauth-login-risk.php' );
$guard = file_get_contents( $root . '/includes/class-sauth-provider-http-guard.php' );
$fail = array();
$checks = array(
    array( $risk, "'risk_storage_unavailable'", 'risk engine does not fail closed on storage errors' ),
    array( $risk, 'null === $has_active_passkey', 'passkey lookup uncertainty can still become a medium-risk password allow' ),
    array( $risk, "'passkey_status_unavailable'", 'passkey lookup failure has no explicit fail-closed reason' ),
    array( $risk, 'null === $count && \'\' !== (string) $wpdb->last_error', 'active-passkey database errors collapse to false/no-passkey' ),
    array( $guard, '\'https\' !== $scheme', 'provider HTTP guard does not reject non-HTTPS Google URLs' ),
    array( $guard, "'sauth_provider_https_required'", 'provider HTTPS rejection is not explicit' ),
);
foreach ( $checks as $check ) { if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; } }
if ( $fail ) { fwrite( STDERR, "R311 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R311 security boundary regression PASS (' . count( $checks ) . " assertions).\n";
''')

# Make R311 permanent in the cumulative exact-head gate.
p = '.github/workflows/review-branch-integrity.yml'
s = read(p)
old = '''            tests/r310-final-adversarial-regression.php
          )
'''
new = '''            tests/r310-final-adversarial-regression.php
            tests/r311-security-boundary-regression.php
          )
'''
s = replace_once(s, old, new, 'cumulative R311 test registration')
write(p, s)

print('R311 frozen ledger corrections applied')
