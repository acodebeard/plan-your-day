<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['plan_your_day_test_options'] = [];
		$GLOBALS['plan_your_day_test_actions'] = [];
		$GLOBALS['plan_your_day_test_option_reads'] = [];
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

	public function test_defaults_use_eight_google_results(): void {
		self::assertSame( 8, Settings::defaults()['result_count'] );
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

	public function test_get_categories_returns_starter_rows_when_saved_list_is_empty_and_fallback_is_enabled(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );

		$settings = new Settings();

		self::assertSame( Settings::default_categories(), $settings->get_categories() );
	}

	public function test_get_categories_returns_empty_when_saved_list_is_empty_and_fallback_is_disabled(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'use_preset_categories' => false,
				]
			)
		);

		$settings = new Settings();

		self::assertSame( [], $settings->get_categories() );
	}

	public function test_seed_default_categories_if_needed_materializes_starter_rows_once(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );

		$settings = new Settings();

		self::assertTrue( $settings->seed_default_categories_if_needed() );
		self::assertSame( Settings::default_categories(), get_option( Settings::OPTION_NAME )['categories'] ?? [] );
		self::assertFalse( $settings->seed_default_categories_if_needed() );
	}

	public function test_seed_default_categories_if_needed_skips_when_fallback_is_disabled(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'use_preset_categories' => false,
				]
			)
		);

		$settings = new Settings();

		self::assertFalse( $settings->seed_default_categories_if_needed() );
		self::assertSame( [], get_option( Settings::OPTION_NAME )['categories'] ?? [] );
	}

	public function test_maybe_upgrade_seeds_default_categories_and_updates_schema_version_for_existing_empty_installs(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );
		update_option( 'plan_your_day_schema_version', 1 );

		$settings = new Settings();
		$settings->maybe_upgrade();

		self::assertSame( Settings::default_categories(), get_option( Settings::OPTION_NAME )['categories'] ?? [] );
		self::assertSame( 2, get_option( 'plan_your_day_schema_version' ) );
	}

	public function test_sanitize_trusted_proxy_cidrs_accepts_ipv4_and_ipv6_edge_masks(): void {
		$sanitized = Settings::sanitize_trusted_proxy_cidrs(
			" 0.0.0.0/0,\n192.0.2.10/32\n2001:DB8::/32\n::/0\n2001:db8::1/128\nfe80::/10 "
		);

		self::assertSame(
			"0.0.0.0/0\n192.0.2.10/32\n2001:DB8::/32\n::/0\n2001:db8::1/128\nfe80::/10",
			$sanitized
		);
	}

	public function test_sanitize_trusted_proxy_cidrs_rejects_zone_ids_and_invalid_mask_lengths(): void {
		$sanitized = Settings::sanitize_trusted_proxy_cidrs(
			"fe80::1%eth0/64\n192.0.2.1/33\n192.0.2.1/128\n2001:db8::/129\n10.0.0.0/8"
		);

		self::assertSame( '10.0.0.0/8', $sanitized );
	}

	public function test_getters_reuse_memoized_settings_for_the_same_request(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'default_location_label'   => 'Harbor Start',
					'default_location_address' => '123 Main St',
				]
			)
		);

		$settings = new Settings();

		self::assertSame( 'Harbor Start', $settings->get_default_location_label() );
		self::assertSame( '123 Main St', $settings->get_default_location_address() );
		self::assertSame( 1, $GLOBALS['plan_your_day_test_option_reads'][ Settings::OPTION_NAME ] ?? 0 );
	}

	public function test_settings_cache_is_flushed_when_the_option_changes(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'default_location_label' => 'Before',
				]
			)
		);

		$settings = new Settings();

		self::assertSame( 'Before', $settings->get_default_location_label() );

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'default_location_label' => 'After',
				]
			)
		);

		self::assertSame( 'After', $settings->get_default_location_label() );
		self::assertSame( 2, $GLOBALS['plan_your_day_test_option_reads'][ Settings::OPTION_NAME ] ?? 0 );
	}

	public function test_frontend_copy_getters_apply_saved_values_and_named_tokens(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'interface_copy' => array_merge(
						Settings::defaults()['interface_copy'],
						[
							'hero_title'             => 'Build Your Route',
							'setup_notice_body'      => 'Missing: {settings}.',
							'category_card_help'     => '',
							'update_results_button'  => '',
						]
					),
				]
			)
		);

		$settings = new Settings();

		self::assertSame( 'Build Your Route', $settings->get_frontend_copy_value( 'hero_title' ) );
		self::assertSame( '', $settings->get_frontend_copy_value( 'category_card_help' ) );
		self::assertSame( 'Update results', $settings->get_frontend_copy_value( 'update_results_button' ) );
		self::assertSame(
			'Missing: Default location label.',
			$settings->format_frontend_copy( 'setup_notice_body', [ 'settings' => 'Default location label' ] )
		);
	}
}
