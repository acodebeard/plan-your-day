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

	public function test_apply_loading_placeholders_uses_fixed_search_loading_copy(): void {
		$planner_state = InitialPlannerHydration::apply_loading_placeholders(
			[
				'has_search'            => true,
				'selected_waypoint_ids' => [],
				'search_results_label'  => '',
				'overview'              => '',
				'preview_mode_label'    => '',
				'messages'              => [],
			],
			[
				'hydration_loading_message'       => 'Loading saved planner state.',
				'loading_results_label'           => 'Editable loading results label.',
				'loading_results_heading'         => 'Editable loading results heading',
				'loading_results_body'            => 'Editable loading results body.',
				'loading_search_preview_mode'     => 'Editable loading search preview mode',
				'loading_search_preview_heading'  => 'Editable loading search preview heading',
				'loading_search_preview_body'     => 'Editable loading search preview body.',
			]
		);

		self::assertSame( 'Loading Google results...', $planner_state['search_results_label'] );
		self::assertSame( 'Loading Google results', $planner_state['results_empty_state']['heading'] );
		self::assertSame(
			'The planner is loading your saved search through the secure request path.',
			$planner_state['results_empty_state']['body']
		);
		self::assertSame(
			'The planner is loading your saved search through the secure request path.',
			$planner_state['overview']
		);
		self::assertSame( 'Loading search preview', $planner_state['preview_mode_label'] );
		self::assertSame( 'Loading search preview', $planner_state['preview_empty_state']['heading'] );
		self::assertSame(
			'The planner is loading your saved search through the secure request path.',
			$planner_state['preview_empty_state']['body']
		);
		self::assertSame( 'Loading saved planner state.', $planner_state['messages'][0]['text'] );
	}

	public function test_apply_loading_placeholders_uses_fixed_trip_loading_copy(): void {
		$planner_state = InitialPlannerHydration::apply_loading_placeholders(
			[
				'has_search'            => false,
				'selected_waypoint_ids' => [ 'place-1' ],
				'trip_count_label'      => '',
				'overview'              => '',
				'preview_mode_label'    => '',
				'messages'              => [],
			],
			[
				'hydration_loading_message'          => 'Loading saved planner state.',
				'loading_trip_count'                 => 'Editable loading trip count.',
				'loading_trip_heading'               => 'Editable loading trip heading',
				'loading_trip_body'                  => 'Editable loading trip body.',
				'loading_trip_preview_mode'          => 'Editable loading trip preview mode',
				'loading_trip_preview_heading'       => 'Editable loading trip preview heading',
				'loading_trip_preview_body'          => 'Editable loading trip preview body.',
			]
		);

		self::assertSame( 'Loading trip waypoints...', $planner_state['trip_count_label'] );
		self::assertSame( 'Loading trip waypoints', $planner_state['trip_empty_state']['heading'] );
		self::assertSame(
			'The planner is loading your saved trip through the secure request path.',
			$planner_state['trip_empty_state']['body']
		);
		self::assertSame(
			'The planner is loading your saved trip through the secure request path.',
			$planner_state['overview']
		);
		self::assertSame( 'Loading trip preview', $planner_state['preview_mode_label'] );
		self::assertSame( 'Loading trip preview', $planner_state['preview_empty_state']['heading'] );
		self::assertSame(
			'The planner is loading your saved trip through the secure request path.',
			$planner_state['preview_empty_state']['body']
		);
		self::assertSame( 'Loading saved planner state.', $planner_state['messages'][0]['text'] );
	}
}
