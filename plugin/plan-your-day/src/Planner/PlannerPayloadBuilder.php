<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

defined( 'ABSPATH' ) || exit;

final class PlannerPayloadBuilder {
	public function build_browse_payload( array $planner_state ): array {
		return [
			'categoryKey'        => $planner_state['category_key'],
			'categoryLabel'      => $planner_state['has_category'] ? $planner_state['category']['label'] : __( 'Not selected', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'hasCategory'        => $planner_state['has_category'],
			'hasCategories'      => $planner_state['has_categories'],
			'searchResults'      => array_values( $planner_state['search_results'] ),
			'searchResultsLabel' => $planner_state['search_results_label'],
			'resultsEmptyState'  => $this->get_empty_results_state( $planner_state ),
			'messages'           => array_values( $planner_state['messages'] ),
		];
	}

	public function build_route_payload( array $planner_state ): array {
		return [
			'categoryKey'         => $planner_state['category_key'],
			'categoryLabel'       => $planner_state['has_category'] ? $planner_state['category']['label'] : __( 'Not selected', PLAN_YOUR_DAY_TEXT_DOMAIN ),
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
				'heading' => __( 'Google results unavailable', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'body'    => __( 'Google place results are unavailable right now. Try again later or open the Google Maps handoff link.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			];
		}

		if ( ! $planner_state['has_categories'] ) {
			return [
				'heading' => __( 'No categories available', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'body'    => __( 'Add custom categories in Plan Your Day settings or enable the preset category fallback.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			];
		}

		if ( ! $planner_state['has_category'] ) {
			return [
				'heading' => __( 'Pick a category to search Google', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'body'    => __( 'Choose a category to load real place results.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			];
		}

		return [
			'heading' => __( 'No matching Google results', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'body'    => __( 'Try a different category or change the starting area.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
		];
	}

	public function get_empty_preview_state( array $planner_state ): array {
		if ( ! $planner_state['has_categories'] && ! $planner_state['has_trip'] ) {
			return [
				'heading' => __( 'No categories available', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'body'    => __( 'Add custom categories in settings or enable the preset category fallback before browsing places.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			];
		}

		if ( ! $planner_state['has_category'] && ! $planner_state['has_trip'] ) {
			return [
				'heading' => __( 'Start with a category search', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'body'    => __( 'Choose a category to load Google results, then add the places you want to turn into trip waypoints.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			];
		}

		if ( $planner_state['has_category'] && ! $planner_state['has_trip'] ) {
			return [
				'heading' => __( 'Search preview unavailable', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'body'    => __( 'The on-page map preview needs a valid Google Maps Embed API key. The Google Maps search link still works.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			];
		}

		return [
			'heading' => __( 'Trip preview unavailable', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'body'    => __( 'The on-page trip preview needs a valid Google Maps Embed API key. The Google Maps handoff link still works.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
		];
	}
}
