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
	 *     start_note_text: string,
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
			$default_location_label = $this->settings->get_frontend_copy_value( 'start_default_location_fallback' );
		}

		$preview_start_label = $default_location_label;
		$handoff_start_label = $default_location_label;
		$handoff_summary     = $default_location_label;
		$search_area         = $default_address;
		$directions_origin   = $default_address;
		$messages            = [];
		$use_current_handoff = false;
		$start_note_text     = $this->settings->get_frontend_copy_value( 'start_default_note' );

		if ( Settings::START_MODE_CURRENT === $start_mode ) {
			$handoff_start_label = $this->settings->get_frontend_copy_value( 'start_current_handoff_label' );
			$handoff_summary     = $this->settings->get_frontend_copy_value( 'start_current_handoff_summary' );
			$directions_origin   = null;
			$use_current_handoff = true;
			$start_note_text     = $this->settings->get_frontend_copy_value( 'start_current_note' );
			$messages[]          = [
				'type' => 'note',
				'text' => $this->settings->get_frontend_copy_value( 'start_current_message' ),
			];
		} elseif ( Settings::START_MODE_CUSTOM === $start_mode && '' !== $custom_start ) {
			$search_area         = $custom_start;
			$preview_start_label = $custom_start;
			$handoff_start_label = $custom_start;
			$handoff_summary     = $custom_start;
			$directions_origin   = $custom_start;
			$start_note_text     = $this->settings->get_frontend_copy_value( 'start_custom_note' );
		} elseif ( Settings::START_MODE_CUSTOM === $start_mode ) {
			$handoff_start_label = $this->settings->get_frontend_copy_value( 'start_default_fallback_label' );
			$handoff_summary     = $this->settings->get_frontend_copy_value( 'start_default_fallback_summary' );
			$start_note_text     = $this->settings->get_frontend_copy_value( 'start_custom_missing_note' );
			$messages[]          = [
				'type' => 'warning',
				'text' => $this->settings->get_frontend_copy_value( 'start_custom_missing_message' ),
			];
		}

		return [
			'search_area'         => $search_area,
			'preview_start_label' => $preview_start_label,
			'handoff_start_label' => $handoff_start_label,
			'handoff_summary'     => $handoff_summary,
			'use_current_handoff' => $use_current_handoff,
			'directions_origin'   => $directions_origin,
			'start_note_text'     => $start_note_text,
			'messages'            => $messages,
		];
	}
}
