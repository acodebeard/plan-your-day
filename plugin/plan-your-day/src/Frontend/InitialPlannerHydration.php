<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

use Acodebeard\PlanYourDay\Planner\PlaceParser;

defined( 'ABSPATH' ) || exit;

final class InitialPlannerHydration {
	public static function build_render_state_options(): array {
		return [
			'include_results'        => false,
			'include_trip_waypoints' => false,
		];
	}

	public static function should_hydrate_on_load( array $request_state ): bool {
		$category_key      = sanitize_key( (string) ( $request_state['category_key'] ?? '' ) );
		$category_search   = trim( sanitize_text_field( (string) ( $request_state['category_search'] ?? '' ) ) );
		$selected_waypoints = array_filter(
			array_map(
				static function ( mixed $waypoint_id ): string {
					return PlaceParser::sanitize_place_id( (string) $waypoint_id );
				},
				(array) ( $request_state['selected_waypoint_ids'] ?? [] )
			)
		);

		return '' !== $category_key || '' !== $category_search || [] !== $selected_waypoints;
	}

	public static function apply_loading_placeholders( array $planner_state ): array {
		$has_search             = ! empty( $planner_state['has_search'] );
		$has_selected_waypoints = [] !== array_filter(
			array_map(
				static function ( mixed $waypoint_id ): string {
					return PlaceParser::sanitize_place_id( (string) $waypoint_id );
				},
				(array) ( $planner_state['selected_waypoint_ids'] ?? [] )
			)
		);
		$messages               = is_array( $planner_state['messages'] ?? null ) ? array_values( $planner_state['messages'] ) : [];

		$messages[] = [
			'type' => 'note',
			'text' => __( 'Loading planner state through a verified request.', 'plan-your-day' ),
		];

		$planner_state['messages'] = $messages;

		if ( $has_search ) {
			$planner_state['search_results_label'] = __( 'Loading Google results...', 'plan-your-day' );
			$planner_state['results_empty_state']  = [
				'heading' => __( 'Loading Google results', 'plan-your-day' ),
				'body'    => __( 'The planner is loading your saved search through the secure request path.', 'plan-your-day' ),
			];
			$planner_state['overview']             = __( 'The planner is loading your saved search through the secure request path.', 'plan-your-day' );
		}

		if ( $has_selected_waypoints ) {
			$planner_state['trip_count_label'] = __( 'Loading trip waypoints...', 'plan-your-day' );
			$planner_state['trip_empty_state'] = [
				'heading' => __( 'Loading trip waypoints', 'plan-your-day' ),
				'body'    => __( 'The planner is loading your saved trip through the secure request path.', 'plan-your-day' ),
			];
			$planner_state['overview']         = __( 'The planner is loading your saved trip through the secure request path.', 'plan-your-day' );
		}

		if ( $has_selected_waypoints ) {
			$planner_state['preview_mode_label']  = __( 'Loading trip preview', 'plan-your-day' );
			$planner_state['preview_empty_state'] = [
				'heading' => __( 'Loading trip preview', 'plan-your-day' ),
				'body'    => __( 'The planner is loading your saved trip through the secure request path.', 'plan-your-day' ),
			];
		} elseif ( $has_search ) {
			$planner_state['preview_mode_label']  = __( 'Loading search preview', 'plan-your-day' );
			$planner_state['preview_empty_state'] = [
				'heading' => __( 'Loading search preview', 'plan-your-day' ),
				'body'    => __( 'The planner is loading your saved search through the secure request path.', 'plan-your-day' ),
			];
		}

		return $planner_state;
	}
}
