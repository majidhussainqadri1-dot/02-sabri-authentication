<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sa_version' );
delete_option( 'sa_db_version' );
delete_option( 'sa_google_enabled' );
delete_option( 'sa_google_client_id' );
delete_option( 'sa_google_client_secret' );
delete_option( 'sa_allow_registration' ); // Legacy option from 0.1.0.
delete_option( 'sa_page_map' );
delete_transient( 'sa_activation_notice' );

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sa_rate_limits" );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'sa_google_link_lock_' ) . '%' ) );

// Managed pages, Membership Core accounts/roles, audit records, and user metadata
// are preserved to prevent silent identity or account loss. Privacy erasure and
// explicit administrative cleanup remain available before plugin removal.
