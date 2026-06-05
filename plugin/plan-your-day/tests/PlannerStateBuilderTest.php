<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Frontend\InterfaceCopy;
use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Google\GoogleApiResult;
use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Planner\DistanceFormatter;
use Acodebeard\PlanYourDay\Planner\MapUrlBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
use Acodebeard\PlanYourDay\Planner\StartContextResolver;
use Acodebeard\PlanYourDay\Planner\WaypointList;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class PlannerStateBuilderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ] = array_merge(
			Settings::defaults(),
			[
				'default_location_label'   => 'Downtown',
				'default_location_address' => '123 Main St',
				'map_preview_enabled'      => false,
				'maps_handoff_enabled'     => false,
				'google_api_timeout'       => 15,
				'allowed_start_modes'      => [
					Settings::START_MODE_CURRENT,
					Settings::START_MODE_DEFAULT,
					Settings::START_MODE_CUSTOM,
				],
			]
		);
	}

	public function test_build_uses_shorter_place_details_timeout_for_bounded_trip_resolution(): void {
		$google_api_client = new FakePlannerGoogleApiClient(
			[
				'place-a' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-a', 'Alpha' ),
					]
				),
				'place-b' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-b', 'Beta' ),
					]
				),
			]
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'selected_waypoint_ids' => [ 'place-a', 'place-b' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'                    => false,
				'include_trip_waypoints'             => true,
				'trip_waypoint_deadline_at'          => microtime( true ) + 60,
				'trip_waypoint_place_details_timeout' => 4,
			]
		);

		self::assertSame( [ 4, 4 ], $google_api_client->timeouts );
		self::assertCount( 2, $state['trip_waypoints'] );
	}

	public function test_build_stops_loading_trip_waypoints_after_deadline(): void {
		$google_api_client = new FakePlannerGoogleApiClient(
			[
				'place-a' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-a', 'Alpha' ),
					]
				),
			]
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'selected_waypoint_ids' => [ 'place-a', 'place-b' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => true,
				'trip_waypoint_deadline_at' => microtime( true ) - 1,
			]
		);

		self::assertSame( [], $google_api_client->requested_place_ids );
		self::assertSame( [ 'place-a', 'place-b' ], $state['selected_waypoint_ids'] );
		self::assertSame(
			'The trip preview stopped loading more places before the request timed out. Try again or remove a few stops.',
			$state['messages'][0]['text'] ?? ''
		);
	}

	public function test_build_stops_loading_trip_waypoints_after_repeated_failures(): void {
		$google_api_client = new FakePlannerGoogleApiClient(
			[
				'place-a' => GoogleApiResult::failure( 'place_details_unavailable', 'failed', 503 ),
				'place-b' => GoogleApiResult::failure( 'place_details_unavailable', 'failed', 503 ),
				'place-c' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-c', 'Gamma' ),
					]
				),
			]
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'selected_waypoint_ids' => [ 'place-a', 'place-b', 'place-c' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'                    => false,
				'include_trip_waypoints'             => true,
				'trip_waypoint_deadline_at'          => microtime( true ) + 60,
				'trip_waypoint_place_details_timeout' => 4,
				'trip_waypoint_max_failures'         => 2,
			]
		);

		self::assertSame( [ 'place-a', 'place-b' ], $google_api_client->requested_place_ids );
		self::assertSame( [ 'place-a', 'place-b', 'place-c' ], $state['selected_waypoint_ids'] );
		self::assertCount( 2, $state['trip_waypoints'] );
		self::assertTrue( $state['trip_waypoints'][0]['unresolved'] ?? false );
		self::assertTrue( $state['trip_waypoints'][1]['unresolved'] ?? false );
		self::assertSame(
			'The trip preview stopped loading more places after repeated Google place errors. Try again later or remove any invalid stops.',
			$state['messages'][2]['text'] ?? ''
		);
		self::assertSame(
			'The trip preview and Google Maps handoff will stay unavailable until every selected place loads successfully.',
			$state['messages'][3]['text'] ?? ''
		);
	}

	public function test_build_preserves_selected_waypoint_ids_and_returns_placeholder_for_single_failed_place(): void {
		$google_api_client = new FakePlannerGoogleApiClient(
			[
				'place-a' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-a', 'Alpha' ),
					]
				),
				'place-b' => GoogleApiResult::failure( 'place_details_unavailable', 'failed', 503 ),
			]
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'selected_waypoint_ids' => [ 'place-a', 'place-b' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => true,
			]
		);

		self::assertSame( [ 'place-a', 'place-b' ], $state['selected_waypoint_ids'] );
		self::assertCount( 2, $state['trip_waypoints'] );
		self::assertSame( 'place-b', $state['trip_waypoints'][1]['id'] );
		self::assertTrue( $state['trip_waypoints'][1]['unresolved'] ?? false );
		self::assertSame( '', $state['maps_url'] );
		self::assertSame(
			'The trip preview and Google Maps handoff will stay unavailable until every selected place loads successfully.',
			$state['messages'][1]['text'] ?? ''
		);
	}

	public function test_build_skips_remote_work_when_results_and_trip_waypoints_are_disabled(): void {
		$google_api_client = new FakePlannerGoogleApiClient(
			[
				'place-a' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-a', 'Alpha' ),
					]
				),
			]
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'category_search'       => 'coffee',
				'selected_waypoint_ids' => [ 'place-a' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => false,
			]
		);

		self::assertSame( [], $google_api_client->requested_place_ids );
		self::assertSame( 0, $google_api_client->text_search_calls );
		self::assertSame( 0, $google_api_client->geocode_calls );
		self::assertSame( [ 'place-a' ], $state['selected_waypoint_ids'] );
	}

	public function test_build_does_not_add_status_message_for_current_location_start_mode(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ],
				[
					'interface_copy' => array_merge(
						InterfaceCopy::defaults(),
						[
							'start_current_handoff_label'   => 'Editable current handoff label',
							'start_current_handoff_summary' => 'editable current handoff summary',
						]
					),
				]
			)
		);

		$google_api_client = new FakePlannerGoogleApiClient( [] );
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'category_search' => 'coffee',
				'start_mode'      => Settings::START_MODE_CURRENT,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => false,
			]
		);

		self::assertSame( Settings::START_MODE_CURRENT, $state['start_mode'] );
		self::assertSame( 'Current location', $state['handoff_start_label'] );
		self::assertStringContainsString( 'near your current location', $state['overview'] );
		self::assertStringNotContainsString( 'Editable current handoff label', $state['overview'] );
		self::assertStringNotContainsString( 'editable current handoff summary', $state['overview'] );
		self::assertSame( [], $state['messages'] );
	}

	public function test_build_uses_fixed_default_location_fallback_copy_when_label_is_missing(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ],
				[
					'default_location_label' => '',
					'interface_copy'         => array_merge(
						InterfaceCopy::defaults(),
						[
							'start_default_location_fallback' => 'Editable fallback location',
						]
					),
				]
			)
		);

		$google_api_client = new FakePlannerGoogleApiClient( [] );
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'start_mode' => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => false,
			]
		);

		self::assertSame( 'Default location', $state['preview_start_label'] );
		self::assertSame( 'Default location', $state['handoff_start_label'] );
	}

	public function test_build_uses_fixed_custom_start_fallback_copy_when_custom_start_is_missing(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ],
				[
					'interface_copy' => array_merge(
						InterfaceCopy::defaults(),
						[
							'start_default_fallback_label' => 'Editable fallback label',
							'start_custom_missing_message' => 'Editable custom missing message.',
						]
					),
				]
			)
		);

		$google_api_client = new FakePlannerGoogleApiClient( [] );
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'start_mode' => Settings::START_MODE_CUSTOM,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => false,
			]
		);

		self::assertSame( Settings::START_MODE_CUSTOM, $state['start_mode'] );
		self::assertSame( 'Default location fallback', $state['handoff_start_label'] );
		self::assertSame(
			'Add a custom address to replace the default fallback before finalizing the trip start.',
			$state['messages'][0]['text'] ?? ''
		);
	}

	public function test_build_uses_custom_interface_copy_for_trip_labels_and_warnings(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'default_location_label'   => 'Downtown',
					'default_location_address' => '123 Main St',
					'map_preview_enabled'      => false,
					'maps_handoff_enabled'     => false,
					'google_api_timeout'       => 15,
					'interface_copy'           => array_merge(
						InterfaceCopy::defaults(),
						[
							'trip_timeout_warning' => 'Custom timeout warning.',
							'trip_count_plural'    => '{count} saved stops',
						]
					),
				]
			)
		);

		$google_api_client = new FakePlannerGoogleApiClient(
			[
				'place-a' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-a', 'Alpha' ),
					]
				),
				'place-b' => GoogleApiResult::success(
					[
						'place' => $this->place( 'place-b', 'Beta' ),
					]
				),
			]
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$warning_state = $builder->build(
			[
				'selected_waypoint_ids' => [ 'place-a', 'place-b' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => true,
				'trip_waypoint_deadline_at' => microtime( true ) - 1,
			]
		);
		$count_state   = $builder->build(
			[
				'selected_waypoint_ids' => [ 'place-a', 'place-b' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_results'        => false,
				'include_trip_waypoints' => true,
			]
		);

		self::assertSame( 'Custom timeout warning.', $warning_state['messages'][0]['text'] ?? '' );
		self::assertSame( '2 saved stops', $count_state['trip_count_label'] );
	}

	public function test_build_uses_fixed_search_result_summary_copy(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ],
				[
					'map_preview_enabled' => true,
					'interface_copy'      => array_merge(
						InterfaceCopy::defaults(),
						[
							'maps_link_label_search'      => 'Editable maps link',
							'preview_mode_label_search'   => 'Editable preview mode',
							'overview_browse_search'      => 'Editable overview {search} {start}',
							'search_results_count_single' => 'Editable single count {count}',
							'search_preview_key_warning'  => 'Editable preview key warning.',
						]
					),
				]
			)
		);

		$google_api_client = new FakePlannerGoogleApiClient(
			[],
			GoogleApiResult::success(
				[
					'places' => [
						$this->place( 'place-a', 'Alpha' ),
					],
				]
			)
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'category_search' => 'coffee',
				'start_mode'      => Settings::START_MODE_DEFAULT,
			]
		);

		self::assertSame( '1 Google result', $state['search_results_label'] );
		self::assertSame( 'Explore in Google Maps', $state['maps_link_label'] );
		self::assertSame( 'Google place search', $state['preview_mode_label'] );
		self::assertSame(
			'Browsing Google results for coffee near Downtown. Add any result to start building a walking trip.',
			$state['overview']
		);
		self::assertSame(
			'Add a valid Google Maps Embed API key before relying on the on-site search preview.',
			$state['messages'][0]['text'] ?? ''
		);
	}

	public function test_build_uses_saved_category_label_and_query_for_category_searches(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'default_location_label'   => 'Downtown',
					'default_location_address' => '123 Main St',
					'map_preview_enabled'      => false,
					'maps_handoff_enabled'     => false,
					'categories'               => Settings::sanitize_categories(
						[
							[
								'label'       => 'Tea',
								'description' => 'Tea houses and lounges',
								'text_query'  => 'tea houses',
								'slug'        => 'tea',
								'enabled'     => true,
								'sort_order'  => 10,
							],
						]
					),
				]
			)
		);

		$google_api_client = new FakePlannerGoogleApiClient( [] );
		$builder           = $this->planner_state_builder( $google_api_client );
		$state             = $builder->build(
			[
				'category_key' => 'tea',
				'start_mode'   => Settings::START_MODE_DEFAULT,
			]
		);

		self::assertSame( 'Tea', $state['active_search_label'] );
		self::assertSame( 'tea houses near 123 Main St', $state['search_query'] );
		self::assertSame( 'tea', $state['category_key'] );
		self::assertSame( 'Tea', $state['category_catalog']['tea']['label'] ?? null );
		self::assertSame( 'tea houses near 123 Main St', $google_api_client->last_text_search_query );
	}

	public function test_build_append_mode_dedupes_loaded_results_and_preserves_trip_state(): void {
		$google_api_client = new FakePlannerGoogleApiClient(
			[
				'trip-1' => GoogleApiResult::success(
					[
						'place' => $this->place( 'trip-1', 'Trip Stop' ),
					]
				),
			],
			GoogleApiResult::success(
				[
					'places'        => [
						[
							'id'      => 'loaded-1',
							'label'   => 'Already Loaded',
							'address' => 'Loaded address',
						],
						[
							'id'      => 'loaded-2',
							'label'   => 'New Result',
							'address' => 'New address',
						],
					],
					'nextPageToken' => 'page-3',
				]
			)
		);
		$builder           = $this->planner_state_builder( $google_api_client );

		$state = $builder->build(
			[
				'category_search'       => 'coffee',
				'page_token'            => 'page-2',
				'append_results'        => true,
				'loaded_result_ids'     => [ 'loaded-1' ],
				'selected_waypoint_ids' => [ 'trip-1' ],
				'start_mode'            => Settings::START_MODE_DEFAULT,
			],
			[
				'include_trip_waypoints' => true,
			]
		);

		self::assertSame( 'page-2', $google_api_client->last_text_search_page_token );
		self::assertSame( [ 'loaded-2' ], array_column( $state['search_results'], 'id' ) );
		self::assertSame( '2 Google results', $state['search_results_label'] );
		self::assertSame( 'page-3', $state['next_page_token'] );
		self::assertTrue( $state['has_more_results'] );
		self::assertSame( [ 'trip-1' ], $state['selected_waypoint_ids'] );
		self::assertCount( 1, $state['trip_waypoints'] );
		self::assertSame( 'trip-1', $state['trip_waypoints'][0]['id'] ?? '' );
	}

	public function test_build_search_context_key_changes_when_search_context_changes(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ],
				[
					'categories' => Settings::sanitize_categories(
						[
							[
								'label'       => 'Coffee',
								'description' => 'Coffee stops',
								'text_query'  => 'coffee shops',
								'slug'        => 'coffee',
								'enabled'     => true,
								'sort_order'  => 10,
							],
							[
								'label'       => 'Tea',
								'description' => 'Tea stops',
								'text_query'  => 'tea houses',
								'slug'        => 'tea',
								'enabled'     => true,
								'sort_order'  => 20,
							],
						]
					),
				]
			)
		);

		$builder = $this->planner_state_builder( new FakePlannerGoogleApiClient( [] ) );

		$category_base = $builder->build(
			[
				'category_key' => 'coffee',
				'start_mode'   => Settings::START_MODE_DEFAULT,
			]
		);
		$category_changed = $builder->build(
			[
				'category_key' => 'tea',
				'start_mode'   => Settings::START_MODE_DEFAULT,
			]
		);
		$custom_search_base = $builder->build(
			[
				'category_search' => 'coffee',
				'start_mode'      => Settings::START_MODE_DEFAULT,
			]
		);
		$custom_search_changed = $builder->build(
			[
				'category_search' => 'tea',
				'start_mode'      => Settings::START_MODE_DEFAULT,
			]
		);
		$start_mode_changed = $builder->build(
			[
				'category_search' => 'coffee',
				'start_mode'      => Settings::START_MODE_CURRENT,
			]
		);
		$custom_start_base = $builder->build(
			[
				'category_search' => 'coffee',
				'start_mode'      => Settings::START_MODE_CUSTOM,
				'custom_start'    => 'Hotel One',
			]
		);
		$custom_start_changed = $builder->build(
			[
				'category_search' => 'coffee',
				'start_mode'      => Settings::START_MODE_CUSTOM,
				'custom_start'    => 'Hotel Two',
			]
		);

		self::assertNotSame( $category_base['search_context_key'], $category_changed['search_context_key'] );
		self::assertNotSame( $custom_search_base['search_context_key'], $custom_search_changed['search_context_key'] );
		self::assertNotSame( $custom_search_base['search_context_key'], $start_mode_changed['search_context_key'] );
		self::assertNotSame( $custom_start_base['search_context_key'], $custom_start_changed['search_context_key'] );
	}

	private function planner_state_builder( GoogleApiClientInterface $google_api_client ): PlannerStateBuilder {
		$settings = new Settings();

		return new PlannerStateBuilder(
			$settings,
			new CategoryCatalog( $settings ),
			$google_api_client,
			new WaypointList( $settings ),
			new StartContextResolver( $settings ),
			new MapUrlBuilder(),
			new DistanceFormatter(),
			new RequestOriginValidator()
		);
	}

	private function place( string $id, string $label ): array {
		return [
			'id'      => $id,
			'label'   => $label,
			'address' => $label . ' address',
		];
	}
}

