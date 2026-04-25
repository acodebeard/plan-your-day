<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use Acodebeard\PlanYourDay\Google\GoogleApiClient;
use Acodebeard\PlanYourDay\Google\GoogleHttpTransportInterface;
use Acodebeard\PlanYourDay\Planner\PlaceParser;
use Acodebeard\PlanYourDay\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class GoogleApiClientTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ] = array_merge(
			Settings::defaults(),
			[
				'google_places_api_key' => 'places-key',
				'google_api_timeout'    => 15,
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
