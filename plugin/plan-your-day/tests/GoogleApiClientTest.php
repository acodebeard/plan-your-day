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

	public function test_text_search_first_page_search_still_works_without_sending_page_token(): void {
		$transport                  = new RecordingGoogleHttpTransport();
		$transport->post_response_body = wp_json_encode(
			[
				'places' => [
					[
						'id'               => 'place-1',
						'displayName'      => [
							'text' => 'Test Place',
						],
						'formattedAddress' => '123 Test St',
						'googleMapsUri'    => 'https://maps.google.com/?q=place-1',
						'location'         => [
							'latitude'  => 19.64,
							'longitude' => -155.99,
						],
					],
				],
			]
		);
		$client                     = new GoogleApiClient( new Settings(), $transport, new PlaceParser() );

		$result = $client->text_search( 'coffee shops', 19.64, -155.99 );

		self::assertTrue( $result->is_success() );
		self::assertSame( '', $result->data()['nextPageToken'] ?? '' );
		self::assertCount( 1, $result->data()['places'] ?? [] );
		self::assertSame( 8, $transport->last_post_body['pageSize'] ?? null );
		self::assertArrayNotHasKey( 'pageToken', $transport->last_post_body );
	}

	public function test_text_search_sends_page_token_and_exposes_next_page_token(): void {
		$transport                    = new RecordingGoogleHttpTransport();
		$transport->post_response_body = wp_json_encode(
			[
				'places'        => [],
				'nextPageToken' => 'page-3',
			]
		);
		$client                       = new GoogleApiClient( new Settings(), $transport, new PlaceParser() );

		$result = $client->text_search( 'coffee shops', 19.64, -155.99, 'page-2' );

		self::assertTrue( $result->is_success() );
		self::assertSame( 'page-2', $transport->last_post_body['pageToken'] ?? '' );
		self::assertSame( 'page-3', $result->data()['nextPageToken'] ?? '' );
	}

	public function test_cached_text_search_key_includes_request_shaping_settings(): void {
		$cache            = new GoogleApiCache();
		$recording_client = new RecordingGoogleApiClient();
		$client           = new CachedGoogleApiClient( $recording_client, new Settings(), $cache );

		$first  = $client->text_search( ' Beaches ', 19.6400001, -155.9900001 );
		$second = $client->text_search( ' Beaches ', 19.6400001, -155.9900001 );

		self::assertSame( 1, $recording_client->text_search_calls );
		self::assertSame( $first, $second );

		$expected_cache_key = $cache->build_key(
			'text_search',
			[
				'query'           => 'Beaches',
				'page_token'      => '',
				'result_count'    => 8,
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

	public function test_cached_text_search_reuses_cache_when_distance_unit_changes(): void {
		$recording_client = new RecordingGoogleApiClient();
		$client           = new CachedGoogleApiClient( $recording_client, new Settings(), new GoogleApiCache() );

		$first = $client->text_search( 'coffee', 19.64, -155.99 );

		$settings                  = $GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ];
		$settings['distance_unit'] = Settings::DISTANCE_UNIT_KILOMETERS;
		update_option( Settings::OPTION_NAME, $settings );

		$second = $client->text_search( 'coffee', 19.64, -155.99 );

		self::assertSame( 1, $recording_client->text_search_calls );
		self::assertSame( $first, $second );
	}

	public function test_cached_text_search_misses_after_result_count_changes(): void {
		$recording_client = new RecordingGoogleApiClient();
		$client           = new CachedGoogleApiClient( $recording_client, new Settings(), new GoogleApiCache() );

		$client->text_search( 'coffee', 19.64, -155.99 );
		$client->text_search( 'coffee', 19.64, -155.99 );

		$settings = $GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ];
		$settings['result_count'] = 9;
		update_option( Settings::OPTION_NAME, $settings );

		$client->text_search( 'coffee', 19.64, -155.99 );

		self::assertSame( 2, $recording_client->text_search_calls );
	}

	public function test_cached_text_search_key_changes_when_page_token_changes(): void {
		$recording_client = new RecordingGoogleApiClient();
		$client           = new CachedGoogleApiClient( $recording_client, new Settings(), new GoogleApiCache() );

		$client->text_search( 'coffee', 19.64, -155.99 );
		$client->text_search( 'coffee', 19.64, -155.99 );
		$client->text_search( 'coffee', 19.64, -155.99, 'page-2' );
		$client->text_search( 'coffee', 19.64, -155.99, 'page-2' );

		self::assertSame( 2, $recording_client->text_search_calls );
		self::assertSame( [ '', 'page-2' ], $recording_client->text_search_page_tokens );
	}
}

final class RecordingGoogleHttpTransport implements GoogleHttpTransportInterface {
	public array $last_get_args = [];
	public array $last_post_args = [];
	public array $last_post_body = [];
	public string $post_response_body = '{}';

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
		$this->last_post_args = $args;
		$this->last_post_body = json_decode( (string) ( $args['body'] ?? '{}' ), true ) ?: [];

		return [
			'response' => [
				'code' => 200,
			],
			'body'     => $this->post_response_body,
		];
	}
}

final class RecordingGoogleApiClient implements GoogleApiClientInterface {
	public int $text_search_calls = 0;
	public array $text_search_page_tokens = [];

	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null, string $page_token = '' ): GoogleApiResult {
		++$this->text_search_calls;
		$this->text_search_page_tokens[] = $page_token;

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
