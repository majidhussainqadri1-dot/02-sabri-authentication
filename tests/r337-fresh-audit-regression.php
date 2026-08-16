<?php
$root = dirname( __DIR__ );
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$plugin = file_get_contents( $root . '/sabri-authentication.php' );
$readme = file_get_contents( $root . '/readme.txt' );
$registration = file_get_contents( $root . '/includes/class-sa-registration.php' );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$sessions = file_get_contents( $root . '/includes/class-sauth-session-manager.php' );
$access = file_get_contents( $root . '/includes/class-sa-access-control.php' );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };

$req( false !== strpos( $adapter, "const MIN_VERSION     = '1.2.44';" ), 'File 00 minimum is not 1.2.44' );
$req( false !== strpos( $plugin, 'Requires at least: 6.4' ), 'plugin WordPress minimum is not 6.4' );
$req( false !== strpos( $plugin, 'Membership Core 1.2.44+' ), 'activation message does not name File 00 1.2.44+' );
$req( false === strpos( $plugin, 'Membership Core 1.2.43+' ), 'activation message still advertises File 00 1.2.43+' );
$req( false !== strpos( $readme, 'Requires at least: 6.4' ), 'readme WordPress minimum is not 6.4' );
$req( false !== strpos( $readme, 'File 00 — Sabri Membership Core 1.2.44+' ), 'readme does not record File 00 1.2.44+ dependency' );

$canonical_professional = "array( 'doctor', 'teacher', 'researcher', 'pharmacy', 'clinic', 'publisher' )";
$req( false !== strpos( $registration, $canonical_professional ), 'registration professional taxonomy is not canonical' );
$req( false === strpos( $registration, "array( 'doctor', 'teacher', 'clinic_staff', 'institution_representative' )" ), 'stale provider-only professional taxonomy remains in registration age gate' );

foreach ( array( '$state_changed = $wpdb->update(', '$persisted_state = $wpdb->get_row(', "'credential_state_persist_failed'", "'user_id' => $user_id, 'status' => 'active'" ) as $marker ) {
    $req( false !== strpos( $passkeys, $marker ), 'passkey state postcondition missing: ' . $marker );
}
$persist_check = strpos( $passkeys, "'credential_state_persist_failed'" );
$assurance_store = strpos( $passkeys, 'self::store_pending_assurance( $user_id' );
$req( false !== $persist_check && false !== $assurance_store && $persist_check < $assurance_store, 'passkey persistence is not proved before assurance/session success' );

foreach ( array( "'session_projection_store_failed'", 'wp_clear_auth_cookie();', 'wp_set_current_user( 0 );', 'wp_send_json_error(', 'wp_die(' ) as $marker ) {
    $req( false !== strpos( $sessions, $marker ), 'session fail-closed postcondition missing: ' . $marker );
}
$req( false !== strpos( $sessions, "false === $result || '' !== (string) $wpdb->last_error" ), 'session projection database error is not fail-closed' );

$req( false !== strpos( $access, "'confirm_admin_email'" ), 'WordPress confirm_admin_email action is still intercepted' );

$req( is_array( $lock ), 'release lock is invalid JSON' );
$req( 'review/file02-r337-fresh-audit-2026-08-16' === (string) ( $lock['candidate_branch'] ?? '' ), 'release lock branch is stale' );
$req( 'R331-R337-corrective' === (string) ( $lock['review_line'] ?? '' ), 'release lock review line is stale' );
$req( '1.2.44' === (string) ( $lock['minimum_file00_runtime'] ?? '' ), 'release lock File 00 minimum is stale' );
$req( '6.4' === (string) ( $lock['minimum_wordpress'] ?? '' ), 'release lock WordPress minimum is stale' );
$req( 'historical_pre_r337' === (string) ( $lock['cross_file_integration_evidence']['evidence_scope'] ?? '' ), 'historical paired integration is not scoped honestly' );
$req( true === ( $lock['cross_file_integration_evidence']['current_r337_revalidation_required'] ?? false ), 'post-R337 paired integration revalidation is not required' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'external completion gates were advanced falsely' );
$req( file_exists( $root . '/review-evidence/R337-REVIEW-FROZEN.md' ), 'R337 frozen defect ledger is missing' );
$req( ! file_exists( $root . '/.github/workflows/r337-exact-corrective-edit.yml' ), 'temporary write-capable R337 correction workflow remains in source' );

if ( $fail ) { fwrite( STDERR, "R337 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R337 fresh audit regression PASS (27 assertions).' . PHP_EOL;
