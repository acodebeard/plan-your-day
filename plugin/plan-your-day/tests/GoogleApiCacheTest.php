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
}
