<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['plan_your_day_test_options'] = [];
	}

	public function test_sanitize_normalizes_settings_values(): void {
		$sanitized = Settings::sanitize(
			[
				'default_location_label'   => '  <b>Harbor Start</b>  ',
				'default_location_address' => " 123 Main St \n",
				'default_location_latitude' => '19.640000',
				'default_location_longitude' => '200',
				'allowed_start_modes'      => [ 'current', 'bogus', 'custom', 'current' ],
				'max_waypoints'            => '99',
				'result_count'             => '0',
				'distance_unit'            => 'kilometers',
				'map_preview_enabled'      => '1',
				'maps_handoff_enabled'     => 0,
				'google_maps_embed_api_key' => ' key-123!@# ',
				'google_places_api_key'    => ' places:key ',
				'google_api_timeout'       => 45,
				'google_text_search_cache_ttl' => WEEK_IN_SECONDS + 10,
				'rate_limit_per_minute'    => '400',
				'trusted_proxy_cidrs'      => "10.0.0.0/8\ninvalid\n2001:db8::/32",
			]
		);

		self::assertSame( 'Harbor Start', $sanitized['default_location_label'] );
		self::assertSame( '123 Main St', $sanitized['default_location_address'] );
		self::assertSame( '19.64', $sanitized['default_location_latitude'] );
		self::assertSame( '', $sanitized['default_location_longitude'] );
		self::assertSame( [ 'current', 'custom' ], $sanitized['allowed_start_modes'] );
		self::assertSame( 25, $sanitized['max_waypoints'] );
		self::assertSame( 1, $sanitized['result_count'] );
		self::assertSame( 'kilometers', $sanitized['distance_unit'] );
		self::assertTrue( $sanitized['map_preview_enabled'] );
		self::assertFalse( $sanitized['maps_handoff_enabled'] );
		self::assertSame( 'key-123', $sanitized['google_maps_embed_api_key'] );
		self::assertSame( 'placeskey', $sanitized['google_places_api_key'] );
		self::assertSame( 30, $sanitized['google_api_timeout'] );
		self::assertSame( WEEK_IN_SECONDS, $sanitized['google_text_search_cache_ttl'] );
		self::assertSame( 400, $sanitized['rate_limit_per_minute'] );
		self::assertSame( "10.0.0.0/8\n2001:db8::/32", $sanitized['trusted_proxy_cidrs'] );
	}

	public function test_sanitize_categories_discards_invalid_rows_and_makes_unique_slugs(): void {
		$sanitized = Settings::sanitize_categories(
			[
				[
					'label'       => 'Coffee',
					'description' => 'Morning stops',
					'text_query'  => 'coffee shops',
					'slug'        => 'coffee',
					'enabled'     => '1',
					'sort_order'  => 20,
				],
				[
					'label'       => 'Coffee',
					'description' => 'Second entry',
					'text_query'  => 'cafes',
					'slug'        => 'coffee',
					'enabled'     => '0',
					'sort_order'  => 10,
				],
				[
					'label'       => '',
					'description' => 'Missing label',
					'text_query'  => 'ignored',
				],
				[
					'label'       => 'Remove me',
					'description' => '',
					'text_query'  => 'remove',
					'remove'      => '1',
				],
			]
		);

		self::assertCount( 2, $sanitized );
		self::assertSame( 'coffee-2', $sanitized[0]['slug'] );
		self::assertFalse( $sanitized[0]['enabled'] );
		self::assertSame( 'coffee', $sanitized[1]['slug'] );
		self::assertTrue( $sanitized[1]['enabled'] );
	}

	public function test_geocoding_key_falls_back_to_places_key(): void {
		update_option(
			Settings::OPTION_NAME,
			[
				'google_places_api_key'    => 'places-key',
				'google_geocoding_api_key' => '',
			]
		);

		$settings = new Settings();

		self::assertSame( 'places-key', $settings->get_google_geocoding_api_key() );
	}
}
