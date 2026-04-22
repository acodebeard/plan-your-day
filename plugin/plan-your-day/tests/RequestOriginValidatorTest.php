<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use PHPUnit\Framework\TestCase;

final class RequestOriginValidatorTest extends TestCase {
	public function test_allows_same_host_origin_and_referer(): void {
		$validator = new RequestOriginValidator();

		self::assertTrue(
			$validator->is_same_site_request(
				[
					'HTTP_HOST'    => 'example.com',
					'HTTP_ORIGIN'  => 'https://example.com',
					'HTTP_REFERER' => 'https://example.com/trips',
				]
			)
		);
	}

	public function test_rejects_cross_site_fetch_without_navigation_context(): void {
		$validator = new RequestOriginValidator();

		self::assertFalse(
			$validator->is_same_site_request(
				[
					'HTTP_HOST'           => 'example.com',
					'HTTP_SEC_FETCH_SITE' => 'cross-site',
					'HTTP_SEC_FETCH_MODE' => 'cors',
					'HTTP_SEC_FETCH_DEST' => 'empty',
				]
			)
		);
	}
}
