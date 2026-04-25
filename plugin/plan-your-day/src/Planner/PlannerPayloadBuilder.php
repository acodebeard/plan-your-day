<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

defined( 'ABSPATH' ) || exit;

final class PlannerPayloadBuilder {
	public function build_browse_payload( array $planner_state ): array {
		return [
			'categoryKey'        => $planner_state['category_key'],
			'categorySearch'     => $planner_state['category_search'],
			'categoryLabel'      => $planner_state['has_search'] ? $planner_state['active_search_label'] : __( 'Not selected', 'plan-your-day' ),
			'hasCategory'        => $planner_state['has_category'],
			'hasCategories'      => $planner_state['has_categories'],
			'hasSearch'          => $planner_state['has_search'],
			'isCustomSearch'     => $planner_state['is_custom_search'],
			'searchResults'      => array_values( $planner_state['search_results'] ),
			'searchResultsLabel' => $planner_state['search_results_label'],
			'resultsEmptyState'  => $this->get_empty_results_state( $planner_state ),
			'messages'           => array_values( $planner_state['messages'] ),
		];
	}

	public function build_route_payload( array $planner_state ): array {
		return [
			'categoryKey'         => $planner_state['category_key'],
			'categorySearch'      => $planner_state['category_search'],
			'categoryLabel'       => $planner_state['has_search'] ? $planner_state['active_search_label'] : __( 'Not selected', 'plan-your-day' ),
			'hasSearch'           => $planner_state['has_search'],
			'selectedWaypointIds' => array_values( $planner_state['selected_waypoint_ids'] ),
			'tripWaypoints'       => array_values( $planner_state['trip_waypoints'] ),
			'hasTrip'             => $planner_state['has_trip'],
			'tripCountLabel'      => $planner_state['trip_count_label'],
			'iframeSrc'           => $planner_state['iframe_src'],
			'mapsUrl'             => $planner_state['maps_url'],
			'mapsLinkLabel'       => $planner_state['maps_link_label'],
			'previewModeLabel'    => $planner_state['preview_mode_label'],
			'overview'            => $planner_state['overview'],
			'previewStartLabel'   => $planner_state['preview_start_label'],
			'handoffStartLabel'   => $planner_state['handoff_start_label'],
			'startNoteText'       => $planner_state['start_note_text'],
			'emptyPreviewState'   => $this->get_empty_preview_state( $planner_state ),
			'messages'            => array_values( $planner_state['messages'] ),
		];
	}

	public function get_empty_results_state( array $planner_state ): array {
		if ( ! empty( $planner_state['search_results_error'] ) ) {
			return [
				'heading' => __( 'Google results unavailable', 'plan-your-day' ),
				'body'    => __( 'Google place results are unavailable right now. Try again later or open the Google Maps handoff link.', 'plan-your-day' ),
			];
		}

		if ( ! $planner_state['has_search'] ) {
			return [
				'heading' => __( 'Search for any category', 'plan-your-day' ),
				'body'    => $planner_state['has_categories']
					? __( 'Use the search box or choose a preset category to load real place results.', 'plan-your-day' )
					: __( 'Use the search box to load real place results.', 'plan-your-day' ),
			];
		}

		return [
			'heading' => __( 'No matching Google results', 'plan-your-day' ),
			'body'    => __( 'Try a different search or change the starting area.', 'plan-your-day' ),
		];
	}

	public function get_empty_preview_state( array $planner_state ): array {
		if ( ! $planner_state['has_search'] && ! $planner_state['has_trip'] ) {
			return [
				'heading' => __( 'Start with a category search', 'plan-your-day' ),
				'body'    => $planner_state['has_categories']
					? __( 'Use the search box or choose a preset category to load Google results, then add the places you want to turn into trip waypoints.', 'plan-your-day' )
					: __( 'Use the search box to load Google results, then add the places you want to turn into trip waypoints.', 'plan-your-day' ),
			];
		}

		if ( $planner_state['has_search'] && ! $planner_state['has_trip'] ) {
			return [
				'heading' => __( 'Search preview unavailable', 'plan-your-day' ),
				'body'    => __( 'The on-page map preview needs a valid Google Maps Embed API key. The Google Maps search link still works.', 'plan-your-day' ),
			];
		}

		return [
			'heading' => __( 'Trip preview unavailable', 'plan-your-day' ),
			'body'    => __( 'The on-page trip preview needs a valid Google Maps Embed API key. The Google Maps handoff link still works.', 'plan-your-day' ),
		];
	}
}
