<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Google;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class CachedGoogleApiClient implements GoogleApiClientInterface {
	private GoogleApiClientInterface $client;
	private Settings $settings;
	private GoogleApiCache $cache;

	public function __construct( GoogleApiClientInterface $client, Settings $settings, GoogleApiCache $cache ) {
		$this->client   = $client;
		$this->settings = $settings;
		$this->cache    = $cache;
	}

	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null ): GoogleApiResult {
		$cache_key = $this->cache->build_key(
			'text_search',
			[
				'query'            => trim( sanitize_text_field( $query ) ),
				'origin_latitude'  => $this->normalize_coordinate( $origin_latitude ),
				'origin_longitude' => $this->normalize_coordinate( $origin_longitude ),
			],
			$this->settings->get_google_places_api_key()
		);

		return $this->remember(
			$cache_key,
			$this->settings->get_google_text_search_cache_ttl(),
			fn (): GoogleApiResult => $this->client->text_search( $query, $origin_latitude, $origin_longitude )
		);
	}

	public function place_details( string $place_id ): GoogleApiResult {
		$cache_key = $this->cache->build_key(
			'place_details',
			[
				'place_id' => $this->sanitize_place_id( $place_id ),
			],
			$this->settings->get_google_places_api_key()
		);

		return $this->remember(
			$cache_key,
			$this->settings->get_google_place_details_cache_ttl(),
			fn (): GoogleApiResult => $this->client->place_details( $place_id )
		);
	}

	public function geocode( string $address ): GoogleApiResult {
		$cache_key = $this->cache->build_key(
			'geocode',
			[
				'address' => trim( sanitize_text_field( $address ) ),
			],
			$this->settings->get_google_geocoding_api_key()
		);

		return $this->remember(
			$cache_key,
			$this->settings->get_google_geocoding_cache_ttl(),
			fn (): GoogleApiResult => $this->client->geocode( $address )
		);
	}

	private function remember( string $cache_key, int $ttl, callable $callback ): GoogleApiResult {
		if ( $ttl > 0 ) {
			$cached = $this->cache->get( $cache_key );

			if ( null !== $cached ) {
				return $cached;
			}
		}

		$result = $callback();
		$this->cache->set( $cache_key, $result, $ttl );

		return $result;
	}

	private function normalize_coordinate( ?float $coordinate ): ?string {
		return null === $coordinate ? null : sprintf( '%.6F', $coordinate );
	}

	private function sanitize_place_id( string $place_id ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', trim( $place_id ) );
	}
}
