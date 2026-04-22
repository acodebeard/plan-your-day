<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Security;

use Acodebeard\PlanYourDay\Settings\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class RateLimiter {
	private const WINDOW_SECONDS = 60;
	private const CACHE_DIR_NAME = 'plan_your_day_rate';

	private Settings $settings;
	private ClientIpResolver $client_ip_resolver;

	public function __construct( Settings $settings, ClientIpResolver $client_ip_resolver ) {
		$this->settings           = $settings;
		$this->client_ip_resolver = $client_ip_resolver;
	}

	public function enforce( string $scope, array $server = [] ): ?WP_Error {
		$limit = max( 1, $this->settings->get_rate_limit_per_minute() );
		$ip    = $this->client_ip_resolver->resolve( $server );

		if ( '' === $ip ) {
			$ip = 'unknown';
		}

		$bucket    = (int) floor( time() / self::WINDOW_SECONDS );
		$cache_dir = trailingslashit( sys_get_temp_dir() ) . self::CACHE_DIR_NAME;

		if ( ! is_dir( $cache_dir ) && ! @mkdir( $cache_dir, 0700, true ) && ! is_dir( $cache_dir ) ) {
			error_log( sprintf( '[plan-your-day] rate limiter degraded: unable to create %s', $cache_dir ) );

			return null;
		}

		$key       = hash( 'sha256', $scope . '|' . $ip . '|' . $bucket );
		$file_path = $cache_dir . '/' . $key;
		$handle    = @fopen( $file_path, 'c+' );
		$count     = 0;

		if ( false === $handle ) {
			error_log( sprintf( '[plan-your-day] rate limiter degraded: unable to open %s', $file_path ) );

			return null;
		}

		if ( ! flock( $handle, LOCK_EX ) ) {
			fclose( $handle );
			error_log( sprintf( '[plan-your-day] rate limiter degraded: unable to lock %s', $file_path ) );

			return null;
		}

		$raw_count = stream_get_contents( $handle );
		$count     = is_string( $raw_count ) ? (int) $raw_count : 0;

		if ( $count >= $limit ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );

			return new WP_Error(
				'plan_your_day_rate_limited',
				__( 'Planner requests are temporarily limited. Please wait a minute and try again.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				[
					'status' => 429,
				]
			);
		}

		$count++;
		rewind( $handle );
		ftruncate( $handle, 0 );
		fwrite( $handle, (string) $count );
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle );

		if ( 0 === $count % 25 ) {
			$this->cleanup_old_files( $cache_dir );
		}

		return null;
	}

	private function cleanup_old_files( string $cache_dir ): void {
		$expiration = time() - ( self::WINDOW_SECONDS * 5 );

		foreach ( (array) glob( $cache_dir . '/*' ) as $file_path ) {
			if ( ! is_string( $file_path ) || ! is_file( $file_path ) ) {
				continue;
			}

			$file_mtime = @filemtime( $file_path );

			if ( false !== $file_mtime && $file_mtime < $expiration ) {
				@unlink( $file_path );
			}
		}
	}
}
