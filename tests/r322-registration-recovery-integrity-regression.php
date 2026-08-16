<?php
$root = dirname( __DIR__ );
$r = file_get_contents( $root . '/includes/class-sa-registration.php' );
$e = file_get_contents( $root . '/includes/class-sauth-email-verification.php' );
$reset = file_get_contents( $root . '/templates/reset-password.php' );
$fail = array();
if ( false !== strpos( $r, 'rawurlencode( SA_Security::safe_redirect( $redirect ) )' ) ) $fail[] = 'login failure redirect is pre-encoded';
if ( false !== strpos( $r, 'rawurlencode( $key )' ) || false !== strpos( $r, 'rawurlencode( $login )' ) ) $fail[] = 'reset retry credential is pre-encoded';
$revoked = strpos( $r, "\$revoked = SAUTH_Session_Manager::revoke_user_sessions( \$user_id, 'password_reset' );" );
$guard = strpos( $r, 'if ( ! $revoked )', $revoked === false ? 0 : $revoked );
$event = strpos( $r, "PasswordResetCompleted.v1", $revoked === false ? 0 : $revoked );
if ( false === $revoked || false === $guard || false === $event || $guard > $event ) $fail[] = 'password reset completion does not guard revocation evidence';
foreach ( array('retry_recovery_job','retry_count','delete_recovery_job') as $m ) if ( false === strpos( $r, $m ) ) $fail[] = 'recovery retry lifecycle missing ' . $m;
foreach ( array('retry_resend_job','retry_count','delete_resend_job','sauth_email_resend_throttled') as $m ) if ( false === strpos( $e, $m ) ) $fail[] = 'resend retry lifecycle missing ' . $m;
if ( false === strpos( $reset, 'Successful completion revokes all existing sessions.' ) ) $fail[] = 'reset UX contract unexpectedly changed';
if ( $fail ) { fwrite( STDERR, "R322 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo "R322 registration/recovery integrity regression PASS (10 assertions).\n";
