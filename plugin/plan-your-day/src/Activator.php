<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay;

use Acodebeard\PlanYourDay\Admin\LegacyConfigMigrator;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Activator {
	public static function activate(): void {
		$settings = new Settings();

		add_option( Settings::OPTION_NAME, Settings::defaults(), '', false );
		( new LegacyConfigMigrator( $settings ) )->import_if_recommended();
		$settings->seed_default_categories_if_needed();

		update_option( 'plan_your_day_version', PLAN_YOUR_DAY_VERSION );
		update_option( 'plan_your_day_schema_version', PLAN_YOUR_DAY_SCHEMA_VERSION );
	}
}
