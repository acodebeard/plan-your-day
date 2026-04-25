<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class CategoryCatalog {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_all(): array {
		$stored_categories = $this->normalize_rows( $this->settings->get_categories() );

		if ( [] !== $stored_categories ) {
			return $stored_categories;
		}

		if ( ! $this->settings->use_preset_categories() ) {
			return [];
		}

		return $this->normalize_rows( self::preset_rows() );
	}

	public static function preset_rows(): array {
		return [
			[
				'slug'        => 'coffee',
				'label'       => __( 'Coffee', 'plan-your-day' ),
				'description' => __( 'Search for coffee shops, cafes, tastings, and easy morning stops.', 'plan-your-day' ),
				'text_query'  => __( 'coffee shops and cafes', 'plan-your-day' ),
				'enabled'     => true,
				'sort_order'  => 10,
			],
			[
				'slug'        => 'food',
				'label'       => __( 'Food', 'plan-your-day' ),
				'description' => __( 'Search for restaurants, quick bites, and broader local food options.', 'plan-your-day' ),
				'text_query'  => __( 'restaurants and local food', 'plan-your-day' ),
				'enabled'     => true,
				'sort_order'  => 20,
			],
			[
				'slug'        => 'shopping',
				'label'       => __( 'Shopping', 'plan-your-day' ),
				'description' => __( 'Search for boutiques, markets, and places to browse local goods.', 'plan-your-day' ),
				'text_query'  => __( 'shopping and local boutiques', 'plan-your-day' ),
				'enabled'     => true,
				'sort_order'  => 30,
			],
			[
				'slug'        => 'outdoors',
				'label'       => __( 'Outdoors', 'plan-your-day' ),
				'description' => __( 'Search for parks, waterfront access, trails, and outdoor stops.', 'plan-your-day' ),
				'text_query'  => __( 'parks and outdoor activities', 'plan-your-day' ),
				'enabled'     => true,
				'sort_order'  => 40,
			],
			[
				'slug'        => 'history-culture',
				'label'       => __( 'History / culture', 'plan-your-day' ),
				'description' => __( 'Search for museums, landmarks, heritage sites, and cultural experiences.', 'plan-your-day' ),
				'text_query'  => __( 'history and culture', 'plan-your-day' ),
				'enabled'     => true,
				'sort_order'  => 50,
			],
			[
				'slug'        => 'scenic',
				'label'       => __( 'Scenic spots', 'plan-your-day' ),
				'description' => __( 'Search for viewpoints, waterfront stretches, and scenic lookouts.', 'plan-your-day' ),
				'text_query'  => __( 'scenic spots and viewpoints', 'plan-your-day' ),
				'enabled'     => true,
				'sort_order'  => 60,
			],
			[
				'slug'        => 'activities',
				'label'       => __( 'Other activities', 'plan-your-day' ),
				'description' => __( 'Search for tours, family-friendly attractions, and broader things to do.', 'plan-your-day' ),
				'text_query'  => __( 'tours and activities', 'plan-your-day' ),
				'enabled'     => true,
				'sort_order'  => 70,
			],
		];
	}

	private function normalize_rows( array $rows ): array {
		$catalog = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['enabled'] ) ) {
				continue;
			}

			$slug = sanitize_key( (string) ( $row['slug'] ?? '' ) );

			if ( '' === $slug ) {
				continue;
			}

			$catalog[ $slug ] = [
				'label'       => (string) ( $row['label'] ?? '' ),
				'description' => (string) ( $row['description'] ?? '' ),
				'text_query'  => (string) ( $row['text_query'] ?? '' ),
			];
		}

		return $catalog;
	}
}