final class FakePlannerGoogleApiClient implements GoogleApiClientInterface {
	/** @var array<string, GoogleApiResult> */
	private array $place_details_results;

	/** @var list<string> */
	public array $requested_place_ids = [];

	/** @var list<int|null> */
	public array $timeouts = [];

	public int $text_search_calls = 0;

	public int $geocode_calls = 0;

	public string $last_text_search_query = '';

	public string $last_text_search_page_token = '';

	private GoogleApiResult $text_search_result;

	/**
	 * @param array<string, GoogleApiResult> $place_details_results
	 */
	public function __construct( array $place_details_results, ?GoogleApiResult $text_search_result = null ) {
		$this->place_details_results = $place_details_results;
		$this->text_search_result    = $text_search_result ?? GoogleApiResult::success(
			[
				'places' => [],
			]
		);
	}

	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null, string $page_token = '' ): GoogleApiResult {
		++$this->text_search_calls;
		$this->last_text_search_query = $query;
		$this->last_text_search_page_token = $page_token;

		return $this->text_search_result;
	}

	public function place_details( string $place_id, ?int $timeout = null ): GoogleApiResult {
		$this->requested_place_ids[] = $place_id;
		$this->timeouts[]            = $timeout;

		return $this->place_details_results[ $place_id ] ?? GoogleApiResult::failure( 'missing_place', 'missing', 404, false );
	}

	public function geocode( string $address ): GoogleApiResult {
		++$this->geocode_calls;

		return GoogleApiResult::success(
			[
				'latitude'  => 33.4484,
				'longitude' => -112.0740,
			]
		);
	}
}
