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

		if ( false === $cached ) {
			$this->untrack_entry( $cache_key );
		}

		return $cached instanceof GoogleApiResult ? $cached : null;
	}

	public function set( string $cache_key, GoogleApiResult $result, int $ttl, string $scope = '', string $place_id = '' ): void {
		if ( $ttl < 1 ) {
			return;
		}

		set_transient( $cache_key, $result, $ttl );
		$this->track_entry( $cache_key, $scope, $place_id );
	}

	public function clear(): int {
		return $this->clear_matching(
			static function (): bool {
				return true;
			}
		);
	}

	public function clear_for_scope( string $scope ): int {
		$scope = sanitize_key( $scope );

		if ( '' === $scope ) {
			return 0;
		}

		return $this->clear_matching(
			static function ( array $entry ) use ( $scope ): bool {
				return $entry['scope'] === $scope;
			}
		);
	}

	public function clear_for_place( string $place_id ): int {
		$place_id = $this->sanitize_place_id( $place_id );

		if ( '' === $place_id ) {
			return 0;
		}

		return $this->clear_matching(
			static function ( array $entry ) use ( $place_id ): bool {
				return $entry['place_id'] === $place_id;
			}
		);
	}

	private function track_entry( string $cache_key, string $scope, string $place_id ): void {
		$entries           = $this->get_tracked_entries();
		$entries_by_key    = [];

		foreach ( $entries as $entry ) {
			$entries_by_key[ $entry['cache_key'] ] = $entry;
		}

		$entries_by_key[ $cache_key ] = [
			'cache_key' => $cache_key,
			'scope'     => sanitize_key( $scope ),
			'place_id'  => $this->sanitize_place_id( $place_id ),
		];

		$entries = array_values( $entries_by_key );

		if ( count( $entries ) > self::MAX_TRACKED_KEYS ) {
			$entries = array_slice( $entries, -self::MAX_TRACKED_KEYS );
		}

		update_option( self::INDEX_OPTION, $entries, false );
	}

	private function clear_matching( callable $predicate ): int {
		$entries         = $this->get_tracked_entries();
		$remaining       = [];
		$cleared_count   = 0;

		foreach ( $entries as $entry ) {
			if ( $predicate( $entry ) ) {
				delete_transient( $entry['cache_key'] );
				++$cleared_count;
				continue;
			}

			$remaining[] = $entry;
		}

		if ( [] === $remaining ) {
			delete_option( self::INDEX_OPTION );
		} else {
			update_option( self::INDEX_OPTION, array_values( $remaining ), false );
		}

		return $cleared_count;
	}

	private function untrack_entry( string $cache_key ): void {
		$entries = get_option( self::INDEX_OPTION, [] );

		if ( ! is_array( $entries ) || [] === $entries ) {
			return;
		}

		$remaining = [];

		foreach ( $entries as $entry ) {
			$normalized = $this->normalize_tracked_entry( $entry );

			if ( null === $normalized || $normalized['cache_key'] === $cache_key ) {
				continue;
			}

			$remaining[] = $normalized;
		}

		if ( count( $remaining ) === count( $entries ) ) {
			return;
		}

		if ( [] === $remaining ) {
			delete_option( self::INDEX_OPTION );
			return;
		}

		update_option( self::INDEX_OPTION, $remaining, false );
	}

	private function get_tracked_entries(): array {
		$entries = get_option( self::INDEX_OPTION, [] );

		if ( ! is_array( $entries ) ) {
			return [];
		}

		$tracked_entries = [];

		foreach ( $entries as $entry ) {
			$normalized = $this->normalize_tracked_entry( $entry );

			if ( null !== $normalized && $this->tracked_entry_exists( $normalized['cache_key'] ) ) {
				$tracked_entries[ $normalized['cache_key'] ] = $normalized;
			}
		}

		return array_values( $tracked_entries );
	}

	private function tracked_entry_exists( string $cache_key ): bool {
		return false !== get_transient( $cache_key );
	}

	private function normalize_tracked_entry( mixed $entry ): ?array {
		if ( is_scalar( $entry ) ) {
			$cache_key = (string) $entry;

			if ( ! str_starts_with( $cache_key, self::KEY_PREFIX ) ) {
				return null;
			}

			return [
				'cache_key' => $cache_key,
				'scope'     => '',
				'place_id'  => '',
			];
		}

		if ( ! is_array( $entry ) ) {
			return null;
		}

		$cache_key = is_scalar( $entry['cache_key'] ?? null ) ? (string) $entry['cache_key'] : '';

		if ( ! str_starts_with( $cache_key, self::KEY_PREFIX ) ) {
			return null;
		}

		return [
			'cache_key' => $cache_key,
			'scope'     => sanitize_key( is_scalar( $entry['scope'] ?? null ) ? (string) $entry['scope'] : '' ),
			'place_id'  => $this->sanitize_place_id( is_scalar( $entry['place_id'] ?? null ) ? (string) $entry['place_id'] : '' ),
		];
	}

	private function sanitize_place_id( string $place_id ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', trim( $place_id ) );
	}
}
