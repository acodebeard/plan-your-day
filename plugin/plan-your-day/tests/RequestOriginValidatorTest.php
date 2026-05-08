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

	public function test_rejects_headerless_request_when_host_is_present(): void {
		$validator = new RequestOriginValidator();

		self::assertFalse(
			$validator->is_same_site_request(
				[
					'HTTP_HOST' => 'example.com',
				]
			)
		);
	}

	public function test_rejects_request_when_host_is_missing(): void {
		$validator = new RequestOriginValidator();

		self::assertFalse(
			$validator->is_same_site_request(
				[
					'HTTP_ORIGIN' => 'https://example.com',
				]
			)
		);
	}

	public function test_rejects_request_when_host_is_unparseable(): void {
		$validator = new RequestOriginValidator();

		self::assertFalse(
			$validator->is_same_site_request(
				[
					'HTTP_HOST'   => ':bad',
					'HTTP_ORIGIN' => 'https://example.com',
				]
			)
		);
	}

	public function test_allows_same_origin_fetch_metadata_without_origin_or_referer(): void {
		$validator = new RequestOriginValidator();

		self::assertTrue(
			$validator->is_same_site_request(
				[
					'HTTP_HOST'           => 'example.com',
					'HTTP_SEC_FETCH_SITE' => 'same-origin',
				]
			)
		);
	}

	public function test_allows_sec_fetch_site_none_for_top_level_navigation_without_origin(): void {
		$validator = new RequestOriginValidator();

		self::assertTrue(
			$validator->is_same_site_request(
				[
					'HTTP_HOST'           => 'example.com',
					'HTTP_SEC_FETCH_SITE' => 'none',
					'HTTP_SEC_FETCH_MODE' => 'navigate',
					'HTTP_SEC_FETCH_DEST' => 'document',
					'HTTP_SEC_FETCH_USER' => '?1',
				]
			)
		);
	}

	public function test_rejects_sec_fetch_site_none_when_origin_is_present(): void {
		$validator = new RequestOriginValidator();

		self::assertFalse(
			$validator->is_same_site_request(
				[
					'HTTP_HOST'           => 'example.com',
					'HTTP_ORIGIN'         => 'https://example.com',
					'HTTP_SEC_FETCH_SITE' => 'none',
					'HTTP_SEC_FETCH_MODE' => 'navigate',
					'HTTP_SEC_FETCH_DEST' => 'document',
					'HTTP_SEC_FETCH_USER' => '?1',
				]
			)
		);
	}
}
