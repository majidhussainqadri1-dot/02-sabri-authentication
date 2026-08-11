<?php
/**
 * Source-level preservation guard: all prior three-plan requirements must remain
 * present in File 02 v1.2.0 while the fourth-plan passkey extension is added.
 */

$root = dirname( __DIR__ );

function sauth_three_plan_read( $root, $path ) {
	$file = $root . '/' . $path;
	if ( ! is_file( $file ) ) {
		fwrite( STDERR, "FAIL: missing {$path}\n" );
		exit( 1 );
	}
	return file_get_contents( $file );
}

function sauth_three_plan_require( $text, array $markers, $label ) {
	foreach ( $markers as $marker ) {
		if ( false === strpos( $text, $marker ) ) {
			fwrite( STDERR, "FAIL: {$label} missing {$marker}\n" );
			exit( 1 );
		}
	}
}

$main = sauth_three_plan_read( $root, 'sabri-authentication.php' );
sauth_three_plan_require(
	$main,
	array(
		'Version: 1.2.0',
		"define( 'SAUTH_VERSION', '1.2.0' );",
		"define( 'SAUTH_DB_VERSION', '1.2.0' );",
		"define( 'SAUTH_ACCOUNT_CONTRACT_VERSION', '1.1.0' );",
		'class-sauth-google-registration.php',
		'class-sauth-canonical-routes.php',
		'class-sauth-passkeys.php',
		'SAUTH_Google_Registration::init()',
		'SAUTH_Canonical_Routes::init()',
		'SAUTH_Passkeys::init()',
	),
	'bootstrap'
);

$signup = sauth_three_plan_read( $root, 'templates/signup.php' );
sauth_three_plan_require(
	$signup,
	array(
		'name="city"',
		'name="account_type"',
		'name="profile_photo_required"',
		'name="accept_ethics"',
		'name="google_registration_token"',
		'Continue with Google',
		'National ID',
		'Passport',
	),
	'registration surface'
);

$registration = sauth_three_plan_read( $root, 'includes/class-sa-registration.php' );
sauth_three_plan_require(
	$registration,
	array(
		"'city'",
		"'account_type'",
		"'ethical_conduct_version'",
		"'profile_photo_required'",
		"'authentication_method'",
		"'google' ===",
		'SAUTH_Google_Registration::finalize_link',
		'Professional and institutional account declarations require an adult account',
	),
	'registration orchestration'
);

$consumer = sauth_three_plan_read( $root, 'includes/class-sauth-account-contract.php' );
sauth_three_plan_require(
	$consumer,
	array(
		"const CONTRACT_VERSION     = '1.1.0';",
		"const PROVIDER_MIN_VERSION = '1.1.0';",
		'SMC_Authentication_Contract_V11',
		"'city'",
		"'account_type'",
		"'ethical_conduct_version'",
	),
	'File 00 consumer contract'
);

$google = sauth_three_plan_read( $root, 'includes/class-sauth-google-registration.php' );
sauth_three_plan_require(
	$google,
	array(
		'code_challenge_method',
		"'S256'",
		"'nonce'",
		'hash_equals',
		'email_verified',
		'finalize_link',
		'get_users',
		'SAUTH_Provider_Health',
	),
	'Google-first registration'
);

$routes = sauth_three_plan_read( $root, 'includes/class-sauth-canonical-routes.php' );
sauth_three_plan_require(
	$routes,
	array(
		"'^account/sessions/?$'",
		"'/account/sessions/'",
		"'canonical_repository'",
		"'02-sabri-authentication-and-accounts'",
		"'php_prefix'",
		"'SAUTH_'",
	),
	'canonical routes and naming'
);

$passkeys = sauth_three_plan_read( $root, 'includes/class-sauth-passkeys.php' );
sauth_three_plan_require(
	$passkeys,
	array(
		'smc_file02_authentication_assurance_v1',
		'webauthn.create',
		'webauthn.get',
		'parse_attestation_object',
		'cose_public_key_to_pem',
		'challenge_claim_key',
	),
	'fourth-plan passkey extension'
);

$readme = sauth_three_plan_read( $root, 'readme.txt' );
sauth_three_plan_require( $readme, array( 'Stable tag: 1.2.0', '/account/sessions/', 'Google-first registration', 'Passkey', 'city', 'ethical' ), 'readme' );

$status = sauth_three_plan_read( $root, 'STATUS.md' );
sauth_three_plan_require( $status, array( 'Version 1.2.0', 'Source coding', 'Automated-QA', 'Staging-Accepted', 'Operational', 'Passkey' ), 'status truth' );

$workflow = sauth_three_plan_read( $root, '.github/workflows/baseline-integrity.yml' );
sauth_three_plan_require( $workflow, array( 'three-plan-completion-unit.php', 'passkey-webauthn-unit.php', 'upload-artifact', '1.2.0' ), 'release workflow' );

echo "File 02 prior three-plan requirements preserved inside 1.2.0 four-plan candidate.\n";
