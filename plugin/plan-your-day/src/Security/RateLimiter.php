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
		$key       = hash( 'sha256', $scope . '|' . $ip . '|' . $bucket );
		$cache_key = self::CACHE_DIR_NAME . '_' . $key;
		$count     = (int) get_transient( $cache_key );

		if ( $count >= $limit ) {
			return new WP_Error(
				'plan_your_day_rate_limited',
				__( 'Planner requests are temporarily limited. Please wait a minute and try again.', 'plan-your-day' ),
				[
					'status' => 429,
				]
			);
		}

		$count++;
		set_transient( $cache_key, $count, self::WINDOW_SECONDS * 2 );

		return null;
	}
}
