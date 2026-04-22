<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class RequestStateParser {
	private WaypointList $waypoint_list;

	public function __construct( WaypointList $waypoint_list ) {
		$this->waypoint_list = $waypoint_list;
	}

	public function parse( array $request ): array {
		$selected_waypoint_ids = isset( $request['waypoints'] ) ? wp_unslash( (array) $request['waypoints'] ) : [];
		$selected_waypoint_ids = $this->waypoint_list->normalize_ids( $selected_waypoint_ids );

		if ( isset( $request['clear_trip'] ) ) {
			$selected_waypoint_ids = [];
		} elseif ( isset( $request['remove_waypoint'] ) ) {
			$remove_waypoint_id = PlaceParser::sanitize_place_id( wp_unslash( (string) $request['remove_waypoint'] ) );

			if ( '' !== $remove_waypoint_id ) {
				$selected_waypoint_ids = array_values(
					array_filter(
						$selected_waypoint_ids,
						static function ( string $waypoint_id ) use ( $remove_waypoint_id ): bool {
							return $waypoint_id !== $remove_waypoint_id;
						}
					)
				);
			}
		} elseif ( isset( $request['move_waypoint'] ) ) {
			$move_waypoint_request = sanitize_text_field( wp_unslash( (string) $request['move_waypoint'] ) );
			$move_waypoint_parts   = explode( ':', $move_waypoint_request, 2 );
			$move_waypoint_id      = PlaceParser::sanitize_place_id( $move_waypoint_parts[0] ?? '' );
			$move_direction        = sanitize_key( $move_waypoint_parts[1] ?? '' );

			if ( '' !== $move_waypoint_id && in_array( $move_direction, [ 'up', 'down' ], true ) ) {
				$selected_waypoint_ids = $this->waypoint_list->move_id( $selected_waypoint_ids, $move_waypoint_id, $move_direction );
			}
		}

		return [
			'category_key'          => isset( $request['category'] ) ? sanitize_key( wp_unslash( (string) $request['category'] ) ) : '',
			'category_search'       => isset( $request['category_search'] ) ? trim( sanitize_text_field( wp_unslash( (string) $request['category_search'] ) ) ) : '',
			'selected_waypoint_ids' => $selected_waypoint_ids,
			'start_mode'            => isset( $request['start_mode'] ) ? sanitize_key( wp_unslash( (string) $request['start_mode'] ) ) : Settings::START_MODE_DEFAULT,
			'custom_start'          => isset( $request['custom_start'] ) ? trim( sanitize_text_field( wp_unslash( (string) $request['custom_start'] ) ) ) : '',
		];
	}
}
