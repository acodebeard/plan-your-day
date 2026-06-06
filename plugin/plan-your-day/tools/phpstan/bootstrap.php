<?php
declare( strict_types=1 );

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
defined( 'WPINC' ) || define( 'WPINC', 'wp-includes' );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
defined( 'WEEK_IN_SECONDS' ) || define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
defined( 'MONTH_IN_SECONDS' ) || define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );

defined( 'PLAN_YOUR_DAY_VERSION' ) || define( 'PLAN_YOUR_DAY_VERSION', '0.5' );
defined( 'PLAN_YOUR_DAY_SCHEMA_VERSION' ) || define( 'PLAN_YOUR_DAY_SCHEMA_VERSION', 5 );
defined( 'PLAN_YOUR_DAY_PLUGIN_FILE' ) || define( 'PLAN_YOUR_DAY_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/plan-your-day.php' );
defined( 'PLAN_YOUR_DAY_PLUGIN_DIR' ) || define( 'PLAN_YOUR_DAY_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
defined( 'PLAN_YOUR_DAY_PLUGIN_URL' ) || define( 'PLAN_YOUR_DAY_PLUGIN_URL', 'https://example.test/wp-content/plugins/plan-your-day/' );
defined( 'PLAN_YOUR_DAY_TEXT_DOMAIN' ) || define( 'PLAN_YOUR_DAY_TEXT_DOMAIN', 'plan-your-day' );
