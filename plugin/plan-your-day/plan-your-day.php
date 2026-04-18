<?php
/**
 * Plugin Name: Plan Your Day
 * Description: A configurable day planning plugin for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: acodebeard
 * Text Domain: plan-your-day
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'PLAN_YOUR_DAY_VERSION', '0.1.0' );
define( 'PLAN_YOUR_DAY_SCHEMA_VERSION', 1 );
define( 'PLAN_YOUR_DAY_PLUGIN_FILE', __FILE__ );
define( 'PLAN_YOUR_DAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLAN_YOUR_DAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PLAN_YOUR_DAY_TEXT_DOMAIN', 'plan-your-day' );

$plan_your_day_autoload = PLAN_YOUR_DAY_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! is_readable( $plan_your_day_autoload ) ) {
	// Dev setup: run `composer install` inside the plugin directory.
	return;
}
require_once $plan_your_day_autoload;

register_activation_hook( __FILE__, [ \PlanYourDay\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \PlanYourDay\Deactivator::class, 'deactivate' ] );

\PlanYourDay\Plugin::instance()->init();
