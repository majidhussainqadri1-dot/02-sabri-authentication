<?php
/**
 * Plugin Name: Sabri Authentication and Accounts
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Google sign-in, account recovery, and authentication assurance integration for the Sabri Social Homeopathy Platform. Requires Sabri Membership Core.
 * Version: 0.3.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: sabri-authentication
 */

defined( 'ABSPATH' ) || exit;

define( 'SA_VERSION', '0.3.0' );
define( 'SA_DB_VERSION', '0.2.0' );
define( 'SA_CF01_ASSURANCE_VERSION', '1.0.0' );
define( 'SA_FILE', __FILE__ );
define( 'SA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SA_URL', plugin_dir_url( __FILE__ ) );

require_once SA_DIR . 'includes/class-sa-security.php';
require_once SA_DIR . 'includes/class-sa-authentication-assurance.php';
require_once SA_DIR . 'includes/class-sa-membership-adapter.php';
require_once SA_DIR . 'includes/class-sa-activator.php';
require_once SA_DIR . 'includes/class-sa-registration.php';
require_once SA_DIR . 'includes/class-sa-profile.php';
require_once SA_DIR . 'includes/class-sa-google-oauth.php';
require_once SA_DIR . 'includes/class-sa-access-control.php';
require_once SA_DIR . 'includes/class-sa-privacy.php';
require_once SA_DIR . 'includes/class-sa-plugin.php';

register_activation_hook( SA_FILE, array( 'SA_Activator', 'activate' ) );
register_deactivation_hook( SA_FILE, array( 'SA_Activator', 'deactivate' ) );

function sa_start_plugin() {
	$plugin = new SA_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'sa_start_plugin', 30 );
