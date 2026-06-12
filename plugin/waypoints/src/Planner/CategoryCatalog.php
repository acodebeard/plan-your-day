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
		return $this->normalize_rows( $this->settings->get_categories() );
	}

	public static function default_rows(): array {
		return [
			[
				'slug'        => 'coffee',
				'label'       => __( 'Coffee', 'waypoints-trip-planner' ),
				'description' => __( 'Search for coffee shops, cafes, tastings, and easy morning stops.', 'waypoints-trip-planner' ),
				'text_query'  => __( 'Coffee near me', 'waypoints-trip-planner' ),
				'enabled'     => true,
				'sort_order'  => 10,
			],
			[
				'slug'        => 'food',
				'label'       => __( 'Food', 'waypoints-trip-planner' ),
				'description' => __( 'Search for restaurants, quick bites, and broader local food options.', 'waypoints-trip-planner' ),
				'text_query'  => __( 'Food near me', 'waypoints-trip-planner' ),
				'enabled'     => true,
				'sort_order'  => 20,
			],
			[
				'slug'        => 'shopping',
				'label'       => __( 'Shopping', 'waypoints-trip-planner' ),
				'description' => __( 'Search for boutiques, markets, and places to browse local goods.', 'waypoints-trip-planner' ),
				'text_query'  => __( 'Shopping near me', 'waypoints-trip-planner' ),
				'enabled'     => true,
				'sort_order'  => 30,
			],
			[
				'slug'        => 'outdoors',
				'label'       => __( 'Outdoors', 'waypoints-trip-planner' ),
				'description' => __( 'Search for parks, waterfront access, trails, and outdoor stops.', 'waypoints-trip-planner' ),
				'text_query'  => __( 'Outdoors near me', 'waypoints-trip-planner' ),
				'enabled'     => true,
				'sort_order'  => 40,
			],
			[
				'slug'        => 'history-culture',
				'label'       => __( 'History / culture', 'waypoints-trip-planner' ),
				'description' => __( 'Search for museums, landmarks, heritage sites, and cultural experiences.', 'waypoints-trip-planner' ),
				'text_query'  => __( 'History / culture near me', 'waypoints-trip-planner' ),
				'enabled'     => true,
				'sort_order'  => 50,
			],
			[
				'slug'        => 'scenic',
				'label'       => __( 'Scenic spots', 'waypoints-trip-planner' ),
				'description' => __( 'Search for viewpoints, waterfront stretches, and scenic lookouts.', 'waypoints-trip-planner' ),
				'text_query'  => __( 'Scenic spots near me', 'waypoints-trip-planner' ),
				'enabled'     => true,
				'sort_order'  => 60,
			],
		];
	}

	public static function preset_rows(): array {
		return self::default_rows();
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
