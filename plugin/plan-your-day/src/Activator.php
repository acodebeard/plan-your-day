<?php
declare( strict_types=1 );

namespace PlanYourDay;

defined( 'ABSPATH' ) || exit;

final class Activator {
	public static function activate(): void {
		update_option( 'plan_your_day_version', PLAN_YOUR_DAY_VERSION );
		update_option( 'plan_your_day_schema_version', PLAN_YOUR_DAY_SCHEMA_VERSION );
	}
}
