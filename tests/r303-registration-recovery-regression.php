<?php
$root = dirname( __DIR__ );
$registration = file_get_contents( $root . '/includes/class-sa-registration.php' );
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$email = file_get_contents( $root . '/includes/class-sauth-email-verification.php' );
$forgot = file_get_contents( $root . '/templates/forgot-password.php' );
$signup = file_get_contents( $root . '/templates/signup.php' );
$reset = file_get_contents( $root . '/templates/reset-password.php' );
$fail = array();
$checks = array(
    array( $registration, 'wp_check_password( $password, (string) $fresh_user->user_pass, $user_id )', 'reset persistence postcondition missing' ),
    array( $registration, 'password_reset_postcondition_failed', 'uncertain reset state is not contained' ),
    array( $registration, 'SA_Membership_Adapter::sign_in_allowed( $assertion, $completion )', 'password sign-in does not delegate to canonical membership/completion admission' ),
    array( $adapter, "'membership_prerequisite_denied'", 'completion-only admission can override an arbitrary membership denial' ),
    array( $adapter, "! empty( \$completion['missing_steps'] )", 'completion-only admission does not require unfinished completion steps' ),
    array( $adapter, "! empty( \$completion['next_route'] )", 'completion-only admission does not require a canonical completion route' ),
    array( $forgot, "get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) )", 'forgot-password template is not canonical-map first' ),
    array( $signup, 'maxlength="200" required', 'identity-reference client bound is stale' ),
    array( $signup, 'maxlength="1000" required', 'address client bound is stale' ),
    array( $reset, 'maxlength="4096"', 'reset client password bound missing' ),
    array( $email, "SAUTH_Provider_Health::allow_request( 'membership' )", 'email verification ignores membership circuit' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
$owner_call = strpos( $registration, 'SAUTH_Account_Contract::register_account(' );
$wipe = strpos( $registration, '$payload[\'password\'] = \'\';', $owner_call === false ? 0 : $owner_call );
$delivery = strpos( $registration, 'SAUTH_Email_Verification::issue(', $owner_call === false ? 0 : $owner_call );
if ( false === $owner_call || false === $wipe || false === $delivery || $wipe >= $delivery ) { $fail[] = 'registration password is not wiped before downstream delivery'; }
if ( $fail ) { fwrite( STDERR, "R303 regression failures:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R303 registration/recovery regression PASS (' . ( count( $checks ) + 1 ) . " assertions).\n";
