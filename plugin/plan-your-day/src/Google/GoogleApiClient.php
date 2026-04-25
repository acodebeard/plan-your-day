<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Google;

use Acodebeard\PlanYourDay\Planner\PlaceParser;
use Acodebeard\PlanYourDay\Settings\Settings;
use Acodebeard\PlanYourDay\Support\DebugLogger;

defined( 'ABSPATH' ) || exit;

final class GoogleApiClient implements GoogleApiClientInterface {
	private const TEXT_SEARCH_ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';
	private const PLACE_DETAILS_ENDPOINT = 'https://places.googleapis.com/v1/places/';
	private const GEOCODE_ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';
	private const TEXT_SEARCH_FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.googleMapsUri,places.location';
	private const PLACE_DETAILS_FIELD_MASK = 'id,displayName,formattedAddress,googleMapsUri';

	private Settings $settings;
	private GoogleHttpTransportInterface $transport;
	private PlaceParser $place_parser;

	public function __construct( Settings $settings, ?GoogleHttpTransportInterface $transport = null, ?PlaceParser $place_parser = null ) {
		$this->settings     = $settings;
		$this->transport    = $transport ?? new WordPressGoogleHttpTransport();
		$this->place_parser = $place_parser ?? new PlaceParser();
	}

	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null ): GoogleApiResult {
		$query = trim( sanitize_text_field( $query ) );

		if ( '' === $query ) {
			return GoogleApiResult::success(
				[
					'places' => [],
				]
			);
		}

		$api_key = $this->settings->get_google_places_api_key();

		if ( '' === $api_key ) {
			return GoogleApiResult::failure(
				'missing_places_api_key',
				__( 'Add a valid Google Places API key to load Google place results.', 'plan-your-day' ),
				0,
				false
			);
		}

		$request_body = [
			'textQuery'      => $query,
			'pageSize'       => $this->settings->get_result_count(),
			'rankPreference' => 'DISTANCE',
		];

		if ( $this->is_valid_coordinate( $origin_latitude, -90, 90 ) && $this->is_valid_coordinate( $origin_longitude, -180, 180 ) ) {
			$request_body['locationBias'] = [
				'circle' => [
					'center' => [
						'latitude'  => $origin_latitude,
						'longitude' => $origin_longitude,
					],
					'radius' => 15000.0,
				],
			];
		}

		DebugLogger::log(
			'google.text_search.request',
			[
				'query'            => $query,
				'origin_latitude'  => $origin_latitude,
				'origin_longitude' => $origin_longitude,
				'request_body'     => $request_body,
			]
		);

		$response = $this->transport->post(
			self::TEXT_SEARCH_ENDPOINT,
			[
				'timeout' => $this->settings->get_google_api_timeout(),
				'headers' => [
					'Content-Type'      => 'application/json',
					'X-Goog-Api-Key'    => $api_key,
					'X-Goog-FieldMask'  => self::TEXT_SEARCH_FIELD_MASK,
				],
				'body'    => (string) wp_json_encode( $request_body ),
			]
		);
		$decoded  = $this->decode_response(
			$response,
			'places_unavailable',
			__( 'Google place results are unavailable right now.', 'plan-your-day' )
		);

		if ( $decoded instanceof GoogleApiResult ) {
			return $decoded;
		}

		$places = [];

		foreach ( (array) ( $decoded['body']['places'] ?? [] ) as $place ) {
			$parsed_place = $this->place_parser->parse_google_place( (array) $place );

			if ( ! $parsed_place['is_valid'] ) {
				continue;
			}

			unset( $parsed_place['is_valid'] );
			$places[] = $parsed_place;
		}

		return GoogleApiResult::success(
			[
				'places' => $places,
			],
			$decoded['status_code']
		);
	}

	public function place_details( string $place_id, ?int $timeout = null ): GoogleApiResult {
		$place_id = PlaceParser::sanitize_place_id( $place_id );

		if ( '' === $place_id ) {
			return GoogleApiResult::failure(
				'invalid_place_id',
				__( 'Google place details are unavailable right now.', 'plan-your-day' ),
				0,
				false
			);
		}

		$api_key = $this->settings->get_google_places_api_key();

		if ( '' === $api_key ) {
			return GoogleApiResult::failure(
				'missing_places_api_key',
				__( 'Add a valid Google Places API key to load exact Google place details.', 'plan-your-day' ),
				0,
				false
			);
		}

		DebugLogger::log(
			'google.place_details.request',
			[
				'place_id' => $place_id,
			]
		);

		$response = $this->transport->get(
			self::PLACE_DETAILS_ENDPOINT . rawurlencode( $place_id ),
			[
				'timeout' => $this->resolve_timeout( $timeout ),
				'headers' => [
					'X-Goog-Api-Key'   => $api_key,
					'X-Goog-FieldMask' => self::PLACE_DETAILS_FIELD_MASK,
				],
			]
		);
		$decoded  = $this->decode_response(
			$response,
			'place_details_unavailable',
			__( 'Google place details are unavailable right now.', 'plan-your-day' )
		);

		if ( $decoded instanceof GoogleApiResult ) {
			return $decoded;
		}

		$place = $this->place_parser->parse_google_place( (array) $decoded['body'] );

		if ( ! $place['is_valid'] ) {
			return GoogleApiResult::failure(
				'place_details_unavailable',
				__( 'Google place details are unavailable right now.', 'plan-your-day' ),
				$decoded['status_code'],
				false
			);
		}

		unset( $place['is_valid'] );

		return GoogleApiResult::success(
			[
				'place' => $place,
			],
			$decoded['status_code']
		);
	}

	private function resolve_timeout( ?int $timeout_override = null ): int {
		$timeout = $this->settings->get_google_api_timeout();

		if ( null === $timeout_override || $timeout_override < 1 ) {
			return $timeout;
		}

		return min( $timeout, $timeout_override );
	}

	public function geocode( string $address ): GoogleApiResult {
		$address = trim( sanitize_text_field( $address ) );

		if ( '' === $address ) {
			return GoogleApiResult::failure(
				'empty_geocode_address',
				__( 'Google geocoding is unavailable for this address.', 'plan-your-day' ),
				0,
				false
			);
		}

		$api_key = $this->settings->get_google_geocoding_api_key();

		if ( '' === $api_key ) {
			return GoogleApiResult::failure(
				'missing_geocoding_api_key',
				__( 'Add a valid Google Geocoding API key or Places API key to geocode starting locations.', 'plan-your-day' ),
				0,
				false
			);
		}

		DebugLogger::log(
			'google.geocode.request',
			[
				'address' => $address,
			]
		);

		$response = $this->transport->get(
			add_query_arg(
				[
					'address' => $address,
					'key'     => $api_key,
				],
				self::GEOCODE_ENDPOINT
			),
			[
				'timeout' => $this->settings->get_google_api_timeout(),
			]
		);
		$decoded  = $this->decode_response(
			$response,
			'geocoding_unavailable',
			__( 'Google geocoding is unavailable right now.', 'plan-your-day' )
		);

		if ( $decoded instanceof GoogleApiResult ) {
			return $decoded;
		}

		$body     = $decoded['body'];
		$results  = is_array( $body['results'] ?? null ) ? $body['results'] : [];
		$first    = is_array( $results[0] ?? null ) ? $results[0] : [];
		$geometry = is_array( $first['geometry'] ?? null ) ? $first['geometry'] : [];
		$location = is_array( $geometry['location'] ?? null ) ? $geometry['location'] : [];

		if (
			'OK' !== (string) ( $body['status'] ?? '' ) ||
			! isset( $location['lat'], $location['lng'] ) ||
			! is_numeric( $location['lat'] ) ||
			! is_numeric( $location['lng'] )
		) {
			return GoogleApiResult::failure(
				'geocoding_unavailable',
				__( 'Google geocoding is unavailable right now.', 'plan-your-day' ),
				$decoded['status_code'],
				$this->is_retryable_status( $decoded['status_code'] )
			);
		}

		return GoogleApiResult::success(
			[
				'latitude'  => (float) $location['lat'],
				'longitude' => (float) $location['lng'],
			],
			$decoded['status_code']
		);
	}

	/**
	 * @return array{body: array, status_code: int}|GoogleApiResult
	 */
	private function decode_response( mixed $response, string $error_code, string $message ): array|GoogleApiResult {
		if ( is_wp_error( $response ) ) {
			DebugLogger::log(
				'google.response.wp_error',
				[
					'error_code' => $error_code,
					'response'   => $response,
				]
			);
			return GoogleApiResult::failure( $error_code, $message );
		}

		if ( ! is_array( $response ) ) {
			DebugLogger::log(
				'google.response.invalid_transport',
				[
					'error_code'    => $error_code,
					'response_type' => gettype( $response ),
				]
			);
			return GoogleApiResult::failure( $error_code, $message );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) ) {
			DebugLogger::log(
				'google.response.error',
				[
					'error_code'   => $error_code,
					'status_code'  => $status_code,
					'body_excerpt' => wp_remote_retrieve_body( $response ),
				]
			);
			return GoogleApiResult::failure(
				$error_code,
				$message,
				$status_code,
				$this->is_retryable_status( $status_code )
			);
		}

		DebugLogger::log(
			'google.response.success',
			[
				'error_code'  => $error_code,
				'status_code' => $status_code,
				'body_keys'   => array_keys( $body ),
			]
		);

		return [
			'body'        => $body,
			'status_code' => $status_code,
		];
	}

	private function is_valid_coordinate( ?float $coordinate, float $minimum, float $maximum ): bool {
		return null !== $coordinate && $coordinate >= $minimum && $coordinate <= $maximum;
	}

	private function is_retryable_status( int $status_code ): bool {
		return 0 === $status_code || 429 === $status_code || $status_code >= 500;
	}
}
