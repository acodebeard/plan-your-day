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
		parent::setUp();

		$GLOBALS['plan_your_day_test_options']         = [];
		$GLOBALS['plan_your_day_test_object_cache']    = [];
		$GLOBALS['plan_your_day_test_transients']      = [];
		$GLOBALS['plan_your_day_use_ext_object_cache'] = false;
		$this->install_database_lock_driver();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		parent::tearDown();
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

	public function test_enforce_counts_weighted_cost_against_the_limit(): void {
		$now     = 300.0;
		$limiter = $this->build_limiter( 5, $now );

		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ], 3 ) );

		$error = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ], 3 );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'plan_your_day_rate_limited', $error->get_error_code() );
	}

	public function test_enforce_uses_database_advisory_locks_without_writing_lock_options(): void {
		$now     = 200.0;
		$limiter = $this->build_limiter( 2, $now );
		$key     = hash( 'sha256', 'browse|198.51.100.10' );

		$result = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertNull( $result );
		self::assertSame( 1, $GLOBALS['wpdb']->get_lock_calls );
		self::assertSame( 1, $GLOBALS['wpdb']->release_lock_calls );
		self::assertSame(
			'plan_your_day_rate_lock_' . substr( $key, 0, 40 ),
			$GLOBALS['wpdb']->queries[0]['args'][0] ?? null
		);
		self::assertFalse(
			array_key_exists(
				'plan_your_day_rate_lock_' . $key,
				$GLOBALS['plan_your_day_test_options']
			)
		);
	}

	public function test_enforce_returns_rate_limited_error_when_database_lock_cannot_be_acquired(): void {
		$GLOBALS['wpdb'] = $this->install_database_lock_driver( [ 0, 0, 0, 0, 0 ] );

		$now     = 1000.0;
		$limiter = $this->build_limiter( 2, $now );
		$error   = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'plan_your_day_rate_limited', $error->get_error_code() );
		self::assertSame( 5, $GLOBALS['wpdb']->get_lock_calls );
		self::assertSame( 0, $GLOBALS['wpdb']->release_lock_calls );
	}

	public function test_enforce_uses_external_object_cache_without_writing_rate_state_options(): void {
		$GLOBALS['plan_your_day_use_ext_object_cache'] = true;

		$now     = 1000.0;
		$limiter = $this->build_limiter( 2, $now );
		$key     = hash( 'sha256', 'browse|198.51.100.10' );

		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );
		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );

		$error = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertArrayHasKey( 'plan-your-day', $GLOBALS['plan_your_day_test_object_cache'] );
		self::assertArrayHasKey(
			'plan_your_day_rate_' . $key,
			$GLOBALS['plan_your_day_test_object_cache']['plan-your-day']
		);
		self::assertArrayNotHasKey(
			'plan_your_day_rate_lock_' . $key,
			$GLOBALS['plan_your_day_test_object_cache']['plan-your-day']
		);
		self::assertFalse(
			array_key_exists(
				'plan_your_day_rate_' . $key,
				$GLOBALS['plan_your_day_test_options']
			)
		);
	}

	public function test_enforce_uses_transient_storage_without_writing_rate_state_options(): void {
		$now     = 1000.0;
		$limiter = $this->build_limiter( 2, $now );
		$key     = hash( 'sha256', 'browse|198.51.100.10' );

		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );
		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );

		$error = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame(
			[ $now, $now ],
			$GLOBALS['plan_your_day_test_transients'][ 'plan_your_day_rate_' . $key ] ?? null
		);
		self::assertFalse(
			array_key_exists(
				'plan_your_day_rate_' . $key,
				$GLOBALS['plan_your_day_test_options']
			)
		);
	}

	public function test_enforce_reads_existing_transient_rate_state(): void {
		$now     = 1000.0;
		$key     = hash( 'sha256', 'browse|198.51.100.10' );
		$limiter = $this->build_limiter( 2, $now );

		set_transient( 'plan_your_day_rate_' . $key, [ 980.0 ], 60 );

		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );

		$error = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'plan_your_day_rate_limited', $error->get_error_code() );
		self::assertSame(
			[ 980.0, $now ],
			$GLOBALS['plan_your_day_test_transients'][ 'plan_your_day_rate_' . $key ] ?? null
		);
	}

	public function test_enforce_migrates_legacy_rate_state_option_to_transient_storage(): void {
		$now = 1000.0;
		$key = hash( 'sha256', 'browse|198.51.100.10' );

		update_option( 'plan_your_day_rate_' . $key, [ 980.0 ], false );

		$limiter = $this->build_limiter( 2, $now );

		self::assertNull( $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] ) );
		self::assertSame(
			[ 980.0, $now ],
			$GLOBALS['plan_your_day_test_transients'][ 'plan_your_day_rate_' . $key ] ?? null
		);
		self::assertFalse(
			array_key_exists(
				'plan_your_day_rate_' . $key,
				$GLOBALS['plan_your_day_test_options']
			)
		);
	}

	public function test_enforce_rejects_when_external_object_cache_lock_is_already_held(): void {
		$GLOBALS['plan_your_day_use_ext_object_cache'] = true;

		$now = 200.0;
		$key = hash( 'sha256', 'browse|198.51.100.10' );
		$GLOBALS['plan_your_day_test_object_cache']['plan-your-day'][ 'plan_your_day_rate_lock_' . $key ] = $now + 5.0;

		$limiter = $this->build_limiter( 2, $now );
		$error   = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'plan_your_day_rate_limited', $error->get_error_code() );
	}

	public function test_enforce_falls_back_to_option_lock_when_database_advisory_locks_are_unavailable(): void {
		unset( $GLOBALS['wpdb'] );

		$now = 200.0;
		update_option(
			'plan_your_day_rate_lock_' . hash( 'sha256', 'browse|198.51.100.10' ),
			150.0
		);
		$limiter = $this->build_limiter( 2, $now );
		$result  = $limiter->enforce( 'browse', [ 'REMOTE_ADDR' => '198.51.100.10' ] );

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

	private function install_database_lock_driver( array $get_lock_results = [], array $release_lock_results = [] ): object {
		$GLOBALS['wpdb'] = new class( $get_lock_results, $release_lock_results ) {
			public array $queries = [];
			public int $get_lock_calls = 0;
			public int $release_lock_calls = 0;

			private array $get_lock_results;
			private array $release_lock_results;

			public function __construct( array $get_lock_results, array $release_lock_results ) {
				$this->get_lock_results     = $get_lock_results;
				$this->release_lock_results = $release_lock_results;
			}

			public function prepare( string $query, mixed ...$args ): string {
				return (string) json_encode(
					[
						'query' => $query,
						'args'  => $args,
					]
				);
			}

			public function get_var( string $prepared_query ): mixed {
				$payload = json_decode( $prepared_query, true );
				$query   = is_array( $payload ) ? (string) ( $payload['query'] ?? '' ) : $prepared_query;
				$args    = is_array( $payload ) ? (array) ( $payload['args'] ?? [] ) : [];

				$this->queries[] = [
					'query' => $query,
					'args'  => $args,
				];

				if ( str_contains( $query, 'GET_LOCK' ) ) {
					++$this->get_lock_calls;

					return array_shift( $this->get_lock_results ) ?? 1;
				}

				if ( str_contains( $query, 'RELEASE_LOCK' ) ) {
					++$this->release_lock_calls;

					return array_shift( $this->release_lock_results ) ?? 1;
				}

				return null;
			}
		};

		return $GLOBALS['wpdb'];
	}
}
