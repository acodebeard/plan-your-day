<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Google;

defined( 'ABSPATH' ) || exit;

final class GoogleApiCache {
	private const KEY_PREFIX = 'pyd_google_';
	private const INDEX_OPTION = 'plan_your_day_google_cache_keys';
	private const MAX_TRACKED_KEYS = 1000;

	public function build_key( string $scope, array $parts, string $api_key ): string {
		$cache_parts = [
			'scope' => $scope,
			'key'   => hash_hmac( 'sha256', $api_key, wp_salt( 'auth' ) ),
			'parts' => $parts,
		];
		$encoded = wp_json_encode( $cache_parts );

		if ( ! is_string( $encoded ) ) {
			$encoded = serialize( $cache_parts );
		}

		return self::KEY_PREFIX . hash( 'sha256', $encoded );
	}

	public function get( string $cache_key ): ?GoogleApiResult {
		$cached = get_transient( $cache_key );

		return $cached instanceof GoogleApiResult ? $cached : null;
	}

	public function set( string $cache_key, GoogleApiResult $result, int $ttl ): void {
		if ( $ttl < 1 || ! $result->is_success() ) {
			return;
		}

		set_transient( $cache_key, $result, $ttl );
		$this->track_key( $cache_key );
	}

	public function clear(): int {
		$cache_keys = $this->get_tracked_keys();

		foreach ( $cache_keys as $cache_key ) {
			delete_transient( $cache_key );
		}

		delete_option( self::INDEX_OPTION );

		return count( $cache_keys );
	}

	private function track_key( string $cache_key ): void {
		$cache_keys = $this->get_tracked_keys();
		$cache_keys[] = $cache_key;
		$cache_keys = array_values( array_unique( $cache_keys ) );

		if ( count( $cache_keys ) > self::MAX_TRACKED_KEYS ) {
			$cache_keys = array_slice( $cache_keys, -self::MAX_TRACKED_KEYS );
		}

		update_option( self::INDEX_OPTION, $cache_keys, false );
	}

	private function get_tracked_keys(): array {
		$cache_keys = get_option( self::INDEX_OPTION, [] );

		if ( ! is_array( $cache_keys ) ) {
			return [];
		}

		$tracked_keys = [];

		foreach ( $cache_keys as $cache_key ) {
			if ( ! is_scalar( $cache_key ) ) {
				continue;
			}

			$cache_key = (string) $cache_key;

			if ( str_starts_with( $cache_key, self::KEY_PREFIX ) ) {
				$tracked_keys[] = $cache_key;
			}
		}

		return array_values( array_unique( $tracked_keys ) );
	}
}
