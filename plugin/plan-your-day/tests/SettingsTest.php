<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Frontend\InterfaceCopy;
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
				'color_mode_default'       => 'dark',
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
		self::assertSame( 'dark', $sanitized['color_mode_default'] );
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

	public function test_invalid_color_mode_default_falls_back_to_light(): void {
		$sanitized = Settings::sanitize(
			[
				'color_mode_default' => 'sepia',
			]
		);

		self::assertSame( 'light', $sanitized['color_mode_default'] );
	}

	public function test_custom_start_option_helper_text_remains_editable_with_current_default(): void {
		self::assertSame(
			'Enter a hotel name, landmark, or street address.',
			InterfaceCopy::default_value( 'start_mode_custom_description' )
		);

		$sanitized = Settings::sanitize(
			[
				'interface_copy' => array_merge(
					InterfaceCopy::defaults(),
					[
						'start_mode_custom_description' => 'Add your preferred starting place.',
					]
				),
			]
		);

		self::assertSame(
			'Add your preferred starting place.',
			$sanitized['interface_copy']['start_mode_custom_description']
		);
	}

	public function test_sanitize_interface_copy_drops_removed_start_note_fields(): void {
		$sanitized = Settings::sanitize(
			[
				'interface_copy' => array_merge(
					InterfaceCopy::defaults(),
					[
						'start_default_note'             => 'Old default note',
						'start_current_note'             => 'Old current note',
						'start_custom_note'              => 'Old custom note',
						'start_custom_missing_note'      => 'Old missing note',
						'start_mode_current_description' => 'Removed current helper',
						'start_current_message'          => 'Removed current status',
						'start_default_fallback_summary' => 'Removed fallback summary',
						'start_card_help'                => 'Removed starting point helper',
						'custom_start_label'             => 'Removed custom start label',
						'start_custom_missing_message'   => 'Removed custom missing warning',
						'start_default_fallback_label'   => 'Removed fallback label',
						'start_mode_legend'              => 'Removed start mode legend',
						'start_mode_default_description' => 'Removed default description',
						'update_results_button'          => 'Removed update button',
						'start_current_handoff_label'    => 'Removed current handoff label',
						'start_current_handoff_summary'  => 'Removed current handoff summary',
						'start_default_location_fallback' => 'Removed default location fallback',
						'category_card_help'             => 'Removed search helper',
						'category_search_label'          => 'Removed search label',
						'category_search_button'         => 'Removed search button',
						'custom_results_heading'         => 'Removed custom results heading',
						'custom_results_description'     => 'Removed custom results description',
						'more_results_button'            => 'Removed more results button',
						'view_in_google_maps'            => 'Removed map link',
						'view_place_in_google_maps_aria' => 'Removed map aria',
						'in_trip'                        => 'Removed in trip',
						'already_in_trip_aria'           => 'Removed already in trip aria',
						'add_to_trip'                    => 'Removed add to trip',
						'add_waypoint_aria'              => 'Removed add waypoint aria',
						'search_results_unavailable_heading' => 'Removed unavailable heading',
						'search_results_unavailable_body' => 'Removed unavailable body',
						'search_results_prompt_heading'  => 'Removed prompt heading',
						'search_results_prompt_body_with_categories' => 'Removed prompt body with categories',
						'search_results_prompt_body_no_categories' => 'Removed prompt body no categories',
						'no_matching_results_heading'    => 'Removed no matching heading',
						'no_matching_results_body'       => 'Removed no matching body',
						'maps_link_label_search'         => 'Removed maps search link',
						'preview_mode_label_search'      => 'Removed preview label',
						'overview_initial_with_categories' => 'Removed overview with categories',
						'search_results_count_single'    => 'Removed single count',
						'search_results_count_plural'    => 'Removed plural count',
						'no_results_loaded_label'        => 'Removed no results loaded',
						'overview_browse_search'         => 'Removed browse overview',
						'search_preview_key_warning'     => 'Removed preview key warning',
						'overview_initial_no_categories' => 'Removed overview no categories',
						'clear_trip'                     => 'Removed clear trip',
						'move_up'                        => 'Removed move up',
						'move_down'                      => 'Removed move down',
						'loading_results_label'          => 'Removed loading results label',
						'loading_results_heading'        => 'Removed loading results heading',
						'loading_results_body'           => 'Removed loading results body',
						'loading_trip_count'             => 'Removed loading trip count',
						'loading_trip_heading'           => 'Removed loading trip heading',
						'loading_trip_body'              => 'Removed loading trip body',
						'loading_trip_preview_mode'      => 'Removed loading trip preview mode',
						'loading_trip_preview_heading'   => 'Removed loading trip preview heading',
						'loading_trip_preview_body'      => 'Removed loading trip preview body',
						'loading_search_preview_mode'    => 'Removed loading search preview mode',
						'loading_search_preview_heading' => 'Removed loading search preview heading',
						'loading_search_preview_body'    => 'Removed loading search preview body',
					]
				),
			]
		);

		self::assertArrayNotHasKey( 'start_default_note', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_current_note', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_custom_note', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_custom_missing_note', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_mode_current_description', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_current_message', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_default_fallback_summary', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_card_help', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'custom_start_label', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_custom_missing_message', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_default_fallback_label', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_mode_legend', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_mode_default_description', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'update_results_button', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_current_handoff_label', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_current_handoff_summary', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'start_default_location_fallback', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'category_card_help', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'category_search_label', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'category_search_button', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'custom_results_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'custom_results_description', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'more_results_button', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'view_in_google_maps', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'view_place_in_google_maps_aria', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'in_trip', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'already_in_trip_aria', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'add_to_trip', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'add_waypoint_aria', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_results_unavailable_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_results_unavailable_body', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_results_prompt_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_results_prompt_body_with_categories', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_results_prompt_body_no_categories', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'no_matching_results_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'no_matching_results_body', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'maps_link_label_search', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'preview_mode_label_search', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'overview_initial_with_categories', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_results_count_single', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_results_count_plural', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'no_results_loaded_label', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'overview_browse_search', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'search_preview_key_warning', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'overview_initial_no_categories', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'clear_trip', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'move_up', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'move_down', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_results_label', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_results_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_results_body', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_trip_count', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_trip_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_trip_body', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_trip_preview_mode', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_trip_preview_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_trip_preview_body', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_search_preview_mode', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_search_preview_heading', $sanitized['interface_copy'] );
		self::assertArrayNotHasKey( 'loading_search_preview_body', $sanitized['interface_copy'] );
		self::assertSame( 'Waypoints', $sanitized['interface_copy']['hero_title'] );
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

	public function test_get_categories_returns_empty_when_saved_list_is_empty(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );

		$settings = new Settings();

		self::assertSame( [], $settings->get_categories() );
	}

	public function test_get_categories_ignores_legacy_fallback_when_saved_list_is_empty(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'use_preset_categories' => true,
				]
			)
		);

		$settings = new Settings();

		self::assertSame( [], $settings->get_categories() );
	}

	public function test_seed_default_categories_if_needed_materializes_starter_rows_when_category_state_is_missing(): void {
		update_option(
			Settings::OPTION_NAME,
			array_diff_key(
				Settings::defaults(),
				[
					'categories' => true,
				]
			)
		);

		$settings = new Settings();

		self::assertTrue( $settings->seed_default_categories_if_needed() );
		self::assertSame( Settings::default_categories(), get_option( Settings::OPTION_NAME )['categories'] ?? [] );
		self::assertFalse( $settings->seed_default_categories_if_needed() );
	}

	public function test_seed_default_categories_if_needed_preserves_intentionally_empty_category_state(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );

		$settings = new Settings();

		self::assertFalse( $settings->seed_default_categories_if_needed() );
		self::assertSame( [], get_option( Settings::OPTION_NAME )['categories'] ?? [] );
	}

	public function test_seed_default_categories_if_needed_skips_when_legacy_fallback_is_disabled(): void {
		update_option(
			Settings::OPTION_NAME,
			array_diff_key(
				array_merge(
					Settings::defaults(),
					[
						'use_preset_categories' => false,
					]
				),
				[
					'categories' => true,
				]
			)
		);

		$settings = new Settings();

		self::assertFalse( $settings->seed_default_categories_if_needed() );
		self::assertSame( [], get_option( Settings::OPTION_NAME )['categories'] ?? [] );
	}

	public function test_maybe_upgrade_seeds_default_categories_and_updates_schema_version_when_category_state_is_missing(): void {
		update_option(
			Settings::OPTION_NAME,
			array_diff_key(
				Settings::defaults(),
				[
					'categories' => true,
				]
			)
		);
		update_option( 'plan_your_day_version', '0.0.1' );
		update_option( 'plan_your_day_schema_version', 1 );

		$settings = new Settings();
		$settings->maybe_upgrade();

		self::assertSame( PLAN_YOUR_DAY_VERSION, get_option( 'plan_your_day_version' ) );
		self::assertSame( Settings::default_categories(), get_option( Settings::OPTION_NAME )['categories'] ?? [] );
		self::assertSame( PLAN_YOUR_DAY_SCHEMA_VERSION, get_option( 'plan_your_day_schema_version' ) );
	}

	public function test_maybe_upgrade_preserves_intentionally_empty_categories(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );
		update_option( 'plan_your_day_version', '0.0.1' );
		update_option( 'plan_your_day_schema_version', 1 );

		$settings = new Settings();
		$settings->maybe_upgrade();

		self::assertSame( [], get_option( Settings::OPTION_NAME )['categories'] ?? [] );
		self::assertSame( PLAN_YOUR_DAY_SCHEMA_VERSION, get_option( 'plan_your_day_schema_version' ) );
	}

	public function test_maybe_upgrade_updates_stored_plugin_version_even_when_schema_is_current(): void {
		update_option( 'plan_your_day_version', '0.0.9' );
		update_option( 'plan_your_day_schema_version', PLAN_YOUR_DAY_SCHEMA_VERSION );

		$settings = new Settings();
		$settings->maybe_upgrade();

		self::assertSame( PLAN_YOUR_DAY_VERSION, get_option( 'plan_your_day_version' ) );
		self::assertSame( PLAN_YOUR_DAY_SCHEMA_VERSION, get_option( 'plan_your_day_schema_version' ) );
	}

	public function test_maybe_upgrade_prunes_removed_interface_copy_fields_for_existing_installs(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'categories'      => [
						[
							'label'      => 'Coffee',
							'text_query' => 'coffee shops',
						],
					],
					'interface_copy'  => array_merge(
						InterfaceCopy::defaults(),
						[
							'hero_title'            => 'Saved title',
							'category_search_label' => 'Removed category search label',
							'loading_results_body'  => 'Removed loading body',
							'move_up'               => 'Removed move up label',
						]
					),
					'unknown_setting' => 'Removed unknown setting',
				]
			)
		);
		update_option( 'plan_your_day_version', '0.1.0' );
		update_option( 'plan_your_day_schema_version', 2 );

		$settings = new Settings();
		$settings->maybe_upgrade();

		$saved_settings = get_option( Settings::OPTION_NAME );

		self::assertSame( 'Saved title', $saved_settings['interface_copy']['hero_title'] ?? null );
		self::assertArrayNotHasKey( 'category_search_label', $saved_settings['interface_copy'] ?? [] );
		self::assertArrayNotHasKey( 'loading_results_body', $saved_settings['interface_copy'] ?? [] );
		self::assertArrayNotHasKey( 'move_up', $saved_settings['interface_copy'] ?? [] );
		self::assertArrayNotHasKey( 'unknown_setting', $saved_settings );
		self::assertSame( PLAN_YOUR_DAY_VERSION, get_option( 'plan_your_day_version' ) );
		self::assertSame( PLAN_YOUR_DAY_SCHEMA_VERSION, get_option( 'plan_your_day_schema_version' ) );
	}

	public function test_maybe_upgrade_replaces_unmodified_legacy_default_categories(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'categories' => $this->legacy_default_categories(),
				]
			)
		);
		update_option( 'plan_your_day_schema_version', 3 );

		$settings = new Settings();
		$settings->maybe_upgrade();

		self::assertSame( Settings::default_categories(), get_option( Settings::OPTION_NAME )['categories'] ?? [] );
		self::assertSame( PLAN_YOUR_DAY_SCHEMA_VERSION, get_option( 'plan_your_day_schema_version' ) );
	}

	public function test_maybe_upgrade_preserves_customized_categories(): void {
		$custom_categories = Settings::sanitize_categories(
			[
				[
					'slug'        => 'coffee',
					'label'       => 'Morning stops',
					'description' => 'Custom copy',
					'text_query'  => 'coffee near waterfront',
					'enabled'     => true,
					'sort_order'  => 10,
				],
				[
					'slug'        => 'activities',
					'label'       => 'Special activities',
					'description' => 'Custom activities',
					'text_query'  => 'guided activities',
					'enabled'     => true,
					'sort_order'  => 20,
				],
			]
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'categories' => $custom_categories,
				]
			)
		);
		update_option( 'plan_your_day_schema_version', 3 );

		$settings = new Settings();
		$settings->maybe_upgrade();

		self::assertSame( $custom_categories, get_option( Settings::OPTION_NAME )['categories'] ?? [] );
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
							'hero_intro'             => '',
							'setup_notice_body'      => 'Missing: {settings}.',
						]
					),
				]
			)
		);

		$settings = new Settings();

		self::assertSame( 'Build Your Route', $settings->get_frontend_copy_value( 'hero_title' ) );
		self::assertSame( '', $settings->get_frontend_copy_value( 'hero_intro' ) );
		self::assertSame(
			'Missing: Default location label.',
			$settings->format_frontend_copy( 'setup_notice_body', [ 'settings' => 'Default location label' ] )
		);
	}

	private function legacy_default_categories(): array {
		return Settings::sanitize_categories(
			[
				[
					'slug'        => 'coffee',
					'label'       => 'Coffee',
					'description' => 'Search for coffee shops, cafes, tastings, and easy morning stops.',
					'text_query'  => 'coffee shops and cafes',
					'enabled'     => true,
					'sort_order'  => 10,
				],
				[
					'slug'        => 'food',
					'label'       => 'Food',
					'description' => 'Search for restaurants, quick bites, and broader local food options.',
					'text_query'  => 'restaurants and local food',
					'enabled'     => true,
					'sort_order'  => 20,
				],
				[
					'slug'        => 'shopping',
					'label'       => 'Shopping',
					'description' => 'Search for boutiques, markets, and places to browse local goods.',
					'text_query'  => 'shopping and local boutiques',
					'enabled'     => true,
					'sort_order'  => 30,
				],
				[
					'slug'        => 'outdoors',
					'label'       => 'Outdoors',
					'description' => 'Search for parks, waterfront access, trails, and outdoor stops.',
					'text_query'  => 'parks and outdoor activities',
					'enabled'     => true,
					'sort_order'  => 40,
				],
				[
					'slug'        => 'history-culture',
					'label'       => 'History / culture',
					'description' => 'Search for museums, landmarks, heritage sites, and cultural experiences.',
					'text_query'  => 'history and culture',
					'enabled'     => true,
					'sort_order'  => 50,
				],
				[
					'slug'        => 'scenic',
					'label'       => 'Scenic spots',
					'description' => 'Search for viewpoints, waterfront stretches, and scenic lookouts.',
					'text_query'  => 'scenic spots and viewpoints',
					'enabled'     => true,
					'sort_order'  => 60,
				],
				[
					'slug'        => 'activities',
					'label'       => 'Other activities',
					'description' => 'Search for tours, family-friendly attractions, and broader things to do.',
					'text_query'  => 'tours and activities',
					'enabled'     => true,
					'sort_order'  => 70,
				],
			]
		);
	}
}
