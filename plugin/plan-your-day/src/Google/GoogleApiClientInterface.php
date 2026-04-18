<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Google;

defined( 'ABSPATH' ) || exit;

interface GoogleApiClientInterface {
	public function text_search( string $query, ?float $origin_latitude = null, ?float $origin_longitude = null ): GoogleApiResult;

	public function place_details( string $place_id ): GoogleApiResult;

	public function geocode( string $address ): GoogleApiResult;
}
