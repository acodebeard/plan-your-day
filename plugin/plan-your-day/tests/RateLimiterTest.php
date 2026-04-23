<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Security\ClientIpResolver;
use Acodebeard\PlanYourDay\Security\RateLimiter;
use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class RateLimiterTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['plan_your_day_test_options'] = [];
	}

	public function test_enforce_allows_requests_until_the_limit_then_blocks(): void {
		$now     = 1000.0;
		$limiter = $this->build_limiter( 2, $now );

		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );
		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );

		$error = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'plan_your_day_rate_limited', $error->get_error_code() );
		self::assertSame( 429, $error->get_error_data()['status'] ?? null );
	}

	public function test_enforce_uses_a_rolling_window_instead_of_a_fixed_bucket_boundary(): void {
		$now     = 59.8;
		$limiter = $this->build_limiter( 2, $now );

		self::assertNull( $limiter->enforce( 'route', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );

		$now = 59.9;
		self::assertNull( $limiter->enforce( 'route', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );

		$now   = 60.1;
		$error = $limiter->enforce( 'route', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'plan_your_day_rate_limited', $error->get_error_code() );
	}

	public function test_enforce_recovers_from_a_stale_lock(): void {
		$now = 200.0;
		update_option(
			'plan_your_day_rate_lock_' . hash( 'sha256', 'browse|198.51.100.10' ),
			150.0
		);
		$limiter = $this->build_limiter( 2, $now );

		$result = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertNull( $result );
		self::assertFalse(
			array_key_exists(
				'plan_your_day_rate_lock_' . hash( 'sha256', 'browse|198.51.100.10' ),
				$GLOBALS['plan_your_day_test_options']
			)
		);
	}

	private function build_limiter( int $limit, float &$now ): RateLimiter {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::defaults(),
				[
					'rate_limit_per_minute' => $limit,
				]
			)
		);

		return new RateLimiter(
			new Settings(),
			new ClientIpResolver( new Settings() ),
			static function () use ( &$now ): float {
				return $now;
			}
		);
	}
}
