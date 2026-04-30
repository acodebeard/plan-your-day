<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Frontend\InterfaceCopy;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class DistanceFormatter {
	private const EARTH_RADIUS_MILES = 3958.8;
	private const MILES_TO_KILOMETERS = 1.609344;
	private ?Settings $settings;

	public function __construct( ?Settings $settings = null ) {
		$this->settings = $settings;
	}

	public function calculate_miles(
		float $origin_latitude,
		float $origin_longitude,
		float $destination_latitude,
		float $destination_longitude
	): float {
		$latitude_delta           = deg2rad( $destination_latitude - $origin_latitude );
		$longitude_delta          = deg2rad( $destination_longitude - $origin_longitude );
		$origin_latitude_rad      = deg2rad( $origin_latitude );
		$destination_latitude_rad = deg2rad( $destination_latitude );
		$haversine                = sin( $latitude_delta / 2 ) ** 2
			+ cos( $origin_latitude_rad ) * cos( $destination_latitude_rad ) * sin( $longitude_delta / 2 ) ** 2;
		$central_angle            = 2 * asin( min( 1, sqrt( $haversine ) ) );

		return self::EARTH_RADIUS_MILES * $central_angle;
	}

	public function format_label( float $distance_miles, string $reference_label, string $distance_unit ): string {
		$reference_label = trim( $reference_label );
		$distance_unit   = Settings::DISTANCE_UNIT_KILOMETERS === $distance_unit
			? Settings::DISTANCE_UNIT_KILOMETERS
			: Settings::DISTANCE_UNIT_MILES;
		$distance        = Settings::DISTANCE_UNIT_KILOMETERS === $distance_unit
			? $distance_miles * self::MILES_TO_KILOMETERS
			: $distance_miles;
		$unit_label      = Settings::DISTANCE_UNIT_KILOMETERS === $distance_unit ? 'km' : 'mi';

		if ( $distance < 0.1 ) {
			if ( '' === $reference_label ) {
				return $this->format_copy(
					'distance_under_threshold_without_reference',
					[
						'unit' => $unit_label,
					]
				);
			}

			return $this->format_copy(
				'distance_under_threshold_with_reference',
				[
					'unit'  => $unit_label,
					'start' => $reference_label,
				]
			);
		}

		$rounded_distance = $distance >= 10
			? number_format_i18n( $distance, 0 )
			: number_format_i18n( $distance, 1 );

		if ( '' === $reference_label ) {
			return $this->format_copy(
				'distance_approx_without_reference',
				[
					'distance' => $rounded_distance,
					'unit'     => $unit_label,
				]
			);
		}

		return $this->format_copy(
			'distance_approx_with_reference',
			[
				'distance' => $rounded_distance,
				'unit'     => $unit_label,
				'start'    => $reference_label,
			]
		);
	}

	private function format_copy( string $key, array $tokens ): string {
		if ( $this->settings instanceof Settings ) {
			return $this->settings->format_frontend_copy( $key, $tokens );
		}

		return InterfaceCopy::format( InterfaceCopy::default_value( $key ), $tokens );
	}
}
