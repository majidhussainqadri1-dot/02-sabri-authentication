<?php
/**
 * Plugin Name: Sabri Authentication and Accounts
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Email/password and Google authentication orchestration, registration, recovery, risk challenge, session controls and authentication assurance for the Sabri Social Homeopathy Platform. Requires Sabri Membership Core.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-authentication
 */

defined( 'ABSPATH' ) || exit;

/* Canonical File 02 constitution. */
define( 'SAUTH_VERSION', '1.1.0' );
define( 'SAUTH_DB_VERSION', '1.1.0' );
define( 'SAUTH_ACCOUNT_CONTRACT_VERSION', '1.1.0' );
define( 'SAUTH_AUTH_EVENT_SCHEMA_VERSION', '1.0.0' );
define( 'SAUTH_CF01_ASSURANCE_VERSION', '1.0.0' );
define( 'SAUTH_PROFESSIONAL_REAUTH_VERSION', '1.0.0' );
define( 'SAUTH_FILE', __FILE__ );
define( 'SAUTH_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAUTH_URL', plugin_dir_url( __FILE__ ) );

/* Backward-compatible aliases for pre-1.1.0 integrations. */
define( 'SA_VERSION', SAUTH_VERSION );
define( 'SA_DB_VERSION', SAUTH_DB_VERSION );
define( 'SA_ACCOUNT_CONTRACT_VERSION', SAUTH_ACCOUNT_CONTRACT_VERSION );
define( 'SA_AUTH_EVENT_SCHEMA_VERSION', SAUTH_AUTH_EVENT_SCHEMA_VERSION );
define( 'SA_CF01_ASSURANCE_VERSION', SAUTH_CF01_ASSURANCE_VERSION );
define( 'SA_PROFESSIONAL_REAUTH_VERSION', SAUTH_PROFESSIONAL_REAUTH_VERSION );
define( 'SA_FILE', SAUTH_FILE );
define( 'SA_DIR', SAUTH_DIR );
define( 'SA_URL', SAUTH_URL );

require_once SAUTH_DIR . 'includes/class-sa-security.php';
require_once SAUTH_DIR . 'includes/class-sauth-provider-health.php';
require_once SAUTH_DIR . 'includes/class-sauth-account-contract.php';
require_once SAUTH_DIR . 'includes/class-sauth-event-outbox.php';
require_once SAUTH_DIR . 'includes/class-sauth-email-verification.php';
require_once SAUTH_DIR . 'includes/class-sa-authentication-assurance.php';
require_once SAUTH_DIR . 'includes/class-sauth-completion-resolver.php';
require_once SAUTH_DIR . 'includes/class-sauth-login-risk.php';
require_once SAUTH_DIR . 'includes/class-sauth-session-manager.php';
require_once SAUTH_DIR . 'includes/class-sa-professional-reauthentication.php';
require_once SAUTH_DIR . 'includes/class-sa-membership-adapter.php';
require_once SAUTH_DIR . 'includes/class-sa-activator.php';
require_once SAUTH_DIR . 'includes/class-sa-registration.php';
require_once SAUTH_DIR . 'includes/class-sa-profile.php';
require_once SAUTH_DIR . 'includes/class-sa-google-oauth.php';
require_once SAUTH_DIR . 'includes/class-sauth-google-registration.php';
require_once SAUTH_DIR . 'includes/class-sa-access-control.php';
require_once SAUTH_DIR . 'includes/class-sa-privacy.php';
require_once SAUTH_DIR . 'includes/class-sauth-operations.php';
require_once SAUTH_DIR . 'includes/class-sauth-provider-http-guard.php';
require_once SAUTH_DIR . 'includes/class-sa-plugin.php';

/* Canonical class names with legacy implementation aliases. */
foreach ( array(
	'SA_Security'                    => 'SAUTH_Security',
	'SA_Membership_Adapter'          => 'SAUTH_Membership_Adapter',
	'SA_Activator'                   => 'SAUTH_Activator',
	'SA_Registration'                => 'SAUTH_Registration',
	'SA_Profile'                     => 'SAUTH_Profile',
	'SA_Google_OAuth'                => 'SAUTH_Google_OAuth',
	'SA_Access_Control'              => 'SAUTH_Access_Control',
	'SA_Privacy'                     => 'SAUTH_Privacy',
	'SA_Plugin'                      => 'SAUTH_Plugin',
	'SA_Authentication_Assurance'    => 'SAUTH_Authentication_Assurance',
	'SA_Professional_Reauthentication'=> 'SAUTH_Professional_Reauthentication',
) as $legacy => $canonical ) {
	if ( class_exists( $legacy, false ) && ! class_exists( $canonical, false ) ) {
		class_alias( $legacy, $canonical );
	}
}

register_activation_hook( SAUTH_FILE, array( 'SAUTH_Activator', 'activate' ) );
register_deactivation_hook( SAUTH_FILE, array( 'SAUTH_Activator', 'deactivate' ) );

function sauth_start_plugin() {
	SAUTH_Provider_Health::init();
	SAUTH_Provider_HTTP_Guard::init();
	SAUTH_Event_Outbox::init();
	SAUTH_Email_Verification::init();
	SAUTH_Authentication_Assurance::init();
	SAUTH_Login_Risk::init();
	SAUTH_Session_Manager::init();
	SAUTH_Professional_Reauthentication::init();
	SAUTH_Google_Registration::init();
	SAUTH_Operations::init();
	$plugin = new SAUTH_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'sauth_start_plugin', 30 );

/* Legacy bootstrap hook retained for integrations that explicitly call it. */
if ( ! function_exists( 'sa_start_plugin' ) ) {
	function sa_start_plugin() {
		return sauth_start_plugin();
	}
}
