<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

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
		self::assertCount( 0, $state['trip_waypoints'] );
		self::assertSame(
			'The trip preview stopped loading more places after repeated Google place errors. Try again later or remove any invalid stops.',
			$state['messages'][2]['text'] ?? ''
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

	/**
	 * @param array<string, GoogleApiResult> $place_details_results
	 */
	public function __construct( array $place_details_results ) {
		$this->place_details_results = $place_details_results;
	}

	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null ): GoogleApiResult {
		++$this->text_search_calls;

		return GoogleApiResult::success(
			[
				'places' => [],
			]
		);
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
