<?php
/**
 * R341 permanent regression for the live-proven legacy email-verification
 * reconciliation gap between File 02 1.3.3 and corrected File 00 1.2.44.
 */

$root       = dirname( __DIR__ );
$reconciler = file_get_contents( $root . '/includes/class-sauth-email-verification-reconciler.php' );
$resolver   = file_get_contents( $root . '/includes/class-sauth-completion-resolver.php' );
$bootstrap  = file_get_contents( $root . '/sabri-authentication.php' );
$fail       = array();

$req = static function ( $ok, $message ) use ( &$fail ) {
	if ( ! $ok ) {
		$fail[] = $message;
	}
};

$req( false !== strpos( $bootstrap, "require_once SAUTH_DIR . 'includes/class-sauth-email-verification-reconciler.php';" ), 'reconciler is not loaded by the File 02 bootstrap' );
$req( false !== strpos( $reconciler, 'final class SAUTH_Email_Verification_Reconciler' ), 'reconciler class is missing' );
$req( false !== strpos( $reconciler, "in_array( 'email', \$missing, true )" ), 'reconciliation is not bounded to File 00 reporting email missing' );
$req( false !== strpos( $reconciler, "'verified' !== (string) ( \$row['status'] ?? '' )" ), 'verified local status gate is missing' );
$req( false !== strpos( $reconciler, "'' === \$verified_at" ), 'non-empty verified_at gate is missing' );
$req( false !== strpos( $reconciler, "absint( \$row['attempts'] ?? 0 ) < 1" ), 'consumed-attempt evidence gate is missing' );
$req( false !== strpos( $reconciler, "hash_equals( str_repeat( '0', 64 ), \$token_hash )" ), 'consumed-token tombstone gate is missing' );
$req( false !== strpos( $reconciler, "wp_salt( 'secure_auth' )" ), 'canonical File 02 email-hash binding is missing' );
$req( false !== strpos( $reconciler, 'SAUTH_Account_Contract::mark_email_verified(' ), 'public File 00 email-verification contract handoff is missing' );
$req( false !== strpos( $reconciler, "'purpose'         => 'email_verification'" ), 'File 00 handoff purpose is not exact' );
$req( false !== strpos( $reconciler, "'legacy-email-reconcile-'" ), 'bounded deterministic reconciliation idempotency namespace is missing' );
$req( false !== strpos( $reconciler, "SAUTH_Account_Contract::completion_state(" ), 'completion state is not re-read after provider reconciliation' );
$req( false !== strpos( $reconciler, "in_array( 'email', \$refreshed_missing, true )" ), 'reconciler does not fail closed when email remains missing' );
$req( false === strpos( $reconciler, 'smc_contact_otps' ), 'File 02 must not read or write File 00 private contact storage directly' );
$req( false === strpos( $reconciler, '$wpdb->prefix . \'smc_' ), 'File 02 must not construct File 00 private table names' );

$fetch_pos = strpos( $resolver, 'SAUTH_Account_Contract::completion_state(' );
$recon_pos = strpos( $resolver, 'SAUTH_Email_Verification_Reconciler::reconcile_if_needed( $user_id, $state )' );
$bound_pos = strpos( $resolver, '$fetched_state = empty( $state );' );
$if_pos    = strpos( $resolver, 'if ( $fetched_state ) {' );
$req( false !== $fetch_pos, 'completion resolver no longer fetches File 00 state' );
$req( false !== $recon_pos && false !== $fetch_pos && $fetch_pos < $recon_pos, 'reconciliation must happen only after File 00 state is fetched' );
$req( false !== $bound_pos && false !== $if_pos && $bound_pos < $if_pos && $if_pos < $recon_pos, 'explicit caller-supplied completion state is not protected from reconciliation side effects' );

if ( $fail ) {
	fwrite( STDERR, "R341 legacy email reconciliation regressions:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}

echo 'R341 legacy email reconciliation PASS (18 assertions).' . PHP_EOL;
