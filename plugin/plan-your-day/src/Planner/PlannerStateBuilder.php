<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Settings\Settings;
use Acodebeard\PlanYourDay\Support\DebugLogger;

defined( 'ABSPATH' ) || exit;

final class PlannerStateBuilder {
	private Settings $settings;
	private CategoryCatalog $category_catalog;
	private GoogleApiClientInterface $google_api_client;
	private WaypointList $waypoint_list;
	private StartContextResolver $start_context_resolver;
	private MapUrlBuilder $map_url_builder;
	private DistanceFormatter $distance_formatter;
	private RequestOriginValidator $request_origin_validator;

	public function __construct(
		Settings $settings,
		CategoryCatalog $category_catalog,
		GoogleApiClientInterface $google_api_client,
		WaypointList $waypoint_list,
		StartContextResolver $start_context_resolver,
		MapUrlBuilder $map_url_builder,
		DistanceFormatter $distance_formatter,
		RequestOriginValidator $request_origin_validator
	) {
		$this->settings                 = $settings;
		$this->category_catalog         = $category_catalog;
		$this->google_api_client        = $google_api_client;
		$this->waypoint_list            = $waypoint_list;
		$this->start_context_resolver   = $start_context_resolver;
		$this->map_url_builder          = $map_url_builder;
		$this->distance_formatter       = $distance_formatter;
		$this->request_origin_validator = $request_origin_validator;
	}

