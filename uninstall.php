<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'sa_version' );
delete_option( 'sa_google_enabled' );
delete_option( 'sa_google_client_id' );
delete_option( 'sa_google_client_secret' );
delete_option( 'sa_allow_registration' );
delete_transient( 'sa_activation_notice' );
// Accounts, roles, user metadata and pages are preserved to prevent data loss.

