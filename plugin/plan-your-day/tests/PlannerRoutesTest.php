<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Google\GoogleApiResult;
use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Planner\DistanceFormatter;
use Acodebeard\PlanYourDay\Planner\MapUrlBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerPayloadBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
use Acodebeard\PlanYourDay\Planner\RequestStateParser;
use Acodebeard\PlanYourDay\Planner\StartContextResolver;
use Acodebeard\PlanYourDay\Planner\WaypointList;
use Acodebeard\PlanYourDay\Rest\PlannerRoutes;
use Acodebeard\PlanYourDay\Security\ClientIpResolver;
use Acodebeard\PlanYourDay\Security\RateLimiter;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Security\VisitorTokenManager;
use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class PlannerRoutesTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['plan_your_day_test_options'] = [];
		$_COOKIE                                = [];
		$_SERVER                                = [];
	}

	public function test_sanitize_waypoints_keeps_only_scalar_values_as_strings(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertSame(
			[ 'place-1', '42', '', '1' ],
			$routes->sanitize_waypoints( [ 'place-1', 42, [ 'bad' ], true ] )
		);
	}

	public function test_sanitize_boolean_handles_common_truthy_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->sanitize_boolean( '1' ) );
		self::assertTrue( $routes->sanitize_boolean( 1 ) );
		self::assertFalse( $routes->sanitize_boolean( '0' ) );
		self::assertFalse( $routes->sanitize_boolean( false ) );
	}

	public function test_validate_loose_scalar_accepts_scalar_and_null_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->validate_loose_scalar( 'coffee' ) );
		self::assertTrue( $routes->validate_loose_scalar( 1 ) );
		self::assertTrue( $routes->validate_loose_scalar( null ) );
		self::assertFalse( $routes->validate_loose_scalar( [ 'coffee' ] ) );
	}

	public function test_validate_loose_boolean_accepts_common_boolean_wire_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->validate_loose_boolean( true ) );
		self::assertTrue( $routes->validate_loose_boolean( '0' ) );
		self::assertTrue( $routes->validate_loose_boolean( null ) );
		self::assertFalse( $routes->validate_loose_boolean( [ true ] ) );
	}

	public function test_validate_loose_waypoints_accepts_scalar_arrays_and_rejects_nested_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->validate_loose_waypoints( [ 'place-1', 'place-2' ] ) );
		self::assertTrue( $routes->validate_loose_waypoints( 'place-1' ) );
		self::assertTrue( $routes->validate_loose_waypoints( null ) );
		self::assertFalse( $routes->validate_loose_waypoints( [ 'place-1', [ 'bad' ] ] ) );
	}

	public function test_get_rate_limit_cost_weights_browse_search_and_trip_waypoints(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( PlannerRoutes::class, 'get_rate_limit_cost' );
		$method->setAccessible( true );

		self::assertSame(
			5,
			$method->invoke(
				$routes,
				'browse',
				[
					'category_key'          => 'coffee',
					'category_search'       => '',
					'selected_waypoint_ids' => [ 'place-1', 'place-2' ],
				]
			)
		);
		self::assertSame(
			3,
			$method->invoke(
				$routes,
				'route',
				[
					'category_key'          => 'coffee',
					'category_search'       => '',
					'selected_waypoint_ids' => [ 'place-1', 'place-2' ],
				]
			)
		);
		self::assertSame(
			1,
			$method->invoke(
				$routes,
				'browse',
				[
					'category_key'          => '',
					'category_search'       => '',
					'selected_waypoint_ids' => [],
				]
			)
		);
	}

	public function test_route_rate_limiter_returns_429_after_the_configured_limit(): void {
		$routes  = $this->build_routes( 1 );
		$request = $this->build_verified_request( str_repeat( 'ab', 24 ) );
		$_SERVER = $this->same_site_server( '198.51.100.10' );

		$first  = $routes->route( $request );
		$second = $routes->route( $request );

		self::assertInstanceOf( WP_REST_Response::class, $first );
		self::assertInstanceOf( WP_Error::class, $second );
		self::assertSame( 'plan_your_day_rate_limited', $second->get_error_code() );
		self::assertSame( 429, $second->get_error_data()['status'] ?? null );
	}

	public function test_route_rate_limiter_uses_client_ip_even_when_visitor_cookie_changes(): void {
		$routes = $this->build_routes( 2 );
		$_SERVER = $this->same_site_server( '198.51.100.10' );

		$first  = $routes->route( $this->build_verified_request( str_repeat( 'ab', 24 ) ) );
		$second = $routes->route( $this->build_verified_request( str_repeat( 'bc', 24 ) ) );
		$third  = $routes->route( $this->build_verified_request( str_repeat( 'cd', 24 ) ) );

		self::assertInstanceOf( WP_REST_Response::class, $first );
		self::assertInstanceOf( WP_REST_Response::class, $second );
		self::assertInstanceOf( WP_Error::class, $third );
		self::assertSame( 'plan_your_day_rate_limited', $third->get_error_code() );
	}

	public function test_route_rate_limiter_uses_trusted_proxy_forwarded_client_ip(): void {
		$routes  = $this->build_routes( 1, [ 'trusted_proxy_cidrs' => "10.0.0.0/8" ] );
		$_SERVER = $this->same_site_server(
			'10.1.1.1',
			[
				'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 10.1.1.1',
			]
		);

		$first  = $routes->route( $this->build_verified_request( str_repeat( 'ab', 24 ) ) );
		$second = $routes->route( $this->build_verified_request( str_repeat( 'bc', 24 ) ) );

		self::assertInstanceOf( WP_REST_Response::class, $first );
		self::assertInstanceOf( WP_Error::class, $second );
		self::assertSame( 'plan_your_day_rate_limited', $second->get_error_code() );
	}

	public function test_browse_returns_pagination_metadata_and_preserves_selected_waypoints_in_append_mode(): void {
		$google_api_client = new PlannerRoutesGoogleApiClient(
			GoogleApiResult::success(
				[
					'places'        => [
						[
							'id'      => 'result-2',
							'label'   => 'Second Result',
							'address' => '456 Test Ave',
						],
					],
					'nextPageToken' => 'page-3',
				]
			),
			[
				'trip-1' => GoogleApiResult::success(
					[
						'place' => [
							'id'      => 'trip-1',
							'label'   => 'Trip Stop',
							'address' => '789 Trip Rd',
						],
					]
				),
			]
		);
		$routes            = $this->build_routes(
			5,
			[
				'default_location_label'   => 'Downtown',
				'default_location_address' => '123 Main St',
			],
			$google_api_client
		);
		$request           = new WP_REST_Request( 'POST', '/plan-your-day/v1/browse' );

		$_SERVER = $this->same_site_server( '198.51.100.10' );
		$_COOKIE['plan_your_day_visitor'] = str_repeat( 'ab', 24 );
		$request->set_param( 'endpoint_token', hash_hmac( 'sha256', str_repeat( 'ab', 24 ), 'tests-auth|plan-your-day' ) );
		$request->set_param( 'category_search', 'coffee' );
		$request->set_param( 'page_token', 'page-2' );
		$request->set_param( 'append_results', true );
		$request->set_param( 'loaded_result_ids', [ 'result-1' ] );
		$request->set_param( 'waypoints', [ 'trip-1' ] );
		$request->set_param( 'start_mode', Settings::START_MODE_DEFAULT );

		$response = $routes->browse( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		self::assertSame( 'page-3', $data['browse']['nextPageToken'] ?? '' );
		self::assertTrue( $data['browse']['hasMoreResults'] ?? false );
		self::assertNotSame( '', $data['browse']['searchContextKey'] ?? '' );
		self::assertSame( [ 'trip-1' ], $data['route']['selectedWaypointIds'] ?? [] );
		self::assertSame( 'page-2', $google_api_client->last_page_token );
	}

	private function build_routes( int $rate_limit_per_minute, array $settings_overrides = [], ?GoogleApiClientInterface $google_api_client = null ): PlannerRoutes {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'rate_limit_per_minute' => $rate_limit_per_minute,
				],
				$settings_overrides
			)
		);

		$settings = new Settings();

		return new PlannerRoutes(
			new RequestStateParser( new WaypointList( $settings ) ),
			new PlannerStateBuilder(
				$settings,
				new CategoryCatalog( $settings ),
				$google_api_client ?? new PlannerRoutesGoogleApiClient(),
				new WaypointList( $settings ),
				new StartContextResolver( $settings ),
				new MapUrlBuilder(),
				new DistanceFormatter(),
				new RequestOriginValidator()
			),
			new PlannerPayloadBuilder(),
			new RequestOriginValidator(),
			new VisitorTokenManager(),
			new RateLimiter( $settings, new ClientIpResolver( $settings ) )
		);
	}

	private function build_verified_request( string $visitor_token ): WP_REST_Request {
		$_COOKIE['plan_your_day_visitor'] = $visitor_token;
		$request                          = new WP_REST_Request( 'POST', '/plan-your-day/v1/route' );
		$request->set_param( 'endpoint_token', hash_hmac( 'sha256', $visitor_token, 'tests-auth|plan-your-day' ) );

		return $request;
	}

	private function same_site_server( string $remote_addr, array $overrides = [] ): array {
		return array_merge(
			[
				'REMOTE_ADDR'         => $remote_addr,
				'HTTP_HOST'           => 'example.com',
				'HTTP_ORIGIN'         => 'https://example.com',
				'HTTP_REFERER'        => 'https://example.com/planner',
				'HTTP_SEC_FETCH_SITE' => 'same-origin',
			],
			$overrides
		);
	}
}

final class PlannerRoutesGoogleApiClient implements GoogleApiClientInterface {
	private GoogleApiResult $text_search_result;

	/** @var array<string, GoogleApiResult> */
	private array $place_details_results;

	public string $last_page_token = '';

	/**
	 * @param array<string, GoogleApiResult> $place_details_results
	 */
	public function __construct( ?GoogleApiResult $text_search_result = null, array $place_details_results = [] ) {
		$this->text_search_result   = $text_search_result ?? GoogleApiResult::success( [ 'places' => [] ] );
		$this->place_details_results = $place_details_results;
	}

	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null, string $page_token = '' ): GoogleApiResult {
		$this->last_page_token = $page_token;

		return $this->text_search_result;
	}

	public function place_details( string $place_id, ?int $timeout = null ): GoogleApiResult {
		return $this->place_details_results[ $place_id ] ?? GoogleApiResult::success( [ 'place' => [] ] );
	}

	public function geocode( string $address ): GoogleApiResult {
		return GoogleApiResult::success( [] );
	}
}
