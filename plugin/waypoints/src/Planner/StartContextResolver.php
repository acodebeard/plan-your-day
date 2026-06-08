<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Planner;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class StartContextResolver {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return array{
	 *     search_area: string,
	 *     preview_start_label: string,
	 *     handoff_start_label: string,
	 *     handoff_summary: string,
	 *     use_current_handoff: bool,
	 *     directions_origin: string|null,
	 *     messages: array<int, array{type: string, text: string}>
	 * }
	 */
	public function resolve( string $start_mode, string $custom_start ): array {
		$allowed_modes          = $this->settings->get_allowed_start_modes();
		$default_location_label = $this->settings->get_default_location_label();
		$default_address        = $this->settings->get_default_location_address();
		$custom_start           = trim( sanitize_text_field( $custom_start ) );
		$start_mode             = sanitize_key( $start_mode );

		if ( ! in_array( $start_mode, $allowed_modes, true ) ) {
			$start_mode = Settings::START_MODE_DEFAULT;
		}

		if ( '' === $default_location_label ) {
			$default_location_label = __( 'Default location', 'waypoints' );
		}

		$preview_start_label = $default_location_label;
		$handoff_start_label = $default_location_label;
		$handoff_summary     = $default_location_label;
		$search_area         = $default_address;
		$directions_origin   = $default_address;
		$messages            = [];
		$use_current_handoff = false;

		if ( Settings::START_MODE_CURRENT === $start_mode ) {
			$handoff_start_label = __( 'Current location', 'waypoints' );
			$handoff_summary     = __( 'your current location', 'waypoints' );
			$directions_origin   = null;
			$use_current_handoff = true;
		} elseif ( Settings::START_MODE_CUSTOM === $start_mode && '' !== $custom_start ) {
			$search_area         = $custom_start;
			$preview_start_label = $custom_start;
			$handoff_start_label = $custom_start;
			$handoff_summary     = $custom_start;
			$directions_origin   = $custom_start;
		} elseif ( Settings::START_MODE_CUSTOM === $start_mode ) {
			$handoff_start_label = __( 'Default location fallback', 'waypoints' );
			$handoff_summary     = __( 'the default location until a custom starting point is provided', 'waypoints' );
			$messages[]          = [
				'type' => 'warning',
				'text' => __( 'Add a custom address to replace the default fallback before finalizing the trip start.', 'waypoints' ),
			];
		}

		return [
			'search_area'         => $search_area,
			'preview_start_label' => $preview_start_label,
			'handoff_start_label' => $handoff_start_label,
			'handoff_summary'     => $handoff_summary,
			'use_current_handoff' => $use_current_handoff,
			'directions_origin'   => $directions_origin,
			'messages'            => $messages,
		];
	}
}
