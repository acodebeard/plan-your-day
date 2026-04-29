<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Security;

use Acodebeard\PlanYourDay\Settings\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {
	private const WINDOW_SECONDS = 60;
	private const CACHE_GROUP = 'plan-your-day';
	private const STATE_OPTION_PREFIX = 'plan_your_day_rate_';
	private const LOCK_OPTION_PREFIX = 'plan_your_day_rate_lock_';
	private const LOCK_TIMEOUT_SECONDS = 5.0;
	private const LOCK_RETRY_ATTEMPTS = 5;
	private const LOCK_RETRY_DELAY_MICROSECONDS = 20000;

	private Settings $settings;
	private ClientIpResolver $client_ip_resolver;
	/** @var callable */
	private $time_provider;

	public function __construct( Settings $settings, ClientIpResolver $client_ip_resolver, ?callable $time_provider = null ) {
		$this->settings           = $settings;
		$this->client_ip_resolver = $client_ip_resolver;
		$this->time_provider      = $time_provider ?? static fn (): float => microtime( true );
	}

	public function enforce( string $scope, array $server = [], int $cost = 1 ): ?WP_Error {
		$limit = max( 1, $this->settings->get_rate_limit_per_minute() );
		$ip    = $this->client_ip_resolver->resolve( $server );
		$cost  = max( 1, $cost );

		if ( '' === $ip ) {
			$ip = 'unknown';
		}

		$key = hash( 'sha256', $scope . '|' . $ip );
		$now = $this->now();

		if ( ! $this->acquire_lock( $key, $now ) ) {
			return $this->rate_limited_error();
		}

		try {
			$window_start = $now - self::WINDOW_SECONDS;
			$timestamps   = array_values(
				array_filter(
					$this->load_state( $key ),
					static function ( mixed $timestamp ) use ( $window_start ): bool {
						return is_numeric( $timestamp ) && (float) $timestamp > $window_start;
					}
				)
			);

			if ( count( $timestamps ) + $cost > $limit ) {
				$this->persist_state( $key, $timestamps );

				return $this->rate_limited_error();
			}

			$timestamps = array_merge( $timestamps, array_fill( 0, $cost, $now ) );
			$this->persist_state( $key, $timestamps );
		} finally {
			$this->release_lock( $key );
		}

		return null;
	}

	private function now(): float {
		return (float) call_user_func( $this->time_provider );
	}

	private function state_option_name( string $key ): string {
		return self::STATE_OPTION_PREFIX . $key;
	}

	private function lock_option_name( string $key ): string {
		return self::LOCK_OPTION_PREFIX . $key;
	}

	private function load_state( string $key ): array {
		if ( $this->uses_external_object_cache() ) {
			$state = wp_cache_get( $this->state_option_name( $key ), self::CACHE_GROUP );

			return is_array( $state ) ? $state : [];
		}

		$state = get_option( $this->state_option_name( $key ), [] );

		return is_array( $state ) ? $state : [];
	}

	private function persist_state( string $key, array $timestamps ): void {
		$storage_key = $this->state_option_name( $key );
		$timestamps  = array_values(
			array_map(
				static function ( mixed $timestamp ): float {
					return (float) $timestamp;
				},
				$timestamps
			)
		);

		if ( [] === $timestamps ) {
			if ( $this->uses_external_object_cache() ) {
				wp_cache_delete( $storage_key, self::CACHE_GROUP );
				return;
			}

			delete_option( $storage_key );
			return;
		}

		if ( $this->uses_external_object_cache() ) {
			wp_cache_set( $storage_key, $timestamps, self::CACHE_GROUP, self::WINDOW_SECONDS );
			return;
		}

		update_option( $storage_key, $timestamps, false );
	}

	private function acquire_lock( string $key, float $now ): bool {
		$option_name = $this->lock_option_name( $key );

		for ( $attempt = 0; $attempt < self::LOCK_RETRY_ATTEMPTS; $attempt++ ) {
			if ( add_option( $option_name, $now + self::LOCK_TIMEOUT_SECONDS, '', false ) ) {
				return true;
			}

			$locked_until = get_option( $option_name, 0 );

			if ( is_numeric( $locked_until ) && (float) $locked_until <= $now ) {
				delete_option( $option_name );
				continue;
			}

			if ( $attempt < self::LOCK_RETRY_ATTEMPTS - 1 ) {
				usleep( self::LOCK_RETRY_DELAY_MICROSECONDS );
				$now = $this->now();
			}
		}

		return false;
	}

	private function release_lock( string $key ): void {
		delete_option( $this->lock_option_name( $key ) );
	}

	private function uses_external_object_cache(): bool {
		return function_exists( 'wp_using_ext_object_cache' )
			&& function_exists( 'wp_cache_get' )
			&& function_exists( 'wp_cache_set' )
			&& function_exists( 'wp_cache_delete' )
			&& wp_using_ext_object_cache();
	}

	private function rate_limited_error(): WP_Error {
		return new WP_Error(
			'plan_your_day_rate_limited',
			__( 'Planner requests are temporarily limited. Please wait a minute and try again.', 'plan-your-day' ),
			[
				'status' => 429,
			]
		);
	}
}
