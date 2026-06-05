<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests {
	use Acodebeard\PlanYourDay\Activator;
	use Acodebeard\PlanYourDay\Settings\Settings;
	use PHPUnit\Framework\TestCase;

	final class ActivatorTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['plan_your_day_test_options'] = [];
			$GLOBALS['plan_your_day_test_filters'] = [];

			if ( ! defined( 'PLAN_YOUR_DAY_VERSION' ) ) {
				define( 'PLAN_YOUR_DAY_VERSION', 'test-version' );
			}

			if ( ! defined( 'PLAN_YOUR_DAY_SCHEMA_VERSION' ) ) {
				define( 'PLAN_YOUR_DAY_SCHEMA_VERSION', 3 );
			}
		}

		public function test_activate_seeds_default_categories_and_version_metadata(): void {
			Activator::activate();

			$settings = get_option( Settings::OPTION_NAME );

			self::assertSame( 'test-version', get_option( 'plan_your_day_version' ) );
			self::assertSame( PLAN_YOUR_DAY_SCHEMA_VERSION, get_option( 'plan_your_day_schema_version' ) );
			self::assertSame( Settings::default_categories(), $settings['categories'] ?? [] );
		}

		public function test_activate_does_not_overwrite_existing_plugin_settings(): void {
			update_option(
				Settings::OPTION_NAME,
				array_merge(
					Settings::defaults(),
					[
						'default_location_label'   => 'Existing location',
						'default_location_address' => 'Existing address',
					]
				)
			);

			Activator::activate();

			$settings = get_option( Settings::OPTION_NAME );

			self::assertSame( 'Existing location', $settings['default_location_label'] );
			self::assertSame( 'Existing address', $settings['default_location_address'] );
			self::assertSame( Settings::default_categories(), $settings['categories'] ?? [] );
		}
	}
}
