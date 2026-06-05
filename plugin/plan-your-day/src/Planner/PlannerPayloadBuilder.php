<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Frontend\InterfaceCopy;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class PlannerPayloadBuilder {
	private ?Settings $settings;

	public function __construct( ?Settings $settings = null ) {
		$this->settings = $settings;
	}

	public function build_browse_payload( array $planner_state ): array {
		return [
			'categoryKey'        => $planner_state['category_key'],
			'categorySearch'     => $planner_state['category_search'],
			'categoryLabel'      => $planner_state['has_search'] ? $planner_state['active_search_label'] : $this->copy_value( 'not_selected' ),
			'hasCategory'        => $planner_state['has_category'],
			'hasCategories'      => $planner_state['has_categories'],
			'hasSearch'          => $planner_state['has_search'],
			'isCustomSearch'     => $planner_state['is_custom_search'],
			'searchContextKey'   => $planner_state['search_context_key'],
			'searchResults'      => array_values( $planner_state['search_results'] ),
			'nextPageToken'      => $planner_state['next_page_token'],
			'hasMoreResults'     => $planner_state['has_more_results'],
			'searchResultsError' => $planner_state['search_results_error'],
			'searchResultsLabel' => $planner_state['search_results_label'],
			'resultsEmptyState'  => $this->get_empty_results_state( $planner_state ),
			'messages'           => array_values( $planner_state['messages'] ),
		];
	}

	public function build_route_payload( array $planner_state ): array {
		return [
			'categoryKey'         => $planner_state['category_key'],
			'categorySearch'      => $planner_state['category_search'],
			'categoryLabel'       => $planner_state['has_search'] ? $planner_state['active_search_label'] : $this->copy_value( 'not_selected' ),
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
					? __( 'Use the search box or choose a category to load real place results.', 'plan-your-day' )
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
				'heading' => $this->copy_value( 'preview_prompt_heading' ),
				'body'    => $planner_state['has_categories']
					? $this->copy_value( 'preview_prompt_body_with_categories' )
					: $this->copy_value( 'preview_prompt_body_no_categories' ),
			];
		}

		if ( $planner_state['has_search'] && ! $planner_state['has_trip'] ) {
			return [
				'heading' => $this->copy_value( 'search_preview_unavailable_heading' ),
				'body'    => $this->copy_value( 'search_preview_unavailable_body' ),
			];
		}

		return [
			'heading' => $this->copy_value( 'trip_preview_unavailable_heading' ),
			'body'    => $this->copy_value( 'trip_preview_unavailable_body' ),
		];
	}

	private function copy_value( string $key ): string {
		if ( $this->settings instanceof Settings ) {
			return $this->settings->get_frontend_copy_value( $key );
		}

		return InterfaceCopy::default_value( $key );
	}
}
