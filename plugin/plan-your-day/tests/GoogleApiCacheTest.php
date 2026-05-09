<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Google\GoogleApiCache;
use Acodebeard\PlanYourDay\Google\GoogleApiResult;
use PHPUnit\Framework\TestCase;

final class GoogleApiCacheTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['plan_your_day_test_options']    = [];
		$GLOBALS['plan_your_day_test_transients'] = [];
	}

	public function test_clear_for_scope_only_removes_matching_entries(): void {
		$cache  = new GoogleApiCache();
		$result = GoogleApiResult::success( [ 'ok' => true ] );

		$text_search_key = $cache->build_key( 'text_search', [ 'query' => 'coffee' ], 'places-key' );
		$place_key       = $cache->build_key( 'place_details', [ 'place_id' => 'place-1' ], 'places-key' );
		$geocode_key     = $cache->build_key( 'geocode', [ 'address' => '123 Main St' ], 'geocode-key' );

		$cache->set( $text_search_key, $result, 60, 'text_search' );
		$cache->set( $place_key, $result, 60, 'place_details', 'place-1' );
		$cache->set( $geocode_key, $result, 60, 'geocode' );

		self::assertSame( 1, $cache->clear_for_scope( 'text_search' ) );
		self::assertArrayNotHasKey( $text_search_key, $GLOBALS['plan_your_day_test_transients'] );
		self::assertArrayHasKey( $place_key, $GLOBALS['plan_your_day_test_transients'] );
		self::assertArrayHasKey( $geocode_key, $GLOBALS['plan_your_day_test_transients'] );
	}

	public function test_clear_for_place_only_removes_matching_place_details_entries(): void {
		$cache  = new GoogleApiCache();
		$result = GoogleApiResult::success( [ 'ok' => true ] );

		$place_one_key = $cache->build_key( 'place_details', [ 'place_id' => 'place-1' ], 'places-key' );
		$place_two_key = $cache->build_key( 'place_details', [ 'place_id' => 'place-2' ], 'places-key' );
		$text_key      = $cache->build_key( 'text_search', [ 'query' => 'coffee' ], 'places-key' );

		$cache->set( $place_one_key, $result, 60, 'place_details', 'place-1' );
		$cache->set( $place_two_key, $result, 60, 'place_details', 'place-2' );
		$cache->set( $text_key, $result, 60, 'text_search' );

		self::assertSame( 1, $cache->clear_for_place( 'place-1' ) );
		self::assertArrayNotHasKey( $place_one_key, $GLOBALS['plan_your_day_test_transients'] );
		self::assertArrayHasKey( $place_two_key, $GLOBALS['plan_your_day_test_transients'] );
		self::assertArrayHasKey( $text_key, $GLOBALS['plan_your_day_test_transients'] );
	}

	public function test_set_prunes_tracked_entries_for_missing_transients_before_appending(): void {
		$cache  = new GoogleApiCache();
		$result = GoogleApiResult::success( [ 'ok' => true ] );

		$stale_key = $cache->build_key( 'text_search', [ 'query' => 'stale' ], 'places-key' );
		$live_key  = $cache->build_key( 'text_search', [ 'query' => 'fresh' ], 'places-key' );

		update_option(
			'plan_your_day_google_cache_keys',
			[
				[
					'cache_key' => $stale_key,
					'scope'     => 'text_search',
					'place_id'  => '',
				],
			]
		);

		$cache->set( $live_key, $result, 60, 'text_search' );

		self::assertSame(
			[
				[
					'cache_key' => $live_key,
					'scope'     => 'text_search',
					'place_id'  => '',
				],
			],
			get_option( 'plan_your_day_google_cache_keys', [] )
		);
	}

	public function test_get_prunes_missing_tracked_entries_when_transient_has_expired(): void {
		$cache     = new GoogleApiCache();
		$stale_key = $cache->build_key( 'text_search', [ 'query' => 'stale' ], 'places-key' );

		update_option(
			'plan_your_day_google_cache_keys',
			[
				[
					'cache_key' => $stale_key,
					'scope'     => 'text_search',
					'place_id'  => '',
				],
			]
		);

		self::assertNull( $cache->get( $stale_key ) );
		self::assertSame( [], get_option( 'plan_your_day_google_cache_keys', [] ) );
	}
}
