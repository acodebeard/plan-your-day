<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Google\GoogleApiResult;
use Acodebeard\PlanYourDay\Planner\PlaceParser;

defined( 'ABSPATH' ) || exit;

final class BrowserSmokeGoogleApiClient implements GoogleApiClientInterface {
	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null, string $page_token = '' ): GoogleApiResult {
		unset( $origin_latitude, $origin_longitude );

		$query      = strtolower( trim( sanitize_text_field( $query ) ) );
		$page_token = trim( sanitize_text_field( $page_token ) );
		$page_key   = '' !== $page_token ? $page_token : '__first__';
		$pages      = self::text_search_pages();

		if ( ! isset( $pages[ $query ][ $page_key ] ) ) {
			return GoogleApiResult::success(
				[
					'places' => [],
				]
			);
		}

		return GoogleApiResult::success( $pages[ $query ][ $page_key ] );
	}

	public function place_details( string $place_id, ?int $timeout = null ): GoogleApiResult {
		unset( $timeout );

		$place_id = PlaceParser::sanitize_place_id( $place_id );
		$places   = self::places();

		if ( '' === $place_id || ! isset( $places[ $place_id ] ) ) {
			return GoogleApiResult::failure(
				'place_details_unavailable',
				__( 'Google place details are unavailable right now.', 'plan-your-day' ),
				404,
				false
			);
		}

		return GoogleApiResult::success(
			[
				'place' => $places[ $place_id ],
			]
		);
	}

	public function geocode( string $address ): GoogleApiResult {
		$address = trim( sanitize_text_field( $address ) );

		if ( '' === $address ) {
			return GoogleApiResult::failure(
				'geocoding_unavailable',
				__( 'Google geocoding is unavailable right now.', 'plan-your-day' ),
				0,
				false
			);
		}

		return GoogleApiResult::success(
			[
				'latitude'  => 37.7749,
				'longitude' => -122.4194,
			]
		);
	}

	/**
	 * @return array<string, array<string, array{places: array<int, array<string, mixed>>, nextPageToken?: string}>>
	 */
	private static function text_search_pages(): array {
		$places = self::places();

		return [
			'coffee near me' => [
				'__first__'     => [
					'places'        => [
						$places['coffee-1'],
						$places['coffee-2'],
					],
					'nextPageToken' => 'coffee-page-2',
				],
				'coffee-page-2' => [
					'places' => [
						$places['coffee-1'],
						$places['coffee-2'],
						$places['coffee-3'],
						$places['coffee-4'],
					],
				],
			],
			'food near me' => [
				'__first__' => [
					'places' => [
						$places['food-1'],
						$places['food-2'],
					],
				],
			],
		];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function places(): array {
		return [
			'coffee-1' => self::place(
				'coffee-1',
				'Harbor Coffee',
				'100 Pier Street',
				37.7762,
				-122.4170
			),
			'coffee-2' => self::place(
				'coffee-2',
				'Sunrise Cafe',
				'240 Market Lane',
				37.7784,
				-122.4152
			),
			'coffee-3' => self::place(
				'coffee-3',
				'Coastal Roasters',
				'410 Bay Avenue',
				37.7801,
				-122.4138
			),
			'coffee-4' => self::place(
				'coffee-4',
				'Boardwalk Espresso',
				'520 Harbor Walk',
				37.7818,
				-122.4119
			),
			'food-1'   => self::place(
				'food-1',
				'Harbor Bistro',
				'85 Main Street',
				37.7756,
				-122.4181
			),
			'food-2'   => self::place(
				'food-2',
				'Market Kitchen',
				'310 Dock Square',
				37.7773,
				-122.4160
			),
		];
	}

	/**
	 * @return array{
	 *     id: string,
	 *     label: string,
	 *     address: string,
	 *     maps_uri: string,
	 *     latitude: float,
	 *     longitude: float
	 * }
	 */
	private static function place( string $id, string $label, string $address, float $latitude, float $longitude ): array {
		return [
			'id'        => $id,
			'label'     => $label,
			'address'   => $address,
			'maps_uri'  => 'https://maps.example.test/place/' . rawurlencode( $id ),
			'latitude'  => $latitude,
			'longitude' => $longitude,
		];
	}
}