	public function build( array $request_state, array $options = [] ): array {
		$category_catalog          = $this->category_catalog->get_all();
		$requested_category        = sanitize_key( (string) ( $request_state['category_key'] ?? '' ) );
		$requested_category_search = trim( sanitize_text_field( (string) ( $request_state['category_search'] ?? '' ) ) );
		$category_key              = '' === $requested_category_search && isset( $category_catalog[ $requested_category ] ) ? $requested_category : '';
		$page_token                = trim( sanitize_text_field( (string) ( $request_state['page_token'] ?? '' ) ) );
		$append_results            = ! empty( $request_state['append_results'] );
		$loaded_result_ids         = $this->normalize_loaded_result_ids( (array) ( $request_state['loaded_result_ids'] ?? [] ) );
		$selected_waypoint_ids     = $this->waypoint_list->normalize_ids( (array) ( $request_state['selected_waypoint_ids'] ?? [] ) );
		$start_mode                = sanitize_key( (string) ( $request_state['start_mode'] ?? Settings::START_MODE_DEFAULT ) );
		$custom_start              = trim( sanitize_text_field( (string) ( $request_state['custom_start'] ?? '' ) ) );
		$include_results           = ! isset( $options['include_results'] ) || (bool) $options['include_results'];
		$include_trip_waypoints    = ! isset( $options['include_trip_waypoints'] ) || (bool) $options['include_trip_waypoints'];

		if (
			! empty( $options['require_same_site'] ) &&
			! $this->request_origin_validator->is_same_site_request( (array) ( $options['server'] ?? $_SERVER ) )
		) {
			$include_results        = false;
			$include_trip_waypoints = false;
		}

		$messages       = [];
		$category       = '' !== $category_key ? $category_catalog[ $category_key ] : [];
		$search_context = $this->start_context_resolver->resolve( $start_mode, $custom_start );
		$messages       = array_merge( $messages, $search_context['messages'] );
		$active_search_term  = '' !== $requested_category_search
			? $requested_category_search
			: trim( sanitize_text_field( (string) ( $category['text_query'] ?? $category['label'] ?? '' ) ) );
		$active_search_label = '' !== $requested_category_search
			? $requested_category_search
			: trim( sanitize_text_field( (string) ( $category['label'] ?? '' ) ) );
		$search_query        = '' !== $active_search_term
			? $this->map_url_builder->build_search_query( $active_search_term, $search_context['search_area'] )
			: '';
		$handoff_search_query = '' !== $active_search_term
			? $this->map_url_builder->build_search_query( $active_search_term, $search_context['search_area'], $search_context['use_current_handoff'] )
			: '';
		$search_origin_coordinates = [
			'latitude'            => null,
			'longitude'           => null,
			'custom_start_status' => '',
		];
		$search_results       = [];
		$search_results_error = '';
		$next_page_token      = '';
		$has_more_results     = false;
		$trip_waypoints       = [];
		$resolved_waypoints   = [];
		$iframe_src           = '';
		$maps_url             = '';
		$maps_link_label      = __( 'Explore in Google Maps', 'plan-your-day' );
		$preview_mode_label   = __( 'Google place search', 'plan-your-day' );
		$overview             = __( 'Search for any category or pick one below to load Google results, then add exact places to your trip.', 'plan-your-day' );
		$trip_count_label     = $this->settings->get_frontend_copy_value( 'trip_not_started_label' );
		$search_results_count = 0;

		if ( $include_results && '' !== $search_query ) {
			$search_origin_coordinates = $this->geocode_search_area( $search_context['search_area'], $start_mode, $custom_start );
			$text_search_response      = $this->google_api_client->text_search(
				$search_query,
				$search_origin_coordinates['latitude'],
				$search_origin_coordinates['longitude'],
				$page_token
			);

			if ( $text_search_response->is_success() ) {
				$search_results = array_slice(
					(array) ( $text_search_response->data()['places'] ?? [] ),
					0,
					$this->settings->get_result_count()
				);
				$next_page_token = trim( sanitize_text_field( (string) ( $text_search_response->data()['nextPageToken'] ?? '' ) ) );
				$has_more_results = '' !== $next_page_token;

				if ( $append_results && '' !== $page_token && [] !== $loaded_result_ids ) {
					$search_results = $this->filter_loaded_results( $search_results, $loaded_result_ids );
				}

				$this->append_distance_labels( $search_results, $search_origin_coordinates, $search_context['preview_start_label'] );
				$search_results_count = $append_results && [] !== $loaded_result_ids
					? count( $loaded_result_ids ) + count( $search_results )
					: count( $search_results );
			} else {
				$search_results_error = $text_search_response->message();
				$messages[]           = [
					'type' => 'warning',
					'text' => $search_results_error,
				];
			}

			DebugLogger::log(
				'planner.search_results',
				[
					'category_key'         => $category_key,
					'requested_search'     => $requested_category_search,
					'active_search_label'  => $active_search_label,
					'search_query'         => $search_query,
					'search_results_count' => count( $search_results ),
					'search_results_error' => $search_results_error,
					'page_token'           => $page_token,
					'next_page_token'      => $next_page_token,
					'append_results'       => $append_results,
				]
			);
		}

		if ( $include_trip_waypoints && [] !== $selected_waypoint_ids ) {
			$trip_waypoint_response = $this->get_trip_waypoints( $selected_waypoint_ids, $options );
			$trip_waypoints         = $trip_waypoint_response['waypoints'];
			$resolved_waypoints     = $trip_waypoint_response['resolved_waypoints'];
			$messages               = array_merge( $messages, $trip_waypoint_response['messages'] );
		}

		$has_category         = '' !== $category_key;
		$has_categories       = [] !== $category_catalog;
		$has_search           = '' !== $search_query;
		$is_custom_search     = '' !== $requested_category_search;
		$has_trip             = [] !== $trip_waypoints;
		$search_context_key   = $has_search
			? $this->build_search_context_key( $category_key, $requested_category_search, $start_mode, $custom_start, $search_context['search_area'] )
			: '';
		$search_results_label = $has_search
			? sprintf(
				/* translators: %d: number of Google results. */
				_n( '%d Google result', '%d Google results', $search_results_count, 'plan-your-day' ),
				$search_results_count
			)
			: __( 'No Google results loaded', 'plan-your-day' );

		if ( $has_trip ) {
			$route_state = $this->build_trip_route_state( $trip_waypoints, $resolved_waypoints, $search_context );
			$messages    = array_merge( $messages, $route_state['messages'] );

			$trip_count_label   = $route_state['trip_count_label'];
			$preview_mode_label = $this->settings->get_frontend_copy_value( 'preview_mode_label_trip' );
			$maps_link_label    = $this->settings->get_frontend_copy_value( 'maps_link_label_trip' );
			$overview           = $route_state['overview'];
			$iframe_src         = $route_state['iframe_src'];
			$maps_url           = $route_state['maps_url'];
		} elseif ( $has_search ) {
			$overview = sprintf(
				/* translators: 1: active search label, 2: starting point summary. */
				__( 'Browsing Google results for %1$s near %2$s. Add any result to start building a walking trip.', 'plan-your-day' ),
				$active_search_label,
				$search_context['handoff_summary']
			);

			if ( $this->settings->is_map_preview_enabled() ) {
				$iframe_src = $this->map_url_builder->build_embed_search_url(
					$this->settings->get_google_maps_embed_api_key(),
					$search_query
				);

				if ( '' === $iframe_src ) {
					$messages[] = [
						'type' => 'warning',
						'text' => __( 'Add a valid Google Maps Embed API key before relying on the on-site search preview.', 'plan-your-day' ),
					];
				}
			}

			if ( $this->settings->is_maps_handoff_enabled() ) {
				$maps_url = $this->map_url_builder->build_search_handoff_url( $handoff_search_query );
			}
		} elseif ( ! $has_categories ) {
			$overview = __( 'Search for any category to load Google results, then add exact places to your trip.', 'plan-your-day' );
		}

		return [
			'category_key'          => $category_key,
			'category_search'       => $is_custom_search ? $requested_category_search : '',
			'category'              => $category,
			'category_catalog'      => $category_catalog,
			'has_category'          => $has_category,
			'has_categories'        => $has_categories,
			'has_search'            => $has_search,
			'is_custom_search'      => $is_custom_search,
			'active_search_label'   => $active_search_label,
			'search_context_key'    => $search_context_key,
			'search_query'          => $search_query,
			'handoff_search_query'  => $handoff_search_query,
			'search_results'        => $search_results,
			'next_page_token'       => $next_page_token,
			'has_more_results'      => $has_more_results,
			'search_results_error'  => $search_results_error,
			'search_results_label'  => $search_results_label,
			'selected_waypoint_ids' => array_values( $selected_waypoint_ids ),
			'trip_waypoints'        => $trip_waypoints,
			'has_trip'              => $has_trip,
			'start_mode'            => $start_mode,
			'custom_start'          => $custom_start,
			'custom_start_status'   => $search_origin_coordinates['custom_start_status'],
			'preview_start_label'   => $search_context['preview_start_label'],
			'handoff_start_label'   => $search_context['handoff_start_label'],
			'iframe_src'            => $iframe_src,
			'maps_url'              => $maps_url,
			'maps_link_label'       => $maps_link_label,
			'preview_mode_label'    => $preview_mode_label,
			'overview'              => $overview,
			'trip_count_label'      => $trip_count_label,
			'messages'              => $messages,
		];
	}

