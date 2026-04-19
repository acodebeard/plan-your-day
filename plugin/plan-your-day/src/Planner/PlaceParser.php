<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

defined( 'ABSPATH' ) || exit;

final class PlaceParser {
	/**
	 * Shape a Google place response into the planner's internal place array.
	 *
	 * @return array{
	 *     id: string,
	 *     label: string,
	 *     address: string,
	 *     maps_uri: string,
	 *     latitude: float|null,
	 *     longitude: float|null,
	 *     is_valid: bool
	 * }
	 */
	public function parse_google_place( array $place ): array {
		$display_name = is_array( $place['displayName'] ?? null ) ? $place['displayName'] : [];
		$location     = is_array( $place['location'] ?? null ) ? $place['location'] : [];
		$place_id     = self::sanitize_place_id( (string) ( $place['id'] ?? '' ) );
		$label        = trim( sanitize_text_field( (string) ( $display_name['text'] ?? '' ) ) );
		$address      = trim( sanitize_text_field( (string) ( $place['formattedAddress'] ?? '' ) ) );
		$maps_uri     = self::safe_https_url( (string) ( $place['googleMapsUri'] ?? '' ) );
		$latitude     = isset( $location['latitude'] ) && is_numeric( $location['latitude'] ) ? (float) $location['latitude'] : null;
		$longitude    = isset( $location['longitude'] ) && is_numeric( $location['longitude'] ) ? (float) $location['longitude'] : null;

		return [
			'id'        => $place_id,
			'label'     => '' !== $label ? $label : $address,
			'address'   => $address,
			'maps_uri'  => $maps_uri,
			'latitude'  => $latitude,
			'longitude' => $longitude,
			'is_valid'  => '' !== $place_id && ( '' !== $label || '' !== $address ),
		];
	}

	public static function sanitize_place_id( string $place_id ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', trim( $place_id ) );
	}

	public static function safe_https_url( string $url ): string {
		$url = trim( $url );

		return 1 === preg_match( '#\Ahttps://[^\s<>"\']+\z#i', $url ) ? $url : '';
	}
}
