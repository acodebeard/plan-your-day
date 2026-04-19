<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

defined( 'ABSPATH' ) || exit;

final class CategoryCatalog {
	public function get_all(): array {
		return [
			'coffee' => [
				'label'       => __( 'Coffee', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'description' => __( 'Search for coffee shops, cafes, tastings, and easy morning stops.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'text_query'  => __( 'coffee shops and cafes', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
			'food' => [
				'label'       => __( 'Food', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'description' => __( 'Search for restaurants, quick bites, and broader local food options.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'text_query'  => __( 'restaurants and local food', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
			'shopping' => [
				'label'       => __( 'Shopping', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'description' => __( 'Search for boutiques, markets, and places to browse local goods.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'text_query'  => __( 'shopping and local boutiques', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
			'outdoors' => [
				'label'       => __( 'Outdoors', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'description' => __( 'Search for parks, waterfront access, trails, and outdoor stops.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'text_query'  => __( 'parks and outdoor activities', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
			'history-culture' => [
				'label'       => __( 'History / culture', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'description' => __( 'Search for museums, landmarks, heritage sites, and cultural experiences.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'text_query'  => __( 'history and culture', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
			'scenic' => [
				'label'       => __( 'Scenic spots', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'description' => __( 'Search for viewpoints, waterfront stretches, and scenic lookouts.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'text_query'  => __( 'scenic spots and viewpoints', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
			'activities' => [
				'label'       => __( 'Other activities', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'description' => __( 'Search for tours, family-friendly attractions, and broader things to do.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'text_query'  => __( 'tours and activities', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
		];
	}
}
