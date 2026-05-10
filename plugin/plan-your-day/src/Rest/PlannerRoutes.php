<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Rest;

use Acodebeard\PlanYourDay\Planner\PlaceParser;
use Acodebeard\PlanYourDay\Planner\PlannerPayloadBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
use Acodebeard\PlanYourDay\Planner\RequestStateParser;
use Acodebeard\PlanYourDay\Security\RateLimiter;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Security\VisitorTokenManager;
use Acodebeard\PlanYourDay\Settings\Settings;
use Acodebeard\PlanYourDay\Support\DebugLogger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class PlannerRoutes {
	public const REST_NAMESPACE = 'plan-your-day/v1';
	private const RATE_LIMIT_BASE_COST = 1;
	private const BROWSE_SEARCH_COST = 2;
	private const PUBLIC_TRIP_WAYPOINT_DEADLINE_SECONDS = 12;
	private const PUBLIC_TRIP_WAYPOINT_PLACE_DETAILS_TIMEOUT = 5;
	private const PUBLIC_TRIP_WAYPOINT_MAX_FAILURES = 3;
	private const LOADED_RESULTS_CACHE_PREFIX = 'pyd_loaded_results_';
	private const LOADED_RESULTS_CACHE_TTL = 1800;

	private RequestStateParser $request_state_parser;
	private PlannerStateBuilder $planner_state_builder;
	private PlannerPayloadBuilder $planner_payload_builder;
	private RequestOriginValidator $request_origin_validator;
	private VisitorTokenManager $visitor_token_manager;
	private RateLimiter $rate_limiter;
	private ?Settings $settings;

	public function __construct(
		RequestStateParser $request_state_parser,
		PlannerStateBuilder $planner_state_builder,
		PlannerPayloadBuilder $planner_payload_builder,
		RequestOriginValidator $request_origin_validator,
		VisitorTokenManager $visitor_token_manager,
		RateLimiter $rate_limiter,
		?Settings $settings = null
	) {
		$this->request_state_parser     = $request_state_parser;
		$this->planner_state_builder    = $planner_state_builder;
		$this->planner_payload_builder  = $planner_payload_builder;
		$this->request_origin_validator = $request_origin_validator;
		$this->visitor_token_manager    = $visitor_token_manager;
		$this->rate_limiter             = $rate_limiter;
		$this->settings                 = $settings;
	}

	public function register(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/browse',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'browse' ],
				'permission_callback' => '__return_true',
				'args'                => $this->route_args(),
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/route',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'route' ],
				'permission_callback' => '__return_true',
				'args'                => $this->route_args(),
			]
		);
	}

	public function browse( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		DebugLogger::log(
			'rest.browse.request',
			[
				'params' => $this->debug_request_params( $request ),
			]
		);
		$request_state = $this->request_state_parser->parse( $this->request_params_from_request( $request ) );
		$guard         = $this->guard_request( $request, 'browse', $request_state );

		if ( $guard instanceof WP_Error ) {
			return $guard;
		}

		$append_results_request = ! empty( $request_state['append_results'] );
		$refresh_route          = $this->should_refresh_browse_route( $request, $request_state );
		$build_options          = $this->public_trip_waypoint_options( true );

		if ( $append_results_request ) {
			$cached_loaded_result_ids = $this->get_cached_loaded_result_ids( $request );

			if ( [] !== $cached_loaded_result_ids ) {
				$request_state['loaded_result_ids'] = $cached_loaded_result_ids;
			}
		}

		if ( $append_results_request || ! $refresh_route ) {
			$build_options['include_trip_waypoints'] = false;
		}

		$planner_state = $this->planner_state_builder->build(
			$request_state,
			$build_options
		);
		$this->remember_loaded_result_ids(
			$request,
			$planner_state,
			(array) ( $request_state['loaded_result_ids'] ?? [] )
		);
		DebugLogger::log(
			'rest.browse.response',
			[
				'category_key'          => $planner_state['category_key'],
				'category_search'       => $planner_state['category_search'],
				'search_results_count'  => count( (array) $planner_state['search_results'] ),
				'search_results_error'  => $planner_state['search_results_error'],
				'next_page_token'       => $planner_state['next_page_token'],
				'has_more_results'      => $planner_state['has_more_results'],
				'selected_waypoint_ids' => $planner_state['selected_waypoint_ids'],
				'messages'              => $planner_state['messages'],
			]
		);

		$response_payload = [
			'browse' => $this->planner_payload_builder->build_browse_payload( $planner_state ),
		];

		if ( $refresh_route && ! $append_results_request ) {
			$response_payload['route'] = $this->planner_payload_builder->build_route_payload( $planner_state );
		}

		return new WP_REST_Response( $response_payload );
	}

	public function route( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		DebugLogger::log(
			'rest.route.request',
			[
				'params' => $this->debug_request_params( $request ),
			]
		);
		$request_state = $this->request_state_parser->parse( $this->request_params_from_request( $request ) );
		$guard         = $this->guard_request( $request, 'route', $request_state );

		if ( $guard instanceof WP_Error ) {
			return $guard;
		}

		$planner_state = $this->planner_state_builder->build(
			$request_state,
			$this->public_trip_waypoint_options( false )
		);
		DebugLogger::log(
			'rest.route.response',
			[
				'category_key'          => $planner_state['category_key'],
				'selected_waypoint_ids' => $planner_state['selected_waypoint_ids'],
				'trip_waypoints_count'  => count( (array) $planner_state['trip_waypoints'] ),
				'iframe_src_present'    => '' !== (string) $planner_state['iframe_src'],
				'maps_url_present'      => '' !== (string) $planner_state['maps_url'],
				'messages'              => $planner_state['messages'],
			]
		);

		return new WP_REST_Response(
			[
				'route' => $this->planner_payload_builder->build_route_payload( $planner_state ),
			]
		);
	}

	private function guard_request( WP_REST_Request $request, string $scope, array $request_state ): ?WP_Error {
		if ( ! $this->request_origin_validator->is_same_site_request( $_SERVER ) ) {
			$error = new WP_Error(
				'plan_your_day_invalid_origin',
				$this->request_verification_failed_message(),
				[
					'status' => 403,
				]
			);
			DebugLogger::log(
				'rest.guard.invalid_origin',
				[
					'scope'          => $scope,
					'host'           => $_SERVER['HTTP_HOST'] ?? '',
					'origin'         => $_SERVER['HTTP_ORIGIN'] ?? '',
					'referer'        => $_SERVER['HTTP_REFERER'] ?? '',
					'sec_fetch_site' => $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '',
					'sec_fetch_mode' => $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '',
					'sec_fetch_dest' => $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '',
				]
			);

			return $error;
		}

		$endpoint_token = trim( sanitize_text_field( (string) $request->get_param( 'endpoint_token' ) ) );

		if ( ! $this->visitor_token_manager->validate_endpoint_token( $endpoint_token ) ) {
			$error = new WP_Error(
				'plan_your_day_invalid_token',
				$this->request_verification_failed_message(),
				[
					'status' => 403,
				]
			);
			DebugLogger::log(
				'rest.guard.invalid_token',
				[
					'scope'          => $scope,
					'token_present'  => '' !== $endpoint_token,
					'cookie_present' => isset( $_COOKIE['plan_your_day_visitor'] ),
					'origin'         => $_SERVER['HTTP_ORIGIN'] ?? '',
					'referer'        => $_SERVER['HTTP_REFERER'] ?? '',
				]
			);

			return $error;
		}

		$rate_limit = $this->rate_limiter->enforce( $scope, $_SERVER, $this->get_rate_limit_cost( $scope, $request_state, $request ) );

		if ( $rate_limit instanceof WP_Error ) {
			DebugLogger::log(
				'rest.guard.rate_limited',
				[
					'scope' => $scope,
					'error' => $rate_limit,
				]
			);
		}

		return $rate_limit;
	}

	private function public_trip_waypoint_options( bool $include_results ): array {
		return [
			'include_results'                    => $include_results,
			'include_trip_waypoints'             => true,
			'trip_waypoint_deadline_seconds'     => self::PUBLIC_TRIP_WAYPOINT_DEADLINE_SECONDS,
			'trip_waypoint_place_details_timeout' => self::PUBLIC_TRIP_WAYPOINT_PLACE_DETAILS_TIMEOUT,
			'trip_waypoint_max_failures'         => self::PUBLIC_TRIP_WAYPOINT_MAX_FAILURES,
		];
	}

	private function request_verification_failed_message(): string {
		return $this->settings instanceof Settings
			? $this->settings->get_frontend_copy_value( 'request_verification_failed' )
			: __( 'The planner request could not be verified. Refresh the page and try again.', 'plan-your-day' );
	}

	private function get_rate_limit_cost( string $scope, array $request_state, ?WP_REST_Request $request = null ): int {
		$cost               = self::RATE_LIMIT_BASE_COST;
		$selected_waypoints = array_values( (array) ( $request_state['selected_waypoint_ids'] ?? [] ) );
		$append_results     = ! empty( $request_state['append_results'] );

		if ( 'browse' === $scope && $this->request_uses_google_search( $request_state ) ) {
			$cost += self::BROWSE_SEARCH_COST;
		}

		if ( 'browse' === $scope && $append_results ) {
			return $cost;
		}

		if ( 'browse' === $scope && [] !== $selected_waypoints && ! $this->should_refresh_browse_route( $request, $request_state ) ) {
			return $cost;
		}

		return $cost + count( $selected_waypoints );
	}

	private function should_refresh_browse_route( ?WP_REST_Request $request, array $request_state ): bool {
		if ( [] === array_values( (array) ( $request_state['selected_waypoint_ids'] ?? [] ) ) ) {
			return true;
		}

		if ( ! $request instanceof WP_REST_Request ) {
			return true;
		}

		$refresh_route = $request->get_param( 'refresh_route' );

		if ( null === $refresh_route ) {
			return true;
		}

		return $this->sanitize_boolean( $refresh_route );
	}

	/**
	 * @return array<int, string>
	 */
	private function get_cached_loaded_result_ids( WP_REST_Request $request ): array {
		$cache_key = $this->loaded_results_cache_key( $request, $this->request_search_context_key( $request ) );

		if ( '' === $cache_key ) {
			return [];
		}

		$cached_loaded_result_ids = get_transient( $cache_key );

		if ( ! is_array( $cached_loaded_result_ids ) ) {
			return [];
		}

		return $this->normalize_place_ids( $cached_loaded_result_ids );
	}

	private function remember_loaded_result_ids( WP_REST_Request $request, array $planner_state, array $loaded_result_ids ): void {
		$search_context_key = trim( sanitize_text_field( (string) ( $planner_state['search_context_key'] ?? '' ) ) );
		$cache_key          = $this->loaded_results_cache_key( $request, $search_context_key );

		if ( '' === $cache_key ) {
			return;
		}

		set_transient(
			$cache_key,
			$this->normalize_place_ids(
				array_merge(
					$loaded_result_ids,
					$this->extract_search_result_ids( $planner_state )
				)
			),
			self::LOADED_RESULTS_CACHE_TTL
		);
	}

	private function request_search_context_key( WP_REST_Request $request ): string {
		return trim( sanitize_text_field( (string) $request->get_param( 'search_context_key' ) ) );
	}

	private function loaded_results_cache_key( WP_REST_Request $request, string $search_context_key ): string {
		$endpoint_token = trim( sanitize_text_field( (string) $request->get_param( 'endpoint_token' ) ) );

		if ( '' === $endpoint_token || '' === $search_context_key ) {
			return '';
		}

		return self::LOADED_RESULTS_CACHE_PREFIX . substr( hash( 'sha256', $endpoint_token . '|' . $search_context_key ), 0, 40 );
	}

	/**
	 * @return array<int, string>
	 */
	private function extract_search_result_ids( array $planner_state ): array {
		$place_ids = [];

		foreach ( (array) ( $planner_state['search_results'] ?? [] ) as $search_result ) {
			if ( ! is_array( $search_result ) ) {
				continue;
			}

			$place_id = PlaceParser::sanitize_place_id( (string) ( $search_result['id'] ?? '' ) );

			if ( '' !== $place_id ) {
				$place_ids[] = $place_id;
			}
		}

		return $this->normalize_place_ids( $place_ids );
	}

	/**
	 * @param array<int, mixed> $place_ids
	 * @return array<int, string>
	 */
	private function normalize_place_ids( array $place_ids ): array {
		$normalized_place_ids = [];

		foreach ( $place_ids as $place_id ) {
			$place_id = PlaceParser::sanitize_place_id( (string) $place_id );

			if ( '' === $place_id || in_array( $place_id, $normalized_place_ids, true ) ) {
				continue;
			}

			$normalized_place_ids[] = $place_id;
		}

		return $normalized_place_ids;
	}

	private function request_uses_google_search( array $request_state ): bool {
		return '' !== sanitize_key( (string) ( $request_state['category_key'] ?? '' ) )
			|| '' !== trim( sanitize_text_field( (string) ( $request_state['category_search'] ?? '' ) ) );
	}

	private function route_args(): array {
		return [
			'category'        => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'category_search' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'page_token'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'append_results'  => [
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'validate_callback' => [ $this, 'validate_loose_boolean' ],
				'default'           => false,
			],
			'refresh_route'   => [
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'validate_callback' => [ $this, 'validate_loose_boolean' ],
				'default'           => true,
			],
			'search_context_key' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'loaded_result_ids' => [
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_waypoints' ],
				'validate_callback' => [ $this, 'validate_loose_waypoints' ],
				'default'           => [],
			],
			'waypoints'       => [
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_waypoints' ],
				'validate_callback' => [ $this, 'validate_loose_waypoints' ],
				'default'           => [],
			],
			'start_mode'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'custom_start'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'clear_trip'      => [
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'validate_callback' => [ $this, 'validate_loose_boolean' ],
				'default'           => false,
			],
			'remove_waypoint' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'move_waypoint'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
			'endpoint_token'  => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_loose_scalar' ],
				'default'           => '',
			],
		];
	}

	public function sanitize_waypoints( mixed $waypoints ): array {
		if ( ! is_array( $waypoints ) ) {
			return [];
		}

		return array_values(
			array_map(
				static function ( mixed $waypoint ): string {
					return sanitize_text_field( is_scalar( $waypoint ) ? (string) $waypoint : '' );
				},
				$waypoints
			)
		);
	}

	public function sanitize_boolean( mixed $value ): bool {
		return true === $value || 1 === $value || '1' === (string) $value;
	}

	public function validate_loose_scalar( mixed $value, ?WP_REST_Request $request = null, string $param = '' ): bool {
		$is_valid = null === $value || is_scalar( $value );

		if ( ! $is_valid ) {
			DebugLogger::log(
				'rest.validation.invalid_scalar',
				[
					'param'   => $param,
					'type'    => gettype( $value ),
					'request' => $this->debug_request_params( $request ),
				]
			);
		}

		return $is_valid;
	}

	public function validate_loose_boolean( mixed $value, ?WP_REST_Request $request = null, string $param = '' ): bool {
		$is_valid = null === $value || is_bool( $value ) || is_scalar( $value );

		if ( ! $is_valid ) {
			DebugLogger::log(
				'rest.validation.invalid_boolean',
				[
					'param'   => $param,
					'type'    => gettype( $value ),
					'request' => $this->debug_request_params( $request ),
				]
			);
		}

		return $is_valid;
	}

	public function validate_loose_waypoints( mixed $value, ?WP_REST_Request $request = null, string $param = '' ): bool {
		if ( null === $value ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			$is_valid = is_scalar( $value );

			if ( ! $is_valid ) {
				DebugLogger::log(
					'rest.validation.invalid_waypoints',
					[
						'param'   => $param,
						'type'    => gettype( $value ),
						'request' => $this->debug_request_params( $request ),
					]
				);
			}

			return $is_valid;
		}

		foreach ( $value as $waypoint ) {
			if ( ! is_scalar( $waypoint ) && null !== $waypoint ) {
				DebugLogger::log(
					'rest.validation.invalid_waypoint_item',
					[
						'param'     => $param,
						'item_type' => gettype( $waypoint ),
						'request'   => $this->debug_request_params( $request ),
					]
				);
				return false;
			}
		}

		return true;
	}

	private function request_params_from_request( WP_REST_Request $request ): array {
		$params = [
			'category'          => (string) $request->get_param( 'category' ),
			'category_search'   => (string) $request->get_param( 'category_search' ),
			'page_token'        => (string) $request->get_param( 'page_token' ),
			'loaded_result_ids' => (array) $request->get_param( 'loaded_result_ids' ),
			'waypoints'         => (array) $request->get_param( 'waypoints' ),
			'start_mode'        => (string) $request->get_param( 'start_mode' ),
			'custom_start'      => (string) $request->get_param( 'custom_start' ),
		];

		if ( true === $request->get_param( 'append_results' ) ) {
			$params['append_results'] = '1';
		}

		if ( true === $request->get_param( 'refresh_route' ) ) {
			$params['refresh_route'] = '1';
		}

		if ( true === $request->get_param( 'clear_trip' ) ) {
			$params['clear_trip'] = '1';
		}

		$remove_waypoint = (string) $request->get_param( 'remove_waypoint' );
		if ( '' !== $remove_waypoint ) {
			$params['remove_waypoint'] = $remove_waypoint;
		}

		$move_waypoint = (string) $request->get_param( 'move_waypoint' );
		if ( '' !== $move_waypoint ) {
			$params['move_waypoint'] = $move_waypoint;
		}

		return $params;
	}

	private function debug_request_params( ?WP_REST_Request $request ): array {
		if ( ! $request instanceof WP_REST_Request ) {
			return [];
		}

		return [
			'method'       => $request->get_method(),
			'route'        => $request->get_route(),
			'params'       => [
				'category'          => $request->get_param( 'category' ),
				'category_search'   => $request->get_param( 'category_search' ),
				'page_token'        => $request->get_param( 'page_token' ),
				'append_results'    => $request->get_param( 'append_results' ),
				'refresh_route'     => $request->get_param( 'refresh_route' ),
				'search_context_key' => $request->get_param( 'search_context_key' ),
				'loaded_result_ids' => $request->get_param( 'loaded_result_ids' ),
				'waypoints'         => $request->get_param( 'waypoints' ),
				'start_mode'        => $request->get_param( 'start_mode' ),
				'custom_start'      => $request->get_param( 'custom_start' ),
				'clear_trip'        => $request->get_param( 'clear_trip' ),
				'remove_waypoint'   => $request->get_param( 'remove_waypoint' ),
				'move_waypoint'     => $request->get_param( 'move_waypoint' ),
			],
			'has_token'    => '' !== (string) $request->get_param( 'endpoint_token' ),
			'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
		];
	}
}
