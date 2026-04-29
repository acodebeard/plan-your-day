<?php
declare( strict_types=1 );

namespace {
	function plan_your_day_test_legacy_settings( mixed $legacy_settings ): array {
		$legacy_settings = is_array( $legacy_settings ) ? $legacy_settings : [];
		$overrides       = $GLOBALS['plan_your_day_legacy_settings'] ?? [];

		return array_merge( $legacy_settings, is_array( $overrides ) ? $overrides : [] );
	}
}

namespace Acodebeard\PlanYourDay\Tests {
	use Acodebeard\PlanYourDay\Activator;
	use Acodebeard\PlanYourDay\Settings\Settings;
	use PHPUnit\Framework\TestCase;

	final class ActivatorTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['plan_your_day_test_options']  = [];
			$GLOBALS['plan_your_day_test_filters']  = [];
			$GLOBALS['plan_your_day_legacy_settings'] = [];

			if ( ! defined( 'PLAN_YOUR_DAY_VERSION' ) ) {
				define( 'PLAN_YOUR_DAY_VERSION', 'test-version' );
			}

			if ( ! defined( 'PLAN_YOUR_DAY_SCHEMA_VERSION' ) ) {
				define( 'PLAN_YOUR_DAY_SCHEMA_VERSION', 1 );
			}

			if ( ! defined( 'PLAN_YOUR_DAY_LEGACY_CONFIG_FILE' ) ) {
				define( 'PLAN_YOUR_DAY_LEGACY_CONFIG_FILE', sys_get_temp_dir() . '/plan-your-day-legacy-config-test.php' );
			}

			$this->write_legacy_config_file( [] );
			add_filter( 'plan_your_day_legacy_settings', 'plan_your_day_test_legacy_settings' );
		}

		public function test_activate_imports_legacy_config_when_plugin_settings_are_empty(): void {
			$GLOBALS['plan_your_day_legacy_settings'] = [
				'default_location_label'     => 'Harbor Start',
				'default_location_address'   => '123 Ocean View Ave',
				'default_location_latitude'  => '19.639994',
				'default_location_longitude' => '-155.996933',
				'default_location_place_id'  => 'ChIJHarborStart',
				'allowed_start_modes'        => [ 'current', 'default', 'custom' ],
				'google_maps_embed_api_key'  => 'embed-key_123',
				'google_places_api_key'      => 'places-key_456',
				'google_geocoding_api_key'   => 'geocode-key_789',
				'categories'                 => [
					[
						'slug'        => 'beaches',
						'label'       => 'Beaches',
						'description' => 'Beach stops',
						'text_query'  => 'beaches',
						'enabled'     => true,
						'sort_order'  => 10,
					],
				],
			];

			Activator::activate();

			$settings = get_option( Settings::OPTION_NAME );

			self::assertSame( 'test-version', get_option( 'plan_your_day_version' ) );
			self::assertSame( 1, get_option( 'plan_your_day_schema_version' ) );
			self::assertSame( 'Harbor Start', $settings['default_location_label'] );
			self::assertSame( '123 Ocean View Ave', $settings['default_location_address'] );
			self::assertSame( '19.639994', $settings['default_location_latitude'] );
			self::assertSame( '-155.996933', $settings['default_location_longitude'] );
			self::assertSame( 'ChIJHarborStart', $settings['default_location_place_id'] );
			self::assertSame( [ Settings::START_MODE_CURRENT, Settings::START_MODE_DEFAULT, Settings::START_MODE_CUSTOM ], $settings['allowed_start_modes'] );
			self::assertSame( 'embed-key_123', $settings['google_maps_embed_api_key'] );
			self::assertSame( 'places-key_456', $settings['google_places_api_key'] );
			self::assertSame( 'geocode-key_789', $settings['google_geocoding_api_key'] );
			self::assertSame( 'beaches', $settings['categories'][0]['slug'] ?? null );
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

			$GLOBALS['plan_your_day_legacy_settings'] = [
				'default_location_label'   => 'Legacy location',
				'default_location_address' => 'Legacy address',
			];

			Activator::activate();

			$settings = get_option( Settings::OPTION_NAME );

			self::assertSame( 'Existing location', $settings['default_location_label'] );
			self::assertSame( 'Existing address', $settings['default_location_address'] );
		}

		public function test_activate_imports_standalone_config_file_api_keys(): void {
			$this->write_legacy_config_file(
				[
					'google_maps_embed_api_key' => 'config-embed-key',
					'google_places_api_key'     => 'config-places-key',
					'google_geocoding_api_key'  => 'config-geocode-key',
				]
			);

			Activator::activate();

			$settings = get_option( Settings::OPTION_NAME );

			self::assertSame( 'config-embed-key', $settings['google_maps_embed_api_key'] );
			self::assertSame( 'config-places-key', $settings['google_places_api_key'] );
			self::assertSame( 'config-geocode-key', $settings['google_geocoding_api_key'] );
		}

		private function write_legacy_config_file( array $legacy_settings ): void {
			file_put_contents(
				PLAN_YOUR_DAY_LEGACY_CONFIG_FILE,
				"<?php\n"
				. "defined( 'PLAN_YOUR_DAY_LEGACY_BOOTSTRAP' ) || exit;\n\n"
				. 'return ' . var_export( $legacy_settings, true ) . ";\n"
			);
		}
	}
}
