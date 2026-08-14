#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

def rep(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    n = text.count(old)
    if n != count:
        raise SystemExit(f'{path}: expected {count}, found {n}: {old[:160]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

# R307-A: privacy passkey bridge must call the current File 02 passkey API.
rep(
    'includes/class-sa-privacy.php',
    "SAUTH_Passkeys::privacy_export( array(), sanitize_email( $email_address ), 1 )",
    "SAUTH_Passkeys::privacy_export( sanitize_email( $email_address ), $page )"
)
rep(
    'includes/class-sa-privacy.php',
    "SAUTH_Passkeys::privacy_erase( array(), sanitize_email( $email_address ), 1 );",
    "$passkey_erasure = SAUTH_Passkeys::privacy_erase( sanitize_email( $email_address ), $page );\n\t\t\t\tif ( ! is_array( $passkey_erasure ) || ! empty( $passkey_erasure['items_retained'] ) ) { $failed = true; }"
)

# R307-B: privacy export must paginate rather than silently cap evidence at 200.
p = ROOT / 'includes/class-sa-privacy.php'
text = p.read_text(encoding='utf-8')
text = text.replace("\t\t$user_id = (int) $user->ID;\n\t\t$data = array();\n", "\t\t$user_id = (int) $user->ID;\n\t\t$page = max( 1, absint( $page ) );\n\t\t$offset = ( $page - 1 ) * self::EXPORT_LIMIT;\n\t\t$data = array();\n\t\t$done = true;\n", 1)
text = text.replace("\t\t$google = $this->google_projection( $user_id );\n\t\tif ( ! empty( $google ) ) {", "\t\t$google = 1 === $page ? $this->google_projection( $user_id ) : array();\n\t\tif ( ! empty( $google ) ) {", 1)
text = text.replace("ORDER BY id DESC LIMIT %d', $user_id, self::EXPORT_LIMIT )", "ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset )", 1)
text = text.replace("\t\tforeach ( is_array( $sessions ) ? $sessions : array() as $row ) {", "\t\t$sessions = is_array( $sessions ) ? $sessions : array();\n\t\t$done = $done && count( $sessions ) < self::EXPORT_LIMIT;\n\t\tforeach ( $sessions as $row ) {", 1)
text = text.replace("\t\t$email_row = $wpdb->get_row(", "\t\t$email_row = 1 === $page ? $wpdb->get_row(", 1)
text = text.replace("' WHERE user_id=%d', $user_id ), ARRAY_A );\n\t\tif ( is_array( $email_row ) )", "' WHERE user_id=%d', $user_id ), ARRAY_A ) : null;\n\t\tif ( is_array( $email_row ) )", 1)
# auth attempts: second matching LIMIT clause
needle = "ORDER BY id DESC LIMIT %d', $user_id, self::EXPORT_LIMIT )"
if needle not in text: raise SystemExit('attempt pagination anchor missing')
text = text.replace(needle, "ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, self::EXPORT_LIMIT, $offset )", 1)
text = text.replace("\t\tforeach ( is_array( $attempts ) ? $attempts : array() as $row ) {", "\t\t$attempts = is_array( $attempts ) ? $attempts : array();\n\t\t$done = $done && count( $attempts ) < self::EXPORT_LIMIT;\n\t\tforeach ( $attempts as $row ) {", 1)
# outbox events
needle = "ORDER BY id DESC LIMIT %d', $user_id, $user_id, self::EXPORT_LIMIT )"
if needle not in text: raise SystemExit('event pagination anchor missing')
text = text.replace(needle, "ORDER BY id DESC LIMIT %d OFFSET %d', $user_id, $user_id, self::EXPORT_LIMIT, $offset )", 1)
text = text.replace("\t\tforeach ( is_array( $events ) ? $events : array() as $row ) {", "\t\t$events = is_array( $events ) ? $events : array();\n\t\t$done = $done && count( $events ) < self::EXPORT_LIMIT;\n\t\tforeach ( $events as $row ) {", 1)
text = text.replace("\t\t\tif ( is_array( $passkeys ) && ! empty( $passkeys['data'] ) && is_array( $passkeys['data'] ) ) { $data = array_merge( $data, $passkeys['data'] ); }\n\t\t}\n\t\treturn array( 'data' => $data, 'done' => true );", "\t\t\tif ( is_array( $passkeys ) && ! empty( $passkeys['data'] ) && is_array( $passkeys['data'] ) ) { $data = array_merge( $data, $passkeys['data'] ); }\n\t\t\t$done = $done && is_array( $passkeys ) && ! empty( $passkeys['done'] );\n\t\t}\n\t\treturn array( 'data' => $data, 'done' => $done );", 1)
p.write_text(text, encoding='utf-8')

# R307-C: preserve occurrence-time producer identity through deferred dispatch.
# Store reserved meta inside payload_json without exposing it as domain payload.
p = ROOT / 'includes/class-sauth-event-outbox.php'
text = p.read_text(encoding='utf-8')
old = "\t\t$payload_json = wp_json_encode( $event['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );"
new = "\t\t$stored_payload = $event['payload'];\n\t\t$stored_payload['sauth_event_meta'] = array( 'producer_version' => (string) $event['producer_version'], 'occurred_at' => (string) $event['occurred_at'] );\n\t\t$payload_json = wp_json_encode( $stored_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );"
if old not in text: raise SystemExit('outbox store anchor missing')
text = text.replace(old, new, 1)
old = "\t\t$event = array( 'event_id' => (string) $row['event_id'], 'event_name' => (string) $row['event_name'], 'schema_version' => (string) $row['schema_version'], 'producer' => 'file-02-authentication', 'producer_version' => defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : '', 'actor_user_id' => absint( $row['actor_user_id'] ), 'subject_user_id' => absint( $row['subject_user_id'] ), 'privacy_class' => sanitize_key( (string) $row['privacy_class'] ), 'trace_id' => sanitize_text_field( (string) $row['trace_id'] ), 'payload' => self::sanitize_payload( $decoded ) );"
new = "\t\t$meta = isset( $decoded['sauth_event_meta'] ) && is_array( $decoded['sauth_event_meta'] ) ? $decoded['sauth_event_meta'] : array();\n\t\tunset( $decoded['sauth_event_meta'] );\n\t\t$producer_version = sanitize_text_field( (string) ( $meta['producer_version'] ?? '' ) );\n\t\t$occurred_at = sanitize_text_field( (string) ( $meta['occurred_at'] ?? '' ) );\n\t\tif ( '' === $producer_version ) { $producer_version = defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : ''; }\n\t\tif ( '' === $occurred_at ) { $occurred_at = gmdate( 'c', strtotime( (string) ( $row['created_at'] ?? 'now' ) ) ?: time() ); }\n\t\t$event = array( 'event_id' => (string) $row['event_id'], 'event_name' => (string) $row['event_name'], 'schema_version' => (string) $row['schema_version'], 'producer' => 'file-02-authentication', 'producer_version' => $producer_version, 'occurred_at' => $occurred_at, 'actor_user_id' => absint( $row['actor_user_id'] ), 'subject_user_id' => absint( $row['subject_user_id'] ), 'privacy_class' => sanitize_key( (string) $row['privacy_class'] ), 'trace_id' => sanitize_text_field( (string) $row['trace_id'] ), 'payload' => self::sanitize_payload( $decoded ) );"
if old not in text: raise SystemExit('outbox dispatch anchor missing')
text = text.replace(old, new, 1)
p.write_text(text, encoding='utf-8')

# R307-D: guarded repair must honor repair return values and deep passkey readiness.
p = ROOT / 'includes/class-sauth-operations.php'
text = p.read_text(encoding='utf-8')
old = "\t\t\tSAUTH_Activator::repair();\n\t\t\tSAUTH_Passkeys::maybe_install( true );\n\t\t\t$checks = self::system_check();"
new = "\t\t\t$core_repaired = SAUTH_Activator::repair();\n\t\t\tSAUTH_Passkeys::maybe_install( true );\n\t\t\tif ( ! $core_repaired || ! SAUTH_Activator::storage_ready() || ! SAUTH_Passkeys::authentication_ready() ) {\n\t\t\t\tSA_Membership_Adapter::audit( 'authentication_guarded_repair_postcondition_failed', get_current_user_id() );\n\t\t\t\tself::redirect( 'error', 'Guarded repair could not prove all core/passkey storage postconditions. Safe Mode remains enabled.' );\n\t\t\t}\n\t\t\t$checks = self::system_check();"
if old not in text: raise SystemExit('repair anchor missing')
text = text.replace(old, new, 1)
old = "\t\t$checks[] = self::check( 'File 02 passkey authentication assurance', class_exists( 'SAUTH_Passkey_Runtime' ) && class_exists( 'SAUTH_Passkeys' ), 'Strong authentication is File 02-owned; File 00 MFA is retired.' );"
new = "\t\t$passkey_ready = class_exists( 'SAUTH_Passkey_Runtime' ) && class_exists( 'SAUTH_Passkeys' ) && SAUTH_Passkeys::authentication_ready();\n\t\t$checks[] = self::check( 'File 02 passkey authentication assurance', $passkey_ready, $passkey_ready ? 'File 02 passkey runtime, schema, table, HTTPS/origin and dependencies are ready.' : 'File 02 passkey authentication readiness is incomplete.' );"
if old not in text: raise SystemExit('system check passkey anchor missing')
text = text.replace(old, new, 1)
p.write_text(text, encoding='utf-8')

print('R307 corrections staged.')
