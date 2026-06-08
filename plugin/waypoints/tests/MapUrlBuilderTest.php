<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Planner\MapUrlBuilder;
use PHPUnit\Framework\TestCase;

final class MapUrlBuilderTest extends TestCase {
	public function test_build_search_query_does_not_append_area_to_near_me_terms(): void {
		$builder = new MapUrlBuilder();

		self::assertSame( 'Coffee near me', $builder->build_search_query( 'Coffee near me', 'Test Harbor' ) );
		self::assertSame( 'Coffee near me', $builder->build_search_query( 'Coffee near me', 'Test Harbor', true ) );
		self::assertSame( 'Coffee near Test Harbor', $builder->build_search_query( 'Coffee', 'Test Harbor' ) );
	}
}
