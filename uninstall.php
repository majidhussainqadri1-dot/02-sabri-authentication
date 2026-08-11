<?php
/**
 * Sabri Authentication uninstall policy.
 *
 * Removing plugin code must not silently destroy account links, security
 * evidence, session projections, route ownership or migration state. A
 * separately reviewed, authenticated, backup-protected and audited purge tool
 * is required for destructive removal.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Intentionally no destructive action.
