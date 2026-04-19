<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class DistanceFormatter {
	private const EARTH_RADIUS_MILES = 3958.8;
	private const MILES_TO_KILOMETERS = 1.609344;

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
				return sprintf(
					/* translators: %s is a distance unit abbreviation, such as mi or km. */
					__( 'Less than 0.1 %s away', PLAN_YOUR_DAY_TEXT_DOMAIN ),
					$unit_label
				);
			}

			return sprintf(
				/* translators: 1: distance unit abbreviation, 2: starting point label. */
				__( 'Less than 0.1 %1$s from %2$s', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				$unit_label,
				$reference_label
			);
		}

		$rounded_distance = $distance >= 10
			? number_format_i18n( $distance, 0 )
			: number_format_i18n( $distance, 1 );

		if ( '' === $reference_label ) {
			return sprintf(
				/* translators: 1: rounded distance, 2: distance unit abbreviation. */
				__( 'Approx. %1$s %2$s away', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				$rounded_distance,
				$unit_label
			);
		}

		return sprintf(
			/* translators: 1: rounded distance, 2: distance unit abbreviation, 3: starting point label. */
			__( 'Approx. %1$s %2$s from %3$s', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			$rounded_distance,
			$unit_label,
			$reference_label
		);
	}
}
