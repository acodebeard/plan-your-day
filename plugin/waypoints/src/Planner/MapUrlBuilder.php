<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

defined( 'ABSPATH' ) || exit;

final class MapUrlBuilder {
	public function build_category_query( array $category, string $search_area, bool $use_current_location = false ): string {
		$text_query = trim( sanitize_text_field( (string) ( $category['text_query'] ?? $category['label'] ?? '' ) ) );

		return $this->build_search_query( $text_query, $search_area, $use_current_location );
	}

	public function build_search_query( string $search_term, string $search_area, bool $use_current_location = false ): string {
		$text_query = trim( sanitize_text_field( $search_term ) );

		if ( '' === $text_query ) {
			return '';
		}

		if ( preg_match( '/\bnear\s+me\b/i', $text_query ) ) {
			return $text_query;
		}

		if ( $use_current_location ) {
			return $text_query . ' near me';
		}

		$search_area = trim( sanitize_text_field( $search_area ) );

		if ( '' === $search_area ) {
			return $text_query;
		}

		return $text_query . ' near ' . $search_area;
	}

	public function build_state_url( string $action_url, array $params, string $section_id ): string {
		$url = esc_url_raw( $action_url );

		if ( [] !== $params ) {
			$url = add_query_arg( $params, $url );
		}

		return $url . '#' . sanitize_key( $section_id );
	}

	public function build_embed_search_url( string $embed_api_key, string $query ): string {
		$embed_api_key = trim( $embed_api_key );
		$query         = trim( $query );

		if ( '' === $embed_api_key || '' === $query ) {
			return '';
		}

		return add_query_arg(
			[
				'key' => $embed_api_key,
				'q'   => $query,
			],
			'https://www.google.com/maps/embed/v1/search'
		);
	}

	public function build_search_handoff_url( string $query ): string {
		$query = trim( $query );

		if ( '' === $query ) {
			return '';
		}

		return add_query_arg(
			[
				'api'   => '1',
				'query' => $query,
			],
			'https://www.google.com/maps/search/'
		);
	}

	public function build_embed_directions_url( string $embed_api_key, string $origin, array $waypoints ): string {
		$embed_api_key = trim( $embed_api_key );
		$origin        = trim( $origin );
		$waypoints     = $this->valid_waypoints( $waypoints );

		if ( '' === $embed_api_key || '' === $origin || [] === $waypoints ) {
			return '';
		}

		$destination = $waypoints[ count( $waypoints ) - 1 ];
		$params      = [
			'key'         => $embed_api_key,
			'origin'      => $origin,
			'destination' => 'place_id:' . $destination['id'],
			'mode'        => 'walking',
		];

		$intermediates = count( $waypoints ) > 1 ? array_slice( $waypoints, 0, -1 ) : [];

		if ( [] !== $intermediates ) {
			$params['waypoints'] = implode(
				'|',
				array_map(
					static function ( array $waypoint ): string {
						return 'place_id:' . $waypoint['id'];
					},
					$intermediates
				)
			);
		}

		return add_query_arg( $params, 'https://www.google.com/maps/embed/v1/directions' );
	}

	public function build_directions_handoff_url( ?string $origin, array $waypoints ): string {
		$waypoints = $this->valid_waypoints( $waypoints );

		if ( [] === $waypoints ) {
			return '';
		}

		$destination = $waypoints[ count( $waypoints ) - 1 ];
		$params      = [
			'api'                  => '1',
			'destination'          => '' !== $destination['address'] ? $destination['address'] : $destination['label'],
			'destination_place_id' => $destination['id'],
			'travelmode'           => 'walking',
		];
		$origin      = null === $origin ? '' : trim( $origin );

		if ( '' !== $origin ) {
			$params['origin'] = $origin;
		}

		$intermediates = count( $waypoints ) > 1 ? array_slice( $waypoints, 0, -1 ) : [];

		if ( [] !== $intermediates ) {
			$params['waypoints'] = implode(
				'|',
				array_map(
					static function ( array $waypoint ): string {
						return '' !== $waypoint['address'] ? $waypoint['address'] : $waypoint['label'];
					},
					$intermediates
				)
			);
			$params['waypoint_place_ids'] = implode(
				'|',
				array_map(
					static function ( array $waypoint ): string {
						return $waypoint['id'];
					},
					$intermediates
				)
			);
		}

		return add_query_arg( $params, 'https://www.google.com/maps/dir/' );
	}

	private function valid_waypoints( array $waypoints ): array {
		$valid_waypoints = [];

		foreach ( $waypoints as $waypoint ) {
			if ( ! is_array( $waypoint ) ) {
				continue;
			}

			$place_id = PlaceParser::sanitize_place_id( (string) ( $waypoint['id'] ?? '' ) );
			$label    = trim( sanitize_text_field( (string) ( $waypoint['label'] ?? '' ) ) );
			$address  = trim( sanitize_text_field( (string) ( $waypoint['address'] ?? '' ) ) );

			if ( '' === $place_id || ( '' === $label && '' === $address ) ) {
				continue;
			}

			$valid_waypoints[] = [
				'id'      => $place_id,
				'label'   => '' !== $label ? $label : $address,
				'address' => $address,
			];
		}

		return $valid_waypoints;
	}
}
