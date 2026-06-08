<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class WaypointList {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function normalize_ids( array $waypoint_ids ): array {
		$normalized_waypoint_ids = [];
		$max_waypoints           = max( 1, $this->settings->get_max_waypoints() );

		foreach ( $waypoint_ids as $waypoint_id ) {
			$waypoint_id = PlaceParser::sanitize_place_id( (string) $waypoint_id );

			if ( '' === $waypoint_id || in_array( $waypoint_id, $normalized_waypoint_ids, true ) ) {
				continue;
			}

			$normalized_waypoint_ids[] = $waypoint_id;

			if ( count( $normalized_waypoint_ids ) >= $max_waypoints ) {
				break;
			}
		}

		return $normalized_waypoint_ids;
	}

	public function move_id( array $waypoint_ids, string $place_id, string $direction ): array {
		$waypoint_ids  = $this->normalize_ids( $waypoint_ids );
		$place_id      = PlaceParser::sanitize_place_id( $place_id );
		$current_index = array_search( $place_id, $waypoint_ids, true );

		if ( false === $current_index ) {
			return $waypoint_ids;
		}

		$next_index = 'up' === $direction ? $current_index - 1 : $current_index + 1;

		if ( $next_index < 0 || $next_index >= count( $waypoint_ids ) ) {
			return $waypoint_ids;
		}

		$reordered_waypoint_ids = $waypoint_ids;
		$moved_waypoint_id      = $reordered_waypoint_ids[ $current_index ];

		array_splice( $reordered_waypoint_ids, $current_index, 1 );
		array_splice( $reordered_waypoint_ids, $next_index, 0, [ $moved_waypoint_id ] );

		return array_values( $reordered_waypoint_ids );
	}
}
