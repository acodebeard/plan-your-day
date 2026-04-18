<?php
/**
 * Uninstall routine — removes plugin-scoped options created by the Activator.
 * Runs only when an administrator explicitly uninstalls the plugin.
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'plan_your_day_version' );
delete_option( 'plan_your_day_schema_version' );
delete_option( 'plan_your_day_settings' );