	/**
	 * Loaded result IDs come from client request state, so they must be
	 * sanitized and deduplicated without being capped by the max selected-trip
	 * waypoint limit.
	 *
	 * @param array<int, mixed> $loaded_result_ids
	 * @return array<int, string>
	 */
	private function normalize_loaded_result_ids( array $loaded_result_ids ): array {
		$normalized_loaded_result_ids = [];

		foreach ( $loaded_result_ids as $loaded_result_id ) {
			$loaded_result_id = PlaceParser::sanitize_place_id( (string) $loaded_result_id );

			if ( '' === $loaded_result_id || in_array( $loaded_result_id, $normalized_loaded_result_ids, true ) ) {
				continue;
			}

			$normalized_loaded_result_ids[] = $loaded_result_id;
		}

		return $normalized_loaded_result_ids;
	}

	/**
	 * @param array<int, array<string, mixed>> $search_results
	 * @param array<int, string>               $loaded_result_ids
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_loaded_results( array $search_results, array $loaded_result_ids ): array {
		return array_values(
			array_filter(
				$search_results,
				static function ( array $result ) use ( $loaded_result_ids ): bool {
					$result_id = PlaceParser::sanitize_place_id( (string) ( $result['id'] ?? '' ) );

					return '' !== $result_id && ! in_array( $result_id, $loaded_result_ids, true );
				}
			)
		);
	}

	private function build_search_context_key( string $category_key, string $category_search, string $start_mode, string $custom_start, string $search_area ): string {
		return md5(
			(string) wp_json_encode(
				[
					'category_key'    => $category_key,
					'category_search' => $category_search,
					'start_mode'      => $start_mode,
					'custom_start'    => $custom_start,
					'search_area'     => $search_area,
				]
			)
		);
	}

	private function geocode_search_area( string $search_area, string $start_mode, string $custom_start ): array {
		$configured_latitude  = $this->settings->get_default_location_latitude();
		$configured_longitude = $this->settings->get_default_location_longitude();
		$is_custom_start      = Settings::START_MODE_CUSTOM === $start_mode && '' !== $custom_start;

		if (
			$search_area === $this->settings->get_default_location_address() &&
			null !== $configured_latitude &&
			null !== $configured_longitude
		) {
			return [
				'latitude'            => $configured_latitude,
				'longitude'           => $configured_longitude,
				'custom_start_status' => $is_custom_start ? 'found' : '',
			];
		}

		$geocode_response = $this->google_api_client->geocode( $search_area );

		if ( ! $geocode_response->is_success() ) {
			return [
				'latitude'            => null,
				'longitude'           => null,
				'custom_start_status' => $is_custom_start ? 'not_found' : '',
			];
		}

		$latitude  = $geocode_response->data()['latitude'] ?? null;
		$longitude = $geocode_response->data()['longitude'] ?? null;
		$is_found  = null !== $latitude && null !== $longitude;

		return [
			'latitude'            => $latitude,
			'longitude'           => $longitude,
			'custom_start_status' => $is_custom_start ? ( $is_found ? 'found' : 'not_found' ) : '',
		];
	}

	private function append_distance_labels( array &$search_results, array $origin_coordinates, string $reference_label ): void {
		if ( null === $origin_coordinates['latitude'] || null === $origin_coordinates['longitude'] ) {
			return;
		}

		foreach ( $search_results as $result_index => $result ) {
			if ( ! is_array( $result ) || null === ( $result['latitude'] ?? null ) || null === ( $result['longitude'] ?? null ) ) {
				continue;
			}

			$distance_miles = $this->distance_formatter->calculate_miles(
				(float) $origin_coordinates['latitude'],
				(float) $origin_coordinates['longitude'],
				(float) $result['latitude'],
				(float) $result['longitude']
			);

			$search_results[ $result_index ]['distance_label'] = $this->distance_formatter->format_label(
				$distance_miles,
				$reference_label,
				$this->settings->get_distance_unit()
			);
		}
	}

	private function get_trip_waypoints( array $waypoint_ids, array $options = [] ): array {
		$waypoints             = [];
		$resolved_waypoints    = [];
		$messages              = [];
		$is_partial            = false;
		$deadline_at           = $this->resolve_trip_waypoint_deadline_at( $options );
		$place_details_timeout = $this->normalize_trip_waypoint_timeout( $options['trip_waypoint_place_details_timeout'] ?? null );
		$max_failures          = $this->normalize_trip_waypoint_limit( $options['trip_waypoint_max_failures'] ?? null );
		$failure_count         = 0;

		foreach ( $waypoint_ids as $waypoint_id ) {
			$request_timeout = $this->resolve_trip_waypoint_request_timeout( $place_details_timeout, $deadline_at );

			if ( null === $request_timeout ) {
				$is_partial = true;
				$messages[] = [
					'type' => 'warning',
					'text' => $this->settings->get_frontend_copy_value( 'trip_timeout_warning' ),
				];
				DebugLogger::log(
					'planner.trip_waypoints.deadline_reached',
					[
						'requested_ids' => $waypoint_ids,
						'loaded_count'  => count( $waypoints ),
					]
				);
				break;
			}

			$detail_response = $this->google_api_client->place_details( $waypoint_id, $request_timeout );

			if ( ! $detail_response->is_success() || empty( $detail_response->data()['place'] ) ) {
				++$failure_count;
				$waypoints[] = $this->build_unresolved_trip_waypoint( $waypoint_id );
				DebugLogger::log(
					'planner.trip_waypoint.skipped',
					[
						'waypoint_id' => $waypoint_id,
						'response'    => [
							'success'     => $detail_response->is_success(),
							'message'     => $detail_response->message(),
							'status_code' => $detail_response->status_code(),
						],
					]
				);
				$messages[] = [
					'type' => 'warning',
					'text' => $this->settings->get_frontend_copy_value( 'trip_place_skipped_warning' ),
				];

				if ( null !== $max_failures && $failure_count >= $max_failures ) {
					$is_partial = true;
					$messages[] = [
						'type' => 'warning',
						'text' => $this->settings->get_frontend_copy_value( 'trip_repeated_errors_warning' ),
					];
					DebugLogger::log(
						'planner.trip_waypoints.failure_limit_reached',
						[
							'requested_ids' => $waypoint_ids,
							'loaded_count'  => count( $waypoints ),
							'failure_count' => $failure_count,
							'max_failures'  => $max_failures,
						]
					);
					break;
				}

				continue;
			}

			$waypoints[]          = $detail_response->data()['place'];
			$resolved_waypoints[] = $detail_response->data()['place'];
		}

		DebugLogger::log(
			'planner.trip_waypoints.loaded',
			[
				'requested_ids' => $waypoint_ids,
				'loaded_count'  => count( $waypoints ),
				'loaded_ids'    => array_map(
					static function ( array $waypoint ): string {
						return (string) ( $waypoint['id'] ?? '' );
					},
					$waypoints
				),
			]
		);

		return [
			'waypoints'          => $waypoints,
			'resolved_waypoints' => $resolved_waypoints,
			'messages'           => $messages,
			'is_partial'         => $is_partial,
		];
	}

	private function build_unresolved_trip_waypoint( string $waypoint_id ): array {
		return [
			'id'         => $waypoint_id,
			'label'      => $this->settings->get_frontend_copy_value( 'unresolved_waypoint_label' ),
			'address'    => $this->settings->get_frontend_copy_value( 'unresolved_waypoint_address' ),
			'unresolved' => true,
		];
	}

	private function resolve_trip_waypoint_deadline_at( array $options ): ?float {
		if ( isset( $options['trip_waypoint_deadline_at'] ) && is_numeric( $options['trip_waypoint_deadline_at'] ) ) {
			return (float) $options['trip_waypoint_deadline_at'];
		}

		if ( ! isset( $options['trip_waypoint_deadline_seconds'] ) || ! is_numeric( $options['trip_waypoint_deadline_seconds'] ) ) {
			return null;
		}

		$deadline_seconds = (float) $options['trip_waypoint_deadline_seconds'];

		if ( $deadline_seconds <= 0 ) {
			return null;
		}

		return microtime( true ) + $deadline_seconds;
	}

	private function resolve_trip_waypoint_request_timeout( ?int $place_details_timeout, ?float $deadline_at ): ?int {
		$request_timeout = $place_details_timeout ?? $this->settings->get_google_api_timeout();

		if ( null === $deadline_at ) {
			return $request_timeout;
		}

		$remaining_seconds = $deadline_at - microtime( true );

		if ( $remaining_seconds <= 0 ) {
			return null;
		}

		return max( 1, min( $request_timeout, (int) ceil( $remaining_seconds ) ) );
	}

	private function normalize_trip_waypoint_timeout( mixed $timeout ): ?int {
		if ( ! is_numeric( $timeout ) ) {
			return null;
		}

		return max( 1, min( $this->settings->get_google_api_timeout(), absint( $timeout ) ) );
	}

	private function normalize_trip_waypoint_limit( mixed $limit ): ?int {
		if ( ! is_numeric( $limit ) ) {
			return null;
		}

		return max( 1, absint( $limit ) );
	}

	private function build_trip_route_state( array $trip_waypoints, array $resolved_waypoints, array $search_context ): array {
		$messages            = [];
		$trip_count          = count( $trip_waypoints );
		$trip_count_label    = $this->count_copy( 'trip_count_single', 'trip_count_plural', $trip_count );
		$route_description   = [] !== $resolved_waypoints
			? $this->build_route_description( $resolved_waypoints, $search_context['handoff_summary'] )
			: $this->settings->get_frontend_copy_value( 'trip_route_unresolved' );
		$overview            = $this->build_trip_overview( $trip_count_label, $route_description );
		$iframe_src          = '';
		$maps_url            = '';

		if ( count( $resolved_waypoints ) !== count( $trip_waypoints ) ) {
			$messages[] = [
				'type' => 'warning',
				'text' => $this->settings->get_frontend_copy_value( 'trip_unavailable_until_loaded_warning' ),
			];

			return [
				'trip_count_label' => $trip_count_label,
				'overview'         => $overview,
				'iframe_src'       => $iframe_src,
				'maps_url'         => $maps_url,
				'messages'         => $messages,
			];
		}

		if ( $this->settings->is_map_preview_enabled() ) {
			$iframe_src = $this->map_url_builder->build_embed_directions_url(
				$this->settings->get_google_maps_embed_api_key(),
				$search_context['search_area'],
				$resolved_waypoints
			);

			if ( '' === $iframe_src ) {
				$messages[] = [
					'type' => 'warning',
					'text' => $this->settings->get_frontend_copy_value( 'trip_preview_key_warning' ),
				];
			}
		}

		if ( $this->settings->is_maps_handoff_enabled() ) {
			$maps_url = $this->map_url_builder->build_directions_handoff_url(
				$search_context['directions_origin'],
				$resolved_waypoints
			);
		}

		return [
			'trip_count_label' => $trip_count_label,
			'overview'         => $overview,
			'iframe_src'       => $iframe_src,
			'maps_url'         => $maps_url,
			'messages'         => $messages,
		];
	}

	private function build_route_description( array $trip_waypoints, string $handoff_summary ): string {
		$destination   = $trip_waypoints[ count( $trip_waypoints ) - 1 ];
		$intermediates = count( $trip_waypoints ) > 1 ? array_slice( $trip_waypoints, 0, -1 ) : [];

		if ( [] === $intermediates ) {
			return $this->settings->format_frontend_copy(
				'route_description_direct',
				[
					'start'       => $handoff_summary,
					'destination' => (string) $destination['label'],
				]
			);
		}

		$via_label = 1 === count( $intermediates )
			? $intermediates[0]['label']
			: $this->format_label_list(
				array_map(
					static function ( array $waypoint ): string {
						return (string) $waypoint['label'];
					},
					$intermediates
				)
			);

		return $this->settings->format_frontend_copy(
			'route_description_via',
			[
				'start'       => $handoff_summary,
				'destination' => (string) $destination['label'],
				'via'         => $via_label,
			]
		);
	}

	private function format_label_list( array $labels ): string {
		$labels = array_values(
			array_filter(
				array_map(
					static function ( mixed $label ): string {
						return trim( (string) $label );
					},
					$labels
				)
			)
		);
		$label_count = count( $labels );

		if ( 0 === $label_count ) {
			return '';
		}

		if ( 1 === $label_count ) {
			return $labels[0];
		}

		if ( 2 === $label_count ) {
			return $this->settings->format_frontend_copy(
				'label_list_pair',
				[
					'first'  => $labels[0],
					'second' => $labels[1],
				]
			);
		}

		$last_label = array_pop( $labels );

		return $this->settings->format_frontend_copy(
			'label_list_many',
			[
				'list' => implode( ', ', $labels ),
				'last' => (string) $last_label,
			]
		);
	}

	private function count_copy( string $single_key, string $plural_key, int $count ): string {
		$template_key = 1 === $count ? $single_key : $plural_key;

		return $this->settings->format_frontend_copy(
			$template_key,
			[
				'count' => (string) $count,
			]
		);
	}

	private function build_trip_overview( string $trip_count_label, string $route_description ): string {
		$template = $this->settings->get_frontend_copy_value( 'trip_overview_template' );

		if ( '' === $template ) {
			return '';
		}

		return trim(
			$this->settings->format_frontend_copy(
				'trip_overview_template',
				[
					'trip_count'        => $trip_count_label,
					'route_description' => $route_description,
				]
			)
		);
	}
}
