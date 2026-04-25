<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Frontend\InitialPlannerHydration;
use PHPUnit\Framework\TestCase;

final class InitialPlannerHydrationTest extends TestCase {
	public function test_build_render_state_options_disable_remote_get_work(): void {
		self::assertSame(
			[
				'include_results'        => false,
				'include_trip_waypoints' => false,
			],
			InitialPlannerHydration::build_render_state_options()
		);
	}

	public function test_should_hydrate_on_load_for_search_and_trip_state(): void {
		self::assertTrue(
			InitialPlannerHydration::should_hydrate_on_load(
				[
					'category_key'          => 'coffee',
					'category_search'       => '',
					'selected_waypoint_ids' => [],
				]
			)
		);
		self::assertTrue(
			InitialPlannerHydration::should_hydrate_on_load(
				[
					'category_key'          => '',
					'category_search'       => 'coffee',
					'selected_waypoint_ids' => [],
				]
			)
		);
		self::assertTrue(
			InitialPlannerHydration::should_hydrate_on_load(
				[
					'category_key'          => '',
					'category_search'       => '',
					'selected_waypoint_ids' => [ 'place-1' ],
				]
			)
		);
	}

	public function test_should_not_hydrate_on_load_for_start_only_state(): void {
		self::assertFalse(
			InitialPlannerHydration::should_hydrate_on_load(
				[
					'category_key'          => '',
					'category_search'       => '',
					'selected_waypoint_ids' => [],
					'start_mode'            => 'custom',
					'custom_start'          => 'Hotel',
				]
			)
		);
	}

	public function test_apply_loading_placeholders_sets_search_and_trip_loading_copy(): void {
		$planner_state = InitialPlannerHydration::apply_loading_placeholders(
			[
				'has_search'            => true,
				'selected_waypoint_ids' => [ 'place-1' ],
				'search_results_label'  => '0 Google results',
				'trip_count_label'      => 'Trip not started',
				'overview'              => 'Original overview',
				'preview_mode_label'    => 'Google place search',
				'messages'              => [],
			]
		);

		self::assertSame( 'Loading Google results...', $planner_state['search_results_label'] );
		self::assertSame( 'Loading trip waypoints...', $planner_state['trip_count_label'] );
		self::assertSame( 'Loading Google results', $planner_state['results_empty_state']['heading'] );
		self::assertSame( 'Loading trip waypoints', $planner_state['trip_empty_state']['heading'] );
		self::assertSame( 'Loading trip preview', $planner_state['preview_empty_state']['heading'] );
		self::assertSame( 'Loading planner state through a verified request.', $planner_state['messages'][0]['text'] );
	}
}
