<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class CategoryCatalogTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['plan_your_day_test_options'] = [];
		$GLOBALS['plan_your_day_test_actions'] = [];
		$GLOBALS['plan_your_day_test_option_reads'] = [];
	}

	public function test_get_all_returns_empty_when_saved_list_is_empty(): void {
		update_option( Settings::OPTION_NAME, Settings::defaults() );

		$settings = new Settings();
		$catalog  = new CategoryCatalog( $settings );

		self::assertSame( [], $catalog->get_all() );
	}

	public function test_built_in_starter_categories_use_near_me_search_terms(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'categories' => Settings::default_categories(),
				]
			)
		);

		$settings = new Settings();
		$catalog  = new CategoryCatalog( $settings );

		self::assertSame(
			[
				'coffee'          => 'Coffee near me',
				'food'            => 'Food near me',
				'shopping'        => 'Shopping near me',
				'outdoors'        => 'Outdoors near me',
				'history-culture' => 'History / culture near me',
				'scenic'          => 'Scenic spots near me',
			],
			array_map(
				static fn ( array $category ): string => (string) $category['text_query'],
				$catalog->get_all()
			)
		);
	}

	public function test_get_all_returns_only_enabled_saved_categories_in_sort_order(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'categories' => Settings::sanitize_categories(
						[
							[
								'label'       => 'Dessert',
								'description' => 'Sweet stops',
								'text_query'  => 'dessert',
								'slug'        => 'dessert',
								'enabled'     => true,
								'sort_order'  => 30,
							],
							[
								'label'       => 'Tea',
								'description' => 'Tea houses',
								'text_query'  => 'tea houses',
								'slug'        => 'tea',
								'enabled'     => true,
								'sort_order'  => 10,
							],
							[
								'label'       => 'Hidden',
								'description' => 'Should not render',
								'text_query'  => 'hidden',
								'slug'        => 'hidden',
								'enabled'     => false,
								'sort_order'  => 20,
							],
						]
					),
				]
			)
		);

		$settings = new Settings();
		$catalog  = new CategoryCatalog( $settings );

		self::assertSame( [ 'tea', 'dessert' ], array_keys( $catalog->get_all() ) );
		self::assertSame( 'Tea', $catalog->get_all()['tea']['label'] ?? null );
		self::assertArrayNotHasKey( 'hidden', $catalog->get_all() );
	}

	public function test_get_all_returns_empty_when_saved_list_is_empty_even_if_legacy_fallback_is_enabled(): void {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'use_preset_categories' => true,
				]
			)
		);

		$settings = new Settings();
		$catalog  = new CategoryCatalog( $settings );

		self::assertSame( [], $catalog->get_all() );
	}
}
