<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Google\CachedGoogleApiClient;
use Acodebeard\PlanYourDay\Google\GoogleApiClient;
use Acodebeard\PlanYourDay\Google\GoogleApiCache;
use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Google\GoogleApiResult;
use Acodebeard\PlanYourDay\Google\GoogleHttpTransportInterface;
use Acodebeard\PlanYourDay\Planner\PlaceParser;
use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class GoogleApiClientTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['plan_your_day_test_options']    = [];
		$GLOBALS['plan_your_day_test_transients'] = [];
		$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ] = array_merge(
			Settings::defaults(),
			[
				'google_places_api_key'        => 'places-key',
				'google_api_timeout'           => 15,
				'google_text_search_cache_ttl' => 600,
			]
		);
	}

	public function test_place_details_uses_timeout_override_when_it_is_lower_than_configured_timeout(): void {
		$transport = new RecordingGoogleHttpTransport();
		$client    = new GoogleApiClient( new Settings(), $transport, new PlaceParser() );

		$result = $client->place_details( 'place-1', 4 );

		self::assertTrue( $result->is_success() );
		self::assertSame( 4, $transport->last_get_args['timeout'] ?? null );
	}

	public function test_place_details_does_not_exceed_the_configured_timeout(): void {
		$transport = new RecordingGoogleHttpTransport();
		$client    = new GoogleApiClient( new Settings(), $transport, new PlaceParser() );

		$result = $client->place_details( 'place-1', 25 );

		self::assertTrue( $result->is_success() );
		self::assertSame( 15, $transport->last_get_args['timeout'] ?? null );
	}

	public function test_cached_text_search_key_includes_request_shaping_settings(): void {
		$cache           = new GoogleApiCache();
		$recording_client = new RecordingGoogleApiClient();
		$client          = new CachedGoogleApiClient( $recording_client, new Settings(), $cache );

		$first = $client->text_search( ' Beaches ', 19.6400001, -155.9900001 );
		$second = $client->text_search( ' Beaches ', 19.6400001, -155.9900001 );

		self::assertSame( 1, $recording_client->text_search_calls );
		self::assertSame( $first, $second );

		$expected_cache_key = $cache->build_key(
			'text_search',
			[
				'query'           => 'Beaches',
				'result_count'    => 16,
				'distance_unit'   => Settings::DISTANCE_UNIT_MILES,
				'rank_preference' => 'DISTANCE',
				'location_bias'   => [
					'circle' => [
						'center' => [
							'latitude'  => '19.640000',
							'longitude' => '-155.990000',
						],
						'radius' => GoogleApiClient::TEXT_SEARCH_LOCATION_BIAS_RADIUS_METERS,
					],
				],
			],
			'places-key'
		);

		self::assertArrayHasKey( $expected_cache_key, $GLOBALS['plan_your_day_test_transients'] );
	}

	public function test_cached_text_search_misses_after_result_count_changes(): void {
		$recording_client = new RecordingGoogleApiClient();
		$client           = new CachedGoogleApiClient( $recording_client, new Settings(), new GoogleApiCache() );

		$client->text_search( 'coffee', 19.64, -155.99 );
		$client->text_search( 'coffee', 19.64, -155.99 );

		$settings = $GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ];
		$settings['result_count'] = 8;
		update_option( Settings::OPTION_NAME, $settings );

		$client->text_search( 'coffee', 19.64, -155.99 );

		self::assertSame( 2, $recording_client->text_search_calls );
	}
}

final class RecordingGoogleHttpTransport implements GoogleHttpTransportInterface {
	public array $last_get_args = [];

	public function get( string $url, array $args = [] ) {
		$this->last_get_args = $args;

		return [
			'response' => [
				'code' => 200,
			],
			'body'     => wp_json_encode(
				[
					'id'               => 'place-1',
					'displayName'      => [
						'text' => 'Test Place',
					],
					'formattedAddress' => '123 Test St',
					'googleMapsUri'    => 'https://maps.google.com/?q=place-1',
				]
			),
		];
	}

	public function post( string $url, array $args = [] ) {
		return [
			'response' => [
				'code' => 200,
			],
			'body'     => '{}',
		];
	}
}

final class RecordingGoogleApiClient implements GoogleApiClientInterface {
	public int $text_search_calls = 0;

	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null ): GoogleApiResult {
		++$this->text_search_calls;

		return GoogleApiResult::success(
			[
				'places' => [
					[
						'id' => 'call-' . $this->text_search_calls,
					],
				],
			]
		);
	}

	public function place_details( string $place_id, ?int $timeout = null ): GoogleApiResult {
		return GoogleApiResult::success( [] );
	}

	public function geocode( string $address ): GoogleApiResult {
		return GoogleApiResult::success( [] );
	}
}
