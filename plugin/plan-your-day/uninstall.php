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

global $wpdb;

if ( isset( $wpdb ) && isset( $wpdb->options ) && method_exists( $wpdb, 'esc_like' ) && method_exists( $wpdb, 'prepare' ) ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'plan_your_day_rate_' ) . '%'
		)
	);
}

$plan_your_day_google_cache_keys = get_option( 'plan_your_day_google_cache_keys', [] );

if ( is_array( $plan_your_day_google_cache_keys ) ) {
	foreach ( $plan_your_day_google_cache_keys as $plan_your_day_google_cache_key ) {
		if ( ! is_scalar( $plan_your_day_google_cache_key ) ) {
			continue;
		}

		$plan_your_day_google_cache_key = (string) $plan_your_day_google_cache_key;

		if ( str_starts_with( $plan_your_day_google_cache_key, 'pyd_google_' ) ) {
			delete_transient( $plan_your_day_google_cache_key );
		}
	}
}

delete_option( 'plan_your_day_google_cache_keys' );
