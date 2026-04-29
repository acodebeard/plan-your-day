<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay;

use Acodebeard\PlanYourDay\Admin\LegacyConfigMigrator;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Activator {
	public static function activate(): void {
		update_option( 'plan_your_day_version', PLAN_YOUR_DAY_VERSION );
		update_option( 'plan_your_day_schema_version', PLAN_YOUR_DAY_SCHEMA_VERSION );
		add_option( Settings::OPTION_NAME, Settings::defaults(), '', false );

		( new LegacyConfigMigrator( new Settings() ) )->import_if_recommended();
	}
}
