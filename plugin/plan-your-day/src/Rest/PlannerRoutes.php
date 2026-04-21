<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Rest;

use Acodebeard\PlanYourDay\Planner\PlannerPayloadBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
use Acodebeard\PlanYourDay\Planner\RequestStateParser;
use Acodebeard\PlanYourDay\Security\RateLimiter;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Security\VisitorTokenManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class PlannerRoutes {
	public const REST_NAMESPACE = 'plan-your-day/v1';

	private RequestStateParser $request_state_parser;
	private PlannerStateBuilder $planner_state_builder;
	private PlannerPayloadBuilder $planner_payload_builder;
	private RequestOriginValidator $request_origin_validator;
	private VisitorTokenManager $visitor_token_manager;
	private RateLimiter $rate_limiter;

	public function __construct(
		RequestStateParser $request_state_parser,
		PlannerStateBuilder $planner_state_builder,
		PlannerPayloadBuilder $planner_payload_builder,
		RequestOriginValidator $request_origin_validator,
		VisitorTokenManager $visitor_token_manager,
		RateLimiter $rate_limiter
	) {
		$this->request_state_parser     = $request_state_parser;
		$this->planner_state_builder    = $planner_state_builder;
		$this->planner_payload_builder  = $planner_payload_builder;
		$this->request_origin_validator = $request_origin_validator;
		$this->visitor_token_manager    = $visitor_token_manager;
		$this->rate_limiter             = $rate_limiter;
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
		$guard = $this->guard_request( $request, 'browse' );

		if ( $guard instanceof WP_Error ) {
			return $guard;
		}

		$planner_state = $this->planner_state_builder->build(
			$this->request_state_parser->parse( $this->request_params_from_request( $request ) ),
			[
				'include_results'        => true,
				'include_trip_waypoints' => true,
			]
		);

		return new WP_REST_Response(
			[
				'browse' => $this->planner_payload_builder->build_browse_payload( $planner_state ),
				'route'  => $this->planner_payload_builder->build_route_payload( $planner_state ),
			]
		);
	}

	public function route( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->guard_request( $request, 'route' );

		if ( $guard instanceof WP_Error ) {
			return $guard;
		}

		$planner_state = $this->planner_state_builder->build(
			$this->request_state_parser->parse( $this->request_params_from_request( $request ) ),
			[
				'include_results'        => false,
				'include_trip_waypoints' => true,
			]
		);

		return new WP_REST_Response(
			[
				'route' => $this->planner_payload_builder->build_route_payload( $planner_state ),
			]
		);
	}

	private function guard_request( WP_REST_Request $request, string $scope ): ?WP_Error {
		if ( ! $this->request_origin_validator->is_same_site_request( $_SERVER ) ) {
			return new WP_Error(
				'plan_your_day_invalid_origin',
				__( 'The planner request could not be verified. Refresh the page and try again.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				[
					'status' => 403,
				]
			);
		}

		$endpoint_token = trim( sanitize_text_field( (string) $request->get_param( 'endpoint_token' ) ) );

		if ( ! $this->visitor_token_manager->validate_endpoint_token( $endpoint_token ) ) {
			return new WP_Error(
				'plan_your_day_invalid_token',
				__( 'The planner request could not be verified. Refresh the page and try again.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				[
					'status' => 403,
				]
			);
		}

		return $this->rate_limiter->enforce( $scope, $_SERVER );
	}

	private function route_args(): array {
		return [
			'category'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => '',
			],
			'waypoints'      => [
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_waypoints' ],
				'default'           => [],
			],
			'start_mode'     => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default'           => '',
			],
			'custom_start'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'clear_trip'     => [
				'type'              => 'boolean',
				'sanitize_callback' => [ $this, 'sanitize_boolean' ],
				'default'           => false,
			],
			'remove_waypoint' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'move_waypoint'  => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'endpoint_token' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
		];
	}

	private function sanitize_waypoints( mixed $waypoints ): array {
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

	private function sanitize_boolean( mixed $value ): bool {
		return true === $value || 1 === $value || '1' === (string) $value;
	}

	private function request_params_from_request( WP_REST_Request $request ): array {
		$params = [
			'category'     => (string) $request->get_param( 'category' ),
			'waypoints'    => (array) $request->get_param( 'waypoints' ),
			'start_mode'   => (string) $request->get_param( 'start_mode' ),
			'custom_start' => (string) $request->get_param( 'custom_start' ),
		];

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
}
