<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Rest\PlannerRoutes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PlannerRoutesTest extends TestCase {
	public function test_sanitize_waypoints_keeps_only_scalar_values_as_strings(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertSame(
			[ 'place-1', '42', '', '1' ],
			$routes->sanitize_waypoints( [ 'place-1', 42, [ 'bad' ], true ] )
		);
	}

	public function test_sanitize_boolean_handles_common_truthy_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->sanitize_boolean( '1' ) );
		self::assertTrue( $routes->sanitize_boolean( 1 ) );
		self::assertFalse( $routes->sanitize_boolean( '0' ) );
		self::assertFalse( $routes->sanitize_boolean( false ) );
	}

	public function test_validate_loose_scalar_accepts_scalar_and_null_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->validate_loose_scalar( 'coffee' ) );
		self::assertTrue( $routes->validate_loose_scalar( 1 ) );
		self::assertTrue( $routes->validate_loose_scalar( null ) );
		self::assertFalse( $routes->validate_loose_scalar( [ 'coffee' ] ) );
	}

	public function test_validate_loose_boolean_accepts_common_boolean_wire_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->validate_loose_boolean( true ) );
		self::assertTrue( $routes->validate_loose_boolean( '0' ) );
		self::assertTrue( $routes->validate_loose_boolean( null ) );
		self::assertFalse( $routes->validate_loose_boolean( [ true ] ) );
	}

	public function test_validate_loose_waypoints_accepts_scalar_arrays_and_rejects_nested_values(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();

		self::assertTrue( $routes->validate_loose_waypoints( [ 'place-1', 'place-2' ] ) );
		self::assertTrue( $routes->validate_loose_waypoints( 'place-1' ) );
		self::assertTrue( $routes->validate_loose_waypoints( null ) );
		self::assertFalse( $routes->validate_loose_waypoints( [ 'place-1', [ 'bad' ] ] ) );
	}

	public function test_get_rate_limit_cost_weights_browse_search_and_trip_waypoints(): void {
		$routes = ( new ReflectionClass( PlannerRoutes::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( PlannerRoutes::class, 'get_rate_limit_cost' );
		$method->setAccessible( true );

		self::assertSame(
			5,
			$method->invoke(
				$routes,
				'browse',
				[
					'category_key'          => 'coffee',
					'category_search'       => '',
					'selected_waypoint_ids' => [ 'place-1', 'place-2' ],
				]
			)
		);
		self::assertSame(
			3,
			$method->invoke(
				$routes,
				'route',
				[
					'category_key'          => 'coffee',
					'category_search'       => '',
					'selected_waypoint_ids' => [ 'place-1', 'place-2' ],
				]
			)
		);
		self::assertSame(
			1,
			$method->invoke(
				$routes,
				'browse',
				[
					'category_key'          => '',
					'category_search'       => '',
					'selected_waypoint_ids' => [],
				]
			)
		);
	}
}
