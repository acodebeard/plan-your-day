<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Security\ClientIpResolver;
use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class ClientIpResolverTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['plan_your_day_test_options'] = [];
	}

	public function test_returns_remote_address_when_proxy_is_not_trusted(): void {
		update_option( Settings::OPTION_NAME, [ 'trusted_proxy_cidrs' => "10.0.0.0/8" ] );

		$resolver = new ClientIpResolver( new Settings() );

		self::assertSame(
			'198.51.100.25',
			$resolver->resolve(
				[
					'REMOTE_ADDR'           => '198.51.100.25',
					'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
				]
			)
		);
	}

	public function test_returns_last_non_proxy_forwarded_address_when_proxy_is_trusted(): void {
		update_option( Settings::OPTION_NAME, [ 'trusted_proxy_cidrs' => "10.0.0.0/8\n2001:db8::/32" ] );

		$resolver = new ClientIpResolver( new Settings() );

		self::assertSame(
			'198.51.100.10',
			$resolver->resolve(
				[
					'REMOTE_ADDR'           => '10.1.2.3',
					'HTTP_X_FORWARDED_FOR' => '198.51.100.10, 10.0.0.2',
				]
			)
		);
	}
}
