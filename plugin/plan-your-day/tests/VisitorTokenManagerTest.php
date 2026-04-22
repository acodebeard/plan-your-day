<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Security\VisitorTokenManager;
use PHPUnit\Framework\TestCase;

final class VisitorTokenManagerTest extends TestCase {
	protected function setUp(): void {
		$_COOKIE = [];
	}

	public function test_validate_endpoint_token_accepts_matching_cookie_token(): void {
		$visitor_token = str_repeat( 'ab', 24 );
		$_COOKIE['plan_your_day_visitor'] = $visitor_token;

		$request_token = hash_hmac( 'sha256', $visitor_token, 'tests-auth|plan-your-day' );
		$manager       = new VisitorTokenManager();

		self::assertTrue( $manager->validate_endpoint_token( $request_token ) );
	}

	public function test_validate_endpoint_token_rejects_invalid_cookie_value(): void {
		$_COOKIE['plan_your_day_visitor'] = 'not-a-valid-token';

		$manager = new VisitorTokenManager();

		self::assertFalse( $manager->validate_endpoint_token( 'abc123' ) );
	}
}
