<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Settings\Settings;

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
		$category_catalog       = $this->category_catalog->get_all();
		$requested_category     = sanitize_key( (string) ( $request_state['category_key'] ?? '' ) );
		$category_key           = isset( $category_catalog[ $requested_category ] ) ? $requested_category : '';
		$selected_waypoint_ids  = $this->waypoint_list->normalize_ids( (array) ( $request_state['selected_waypoint_ids'] ?? [] ) );
		$start_mode             = sanitize_key( (string) ( $request_state['start_mode'] ?? Settings::START_MODE_DEFAULT ) );
		$custom_start           = trim( sanitize_text_field( (string) ( $request_state['custom_start'] ?? '' ) ) );
		$include_results        = ! isset( $options['include_results'] ) || (bool) $options['include_results'];
		$include_trip_waypoints = ! isset( $options['include_trip_waypoints'] ) || (bool) $options['include_trip_waypoints'];

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
		$search_query   = '' !== $category_key
			? $this->map_url_builder->build_category_query( $category, $search_context['search_area'] )
			: '';
		$handoff_search_query = '' !== $category_key
			? $this->map_url_builder->build_category_query( $category, $search_context['search_area'], $search_context['use_current_handoff'] )
			: '';
		$search_origin_coordinates = [
			'latitude'  => null,
			'longitude' => null,
		];
		$search_results       = [];
		$search_results_error = '';
		$trip_waypoints       = [];
		$iframe_src           = '';
		$maps_url             = '';
		$maps_link_label      = __( 'Explore in Google Maps', PLAN_YOUR_DAY_TEXT_DOMAIN );
		$preview_mode_label   = __( 'Google place search', PLAN_YOUR_DAY_TEXT_DOMAIN );
		$overview             = __( 'Choose a category to load Google results, then add exact places to your trip.', PLAN_YOUR_DAY_TEXT_DOMAIN );
		$trip_count_label     = __( 'Trip not started', PLAN_YOUR_DAY_TEXT_DOMAIN );

		if ( $include_results && '' !== $category_key ) {
			$search_origin_coordinates = $this->geocode_search_area( $search_context['search_area'] );
			$text_search_response      = $this->google_api_client->text_search(
				$search_query,
				$search_origin_coordinates['latitude'],
				$search_origin_coordinates['longitude']
			);

			if ( $text_search_response->is_success() ) {
				$search_results = array_slice(
					(array) ( $text_search_response->data()['places'] ?? [] ),
					0,
					$this->settings->get_result_count()
				);
				$this->append_distance_labels( $search_results, $search_origin_coordinates, $search_context['preview_start_label'] );
			} else {
				$search_results_error = $text_search_response->message();
				$messages[]           = [
					'type' => 'warning',
					'text' => $search_results_error,
				];
			}
		}

		if ( $include_trip_waypoints && [] !== $selected_waypoint_ids ) {
			$trip_waypoint_response = $this->get_trip_waypoints( $selected_waypoint_ids );
			$trip_waypoints         = $trip_waypoint_response['waypoints'];
			$messages               = array_merge( $messages, $trip_waypoint_response['messages'] );
			$selected_waypoint_ids  = array_map(
				static function ( array $waypoint ): string {
					return (string) $waypoint['id'];
				},
				$trip_waypoints
			);
		}

		$has_category         = '' !== $category_key;
		$has_trip             = [] !== $trip_waypoints;
		$search_results_count = count( $search_results );
		$search_results_label = $has_category
			? sprintf(
				/* translators: %d is the number of Google results. */
				_n( '%d Google result', '%d Google results', $search_results_count, PLAN_YOUR_DAY_TEXT_DOMAIN ),
				$search_results_count
			)
			: __( 'No Google results loaded', PLAN_YOUR_DAY_TEXT_DOMAIN );

		if ( $has_trip ) {
			$route_state = $this->build_trip_route_state( $trip_waypoints, $search_context );
			$messages    = array_merge( $messages, $route_state['messages'] );

			$trip_count_label   = $route_state['trip_count_label'];
			$preview_mode_label = __( 'Walking directions', PLAN_YOUR_DAY_TEXT_DOMAIN );
			$maps_link_label    = __( 'Open trip in Google Maps', PLAN_YOUR_DAY_TEXT_DOMAIN );
			$overview           = $route_state['overview'];
			$iframe_src         = $route_state['iframe_src'];
			$maps_url           = $route_state['maps_url'];
		} elseif ( $has_category ) {
			$overview = sprintf(
				/* translators: 1: category label, 2: start summary. */
				__( 'Browsing Google results for %1$s near %2$s. Add any result to start building a walking trip.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				$category['label'],
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
						'text' => __( 'Add a valid Google Maps Embed API key before relying on the on-site search preview.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
					];
				}
			}

			if ( $this->settings->is_maps_handoff_enabled() ) {
				$maps_url = $this->map_url_builder->build_search_handoff_url( $handoff_search_query );
			}
		}

		return [
			'category_key'          => $category_key,
			'category'              => $category,
			'category_catalog'      => $category_catalog,
			'has_category'          => $has_category,
			'search_query'          => $search_query,
			'handoff_search_query'  => $handoff_search_query,
			'search_results'        => $search_results,
			'search_results_error'  => $search_results_error,
			'search_results_label'  => $search_results_label,
			'selected_waypoint_ids' => array_values( $selected_waypoint_ids ),
			'trip_waypoints'        => $trip_waypoints,
			'has_trip'              => $has_trip,
			'start_mode'            => $start_mode,
			'custom_start'          => $custom_start,
			'preview_start_label'   => $search_context['preview_start_label'],
			'handoff_start_label'   => $search_context['handoff_start_label'],
			'start_note_text'       => $search_context['start_note_text'],
			'iframe_src'            => $iframe_src,
			'maps_url'              => $maps_url,
			'maps_link_label'       => $maps_link_label,
			'preview_mode_label'    => $preview_mode_label,
			'overview'              => $overview,
			'trip_count_label'      => $trip_count_label,
			'messages'              => $messages,
		];
	}

	private function geocode_search_area( string $search_area ): array {
		$configured_latitude  = $this->settings->get_default_location_latitude();
		$configured_longitude = $this->settings->get_default_location_longitude();

		if (
			$search_area === $this->settings->get_default_location_address() &&
			null !== $configured_latitude &&
			null !== $configured_longitude
		) {
			return [
				'latitude'  => $configured_latitude,
				'longitude' => $configured_longitude,
			];
		}

		$geocode_response = $this->google_api_client->geocode( $search_area );

		if ( ! $geocode_response->is_success() ) {
			return [
				'latitude'  => null,
				'longitude' => null,
			];
		}

		return [
			'latitude'  => $geocode_response->data()['latitude'] ?? null,
			'longitude' => $geocode_response->data()['longitude'] ?? null,
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

	private function get_trip_waypoints( array $waypoint_ids ): array {
		$waypoints = [];
		$messages  = [];

		foreach ( $waypoint_ids as $waypoint_id ) {
			$detail_response = $this->google_api_client->place_details( $waypoint_id );

			if ( ! $detail_response->is_success() || empty( $detail_response->data()['place'] ) ) {
				$messages[] = [
					'type' => 'warning',
					'text' => __( 'One selected place could not be loaded from Google and was skipped.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				];
				continue;
			}

			$waypoints[] = $detail_response->data()['place'];
		}

		return [
			'waypoints' => $waypoints,
			'messages'  => $messages,
		];
	}

	private function build_trip_route_state( array $trip_waypoints, array $search_context ): array {
		$messages            = [];
		$trip_count          = count( $trip_waypoints );
		$trip_count_label    = sprintf(
			/* translators: %d is the number of selected waypoints. */
			_n( '%d waypoint selected', '%d waypoints selected', $trip_count, PLAN_YOUR_DAY_TEXT_DOMAIN ),
			$trip_count
		);
		$route_description   = $this->build_route_description( $trip_waypoints, $search_context['handoff_summary'] );
		$overview            = sprintf(
			/* translators: 1: waypoint count label, 2: route description. */
			__( '%1$s. %2$s', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			$trip_count_label,
			$route_description
		);
		$iframe_src          = '';
		$maps_url            = '';

		if ( $this->settings->is_map_preview_enabled() ) {
			$iframe_src = $this->map_url_builder->build_embed_directions_url(
				$this->settings->get_google_maps_embed_api_key(),
				$search_context['search_area'],
				$trip_waypoints
			);

			if ( '' === $iframe_src ) {
				$messages[] = [
					'type' => 'warning',
					'text' => __( 'Add a valid Google Maps Embed API key before relying on the on-site trip preview.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				];
			}
		}

		if ( $this->settings->is_maps_handoff_enabled() ) {
			$maps_url = $this->map_url_builder->build_directions_handoff_url(
				$search_context['directions_origin'],
				$trip_waypoints
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
			return sprintf(
				/* translators: 1: start summary, 2: destination label. */
				__( 'Walking directions run from %1$s to %2$s.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				$handoff_summary,
				$destination['label']
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

		return sprintf(
			/* translators: 1: start summary, 2: destination label, 3: via stop labels. */
			__( 'Walking directions run from %1$s to %2$s via %3$s.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			$handoff_summary,
			$destination['label'],
			$via_label
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
			return sprintf(
				/* translators: 1: first label, 2: second label. */
				__( '%1$s and %2$s', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				$labels[0],
				$labels[1]
			);
		}

		$last_label = array_pop( $labels );

		return sprintf(
			/* translators: 1: comma-separated label list, 2: final label. */
			__( '%1$s, and %2$s', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			implode( ', ', $labels ),
			$last_label
		);
	}
}
