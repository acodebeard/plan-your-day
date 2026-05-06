<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

defined( 'ABSPATH' ) || exit;

final class InterfaceCopy {
	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	public static function definitions(): array {
		return array_merge(
			self::general_definitions(),
			self::starting_point_definitions(),
			self::search_results_definitions(),
			self::trip_definitions(),
			self::preview_definitions(),
			self::status_definitions(),
			self::distance_definitions()
		);
	}

	/**
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function groups(): array {
		return [
			'general'         => [
				'label'       => __( 'Top Section And Setup Notice', 'plan-your-day' ),
				'description' => __( 'Edit the main heading, intro text, and the fallback notice shown when required planner settings are missing.', 'plan-your-day' ),
			],
			'starting_point'  => [
				'label'       => __( 'Starting Point', 'plan-your-day' ),
				'description' => __( 'Edit the starting-point labels, helper copy, and custom start instructions. Helper text fields may be left blank to hide them.', 'plan-your-day' ),
			],
			'search_results'  => [
				'label'       => __( 'Search And Results', 'plan-your-day' ),
				'description' => __( 'Edit category search labels, result actions, and search-related empty states.', 'plan-your-day' ),
			],
			'trip'            => [
				'label'       => __( 'Trip Builder', 'plan-your-day' ),
				'description' => __( 'Edit waypoint labels, empty states, route summaries, and trip-related warnings.', 'plan-your-day' ),
			],
			'preview'         => [
				'label'       => __( 'Preview And Summary', 'plan-your-day' ),
				'description' => __( 'Edit the preview card, Google Maps handoff labels, and summary field names. Optional helper text may be blank.', 'plan-your-day' ),
			],
			'status_messages' => [
				'label'       => __( 'Loading, Errors, And Live Announcements', 'plan-your-day' ),
				'description' => __( 'Edit loading text, frontend error messages, and screen-reader announcements triggered by dynamic planner updates.', 'plan-your-day' ),
			],
			'distance'        => [
				'label'       => __( 'Distance Labels', 'plan-your-day' ),
				'description' => __( 'Edit the distance text shown on search results. Use {distance}, {unit}, and {start} where needed.', 'plan-your-day' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	public static function definitions_for_group( string $group ): array {
		$definitions = [];

		foreach ( self::definitions() as $key => $definition ) {
			if ( $definition['group'] === $group ) {
				$definitions[ $key ] = $definition;
			}
		}

		return $definitions;
	}

	/**
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		$defaults = [];

		foreach ( self::definitions() as $key => $definition ) {
			$defaults[ $key ] = $definition['default'];
		}

		return $defaults;
	}

	public static function default_value( string $key ): string {
		$definitions = self::definitions();

		return isset( $definitions[ $key ]['default'] ) ? (string) $definitions[ $key ]['default'] : '';
	}

	/**
	 * @return array<string, string>
	 */
	public static function sanitize( mixed $raw_copy ): array {
		$definitions = self::definitions();
		$raw_copy    = is_array( $raw_copy ) ? wp_unslash( $raw_copy ) : [];
		$sanitized   = [];

		foreach ( $definitions as $key => $definition ) {
			$has_raw_value = array_key_exists( $key, $raw_copy );
			$raw_value     = $has_raw_value ? $raw_copy[ $key ] : $definition['default'];
			$value         = self::sanitize_field_value( $raw_value, $definition['type'] );

			if ( ! $has_raw_value ) {
				$sanitized[ $key ] = $definition['default'];
				continue;
			}

			if ( '' === $value && $definition['required'] ) {
				$sanitized[ $key ] = $definition['default'];
				continue;
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * @return array<string, string>
	 */
	public static function resolve_values( mixed $copy ): array {
		$definitions = self::definitions();
		$copy        = is_array( $copy ) ? $copy : [];
		$resolved    = [];

		foreach ( $definitions as $key => $definition ) {
			if ( ! array_key_exists( $key, $copy ) ) {
				$resolved[ $key ] = $definition['default'];
				continue;
			}

			$value = is_scalar( $copy[ $key ] ) ? trim( (string) $copy[ $key ] ) : '';

			if ( '' === $value && $definition['required'] ) {
				$resolved[ $key ] = $definition['default'];
				continue;
			}

			$resolved[ $key ] = $value;
		}

		return $resolved;
	}

	public static function format( string $template, array $tokens = [] ): string {
		if ( [] === $tokens ) {
			return $template;
		}

		$replacements = [];

		foreach ( $tokens as $token => $value ) {
			$replacements[ '{' . trim( (string) $token, '{}' ) . '}' ] = is_scalar( $value ) ? (string) $value : '';
		}

		return strtr( $template, $replacements );
	}

	private static function sanitize_field_value( mixed $value, string $type ): string {
		$value = self::scalar_to_string( $value );

		return 'textarea' === $type
			? trim( sanitize_textarea_field( $value ) )
			: trim( sanitize_text_field( $value ) );
	}

	private static function scalar_to_string( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function general_definitions(): array {
		return [
			'setup_notice_title'     => [
				'label'       => __( 'Setup notice heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'general',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Plan Your Day setup needed', 'plan-your-day' ),
			],
			'setup_notice_body'      => [
				'label'       => __( 'Setup notice message', 'plan-your-day' ),
				'description' => __( 'Use {settings} where the missing setting list should appear.', 'plan-your-day' ),
				'group'       => 'general',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner needs required settings before it can render: {settings}.', 'plan-your-day' ),
			],
			'setup_notice_link'      => [
				'label'       => __( 'Setup notice link label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'general',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Open Plan Your Day settings', 'plan-your-day' ),
			],
			'hero_eyebrow'           => [
				'label'       => __( 'Top section eyebrow', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this short label above the main heading.', 'plan-your-day' ),
				'group'       => 'general',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Trip builder', 'plan-your-day' ),
			],
			'hero_title'             => [
				'label'       => __( 'Main planner heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'general',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Plan Your Day', 'plan-your-day' ),
			],
			'hero_intro'             => [
				'label'       => __( 'Top section intro', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide the introductory helper sentence.', 'plan-your-day' ),
				'group'       => 'general',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Search by category, choose real places from Google, then turn your picks into a walking trip.', 'plan-your-day' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function starting_point_definitions(): array {
		return [
			'start_card_heading'                 => [
				'label'       => __( 'Starting point heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Starting point', 'plan-your-day' ),
			],
			'start_card_help'                    => [
				'label'       => __( 'Starting point helper text', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper sentence.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Choose where the trip starts.', 'plan-your-day' ),
			],
			'start_mode_legend'                  => [
				'label'       => __( 'Starting point options label', 'plan-your-day' ),
				'description' => __( 'Used for screen readers.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Starting point mode', 'plan-your-day' ),
			],
			'start_mode_current_label'           => [
				'label'       => __( 'Current location option label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Current location handoff', 'plan-your-day' ),
			],
			'start_mode_current_description'     => [
				'label'       => __( 'Current location option helper text', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper line.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Use the configured default location for on-page previews. Google Maps can start from the visitor\'s current location during handoff.', 'plan-your-day' ),
			],
			'start_mode_default_description'     => [
				'label'       => __( 'Default location option helper text', 'plan-your-day' ),
				'description' => __( 'Use {default_location} where the configured default location label should appear. Leave blank to hide this helper line.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Start from {default_location}.', 'plan-your-day' ),
			],
			'start_mode_custom_label'            => [
				'label'       => __( 'Custom start option label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Custom starting point', 'plan-your-day' ),
			],
			'start_mode_custom_description'      => [
				'label'       => __( 'Custom start option helper text', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper line.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Enter a hotel name, landmark, or street address.', 'plan-your-day' ),
			],
			'custom_start_label'                 => [
				'label'       => __( 'Custom start field label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Custom starting point', 'plan-your-day' ),
			],
			'custom_start_placeholder'           => [
				'label'       => __( 'Custom start placeholder', 'plan-your-day' ),
				'description' => __( 'Leave blank to remove the placeholder text.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Hotel name or street address', 'plan-your-day' ),
			],
			'update_results_button'              => [
				'label'       => __( 'Update results button', 'plan-your-day' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Update results', 'plan-your-day' ),
			],
			'start_current_message'              => [
				'label'       => __( 'Current location status message', 'plan-your-day' ),
				'description' => __( 'Shown in the planner status message list when current location handoff is active.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The on-page results and preview use the configured default starting point. Google Maps will start from the visitor\'s current location during handoff.', 'plan-your-day' ),
			],
			'start_custom_missing_message'       => [
				'label'       => __( 'Missing custom start warning', 'plan-your-day' ),
				'description' => __( 'Shown in the planner status message list when custom start mode is selected without an address.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Add a custom address to replace the default fallback before finalizing the trip start.', 'plan-your-day' ),
			],
			'start_current_handoff_label'        => [
				'label'       => __( 'Current location summary label', 'plan-your-day' ),
				'description' => __( 'Shown in the trip summary when current location handoff is active.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Current location', 'plan-your-day' ),
			],
			'start_current_handoff_summary'      => [
				'label'       => __( 'Current location summary phrase', 'plan-your-day' ),
				'description' => __( 'Used inside route summary sentences.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'your current location', 'plan-your-day' ),
			],
			'start_default_fallback_label'       => [
				'label'       => __( 'Default fallback summary label', 'plan-your-day' ),
				'description' => __( 'Shown in the trip summary when custom start mode is selected without an address.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Default location fallback', 'plan-your-day' ),
			],
			'start_default_fallback_summary'     => [
				'label'       => __( 'Default fallback summary phrase', 'plan-your-day' ),
				'description' => __( 'Used inside route summary sentences when custom start mode is selected without an address.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'the default location until a custom starting point is provided', 'plan-your-day' ),
			],
			'start_default_location_fallback'    => [
				'label'       => __( 'Default location fallback label', 'plan-your-day' ),
				'description' => __( 'Used only if the configured default location label is missing.', 'plan-your-day' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Default location', 'plan-your-day' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function search_results_definitions(): array {
		return [
			'category_card_heading'                 => [
				'label'       => __( 'Search section heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'What are you looking for?', 'plan-your-day' ),
			],
			'category_card_help'                    => [
				'label'       => __( 'Search section helper text', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper sentence.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Search for any category or use a category shortcut to load Google results.', 'plan-your-day' ),
			],
			'category_search_label'                 => [
				'label'       => __( 'Category search field label', 'plan-your-day' ),
				'description' => __( 'Used for screen readers.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Search categories', 'plan-your-day' ),
			],
			'category_search_placeholder'           => [
				'label'       => __( 'Category search placeholder', 'plan-your-day' ),
				'description' => __( 'Leave blank to remove the placeholder text.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Search categories', 'plan-your-day' ),
			],
			'category_search_button'                => [
				'label'       => __( 'Category search button', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Search', 'plan-your-day' ),
			],
			'custom_results_heading'                => [
				'label'       => __( 'Custom search results heading', 'plan-your-day' ),
				'description' => __( 'Use {search} where the current search term should appear.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Results for {search}', 'plan-your-day' ),
			],
			'custom_results_description'            => [
				'label'       => __( 'Custom search results helper text', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper line.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Custom category search results.', 'plan-your-day' ),
			],
			'more_results_button'                  => [
				'label'       => __( 'More results button', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'More results', 'plan-your-day' ),
			],
			'view_in_google_maps'                   => [
				'label'       => __( 'Result map link label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'View in Google Maps', 'plan-your-day' ),
			],
			'view_place_in_google_maps_aria'        => [
				'label'       => __( 'Result map link screen reader label', 'plan-your-day' ),
				'description' => __( 'Use {place} where the place name should appear.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'View {place} in Google Maps', 'plan-your-day' ),
			],
			'in_trip'                               => [
				'label'       => __( 'Already added label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'In trip', 'plan-your-day' ),
			],
			'already_in_trip_aria'                  => [
				'label'       => __( 'Already added screen reader label', 'plan-your-day' ),
				'description' => __( 'Use {place} where the place name should appear.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{place} is already in the trip', 'plan-your-day' ),
			],
			'add_to_trip'                           => [
				'label'       => __( 'Add to trip button', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Add to trip', 'plan-your-day' ),
			],
			'add_waypoint_aria'                     => [
				'label'       => __( 'Add to trip screen reader label', 'plan-your-day' ),
				'description' => __( 'Use {place} where the place name should appear.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Add {place} to trip', 'plan-your-day' ),
			],
			'search_results_unavailable_heading'    => [
				'label'       => __( 'Results unavailable heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google results unavailable', 'plan-your-day' ),
			],
			'search_results_unavailable_body'       => [
				'label'       => __( 'Results unavailable message', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Google place results are unavailable right now. Try again later or open the Google Maps handoff link.', 'plan-your-day' ),
			],
			'search_results_prompt_heading'         => [
				'label'       => __( 'No search yet heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Search for any category', 'plan-your-day' ),
			],
			'search_results_prompt_body_with_categories' => [
				'label'       => __( 'No search yet message with category shortcuts', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper text when category shortcuts are available.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Use the search box or choose a category to load real place results.', 'plan-your-day' ),
			],
			'search_results_prompt_body_no_categories' => [
				'label'       => __( 'No search yet message without category shortcuts', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper text when no category shortcuts are available.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Use the search box to load real place results.', 'plan-your-day' ),
			],
			'no_matching_results_heading'           => [
				'label'       => __( 'No matching results heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'No matching Google results', 'plan-your-day' ),
			],
			'no_matching_results_body'              => [
				'label'       => __( 'No matching results message', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper text.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Try a different search or change the starting area.', 'plan-your-day' ),
			],
			'maps_link_label_search'                => [
				'label'       => __( 'Search-mode Google Maps link label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Explore in Google Maps', 'plan-your-day' ),
			],
			'preview_mode_label_search'             => [
				'label'       => __( 'Search-mode preview label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google place search', 'plan-your-day' ),
			],
			'overview_initial_with_categories'      => [
				'label'       => __( 'Initial search overview with category shortcuts', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this overview sentence in the trip summary.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Search for any category or pick one below to load Google results, then add exact places to your trip.', 'plan-your-day' ),
			],
			'search_results_count_single'           => [
				'label'       => __( 'Single result count label', 'plan-your-day' ),
				'description' => __( 'Use {count} where the number of results should appear.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} Google result', 'plan-your-day' ),
			],
			'search_results_count_plural'           => [
				'label'       => __( 'Plural result count label', 'plan-your-day' ),
				'description' => __( 'Use {count} where the number of results should appear.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} Google results', 'plan-your-day' ),
			],
			'no_results_loaded_label'               => [
				'label'       => __( 'No results loaded label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'No Google results loaded', 'plan-your-day' ),
			],
			'overview_browse_search'                => [
				'label'       => __( 'Search overview after results load', 'plan-your-day' ),
				'description' => __( 'Use {search} for the active search and {start} for the current start summary. Leave blank to hide this overview sentence.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Browsing Google results for {search} near {start}. Add any result to start building a walking trip.', 'plan-your-day' ),
			],
			'search_preview_key_warning'            => [
				'label'       => __( 'Search preview key warning', 'plan-your-day' ),
				'description' => __( 'Shown when map preview is enabled without a valid Google Maps Embed API key.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Add a valid Google Maps Embed API key before relying on the on-site search preview.', 'plan-your-day' ),
			],
			'overview_initial_no_categories'        => [
				'label'       => __( 'Initial search overview without category shortcuts', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this overview sentence when no category shortcuts are available.', 'plan-your-day' ),
				'group'       => 'search_results',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Search for any category to load Google results, then add exact places to your trip.', 'plan-your-day' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function trip_definitions(): array {
		return [
			'trip_empty_heading'                  => [
				'label'       => __( 'Empty trip heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Start building the trip', 'plan-your-day' ),
			],
			'trip_empty_body'                     => [
				'label'       => __( 'Empty trip message', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper text.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Search Google by category, then add the exact places you want as walking-trip waypoints.', 'plan-your-day' ),
			],
			'trip_card_heading'                   => [
				'label'       => __( 'Trip section heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip waypoints', 'plan-your-day' ),
			],
			'trip_card_help'                      => [
				'label'       => __( 'Trip section helper text', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper sentence.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Add exact places from Google, then use the move controls to set the walking trip order.', 'plan-your-day' ),
			],
			'clear_trip'                          => [
				'label'       => __( 'Clear trip button', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Clear trip', 'plan-your-day' ),
			],
			'move_up'                             => [
				'label'       => __( 'Move up button', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Move up', 'plan-your-day' ),
			],
			'move_down'                           => [
				'label'       => __( 'Move down button', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Move down', 'plan-your-day' ),
			],
			'move_waypoint_up_aria'               => [
				'label'       => __( 'Move up screen reader label', 'plan-your-day' ),
				'description' => __( 'Use {place} where the place name should appear.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Move {place} up in the trip', 'plan-your-day' ),
			],
			'move_waypoint_down_aria'             => [
				'label'       => __( 'Move down screen reader label', 'plan-your-day' ),
				'description' => __( 'Use {place} where the place name should appear.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Move {place} down in the trip', 'plan-your-day' ),
			],
			'remove_waypoint_label'               => [
				'label'       => __( 'Remove waypoint button', 'plan-your-day' ),
				'description' => __( 'Use {place} where the place name should appear.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Remove {place}', 'plan-your-day' ),
			],
			'trip_not_started_label'              => [
				'label'       => __( 'Trip not started label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip not started', 'plan-your-day' ),
			],
			'trip_timeout_warning'                => [
				'label'       => __( 'Trip loading timeout warning', 'plan-your-day' ),
				'description' => __( 'Shown when some selected places cannot be loaded before the request deadline.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The trip preview stopped loading more places before the request timed out. Try again or remove a few stops.', 'plan-your-day' ),
			],
			'trip_place_skipped_warning'          => [
				'label'       => __( 'Skipped place warning', 'plan-your-day' ),
				'description' => __( 'Shown when one selected place fails to load from Google.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'One selected place could not be loaded from Google and was skipped.', 'plan-your-day' ),
			],
			'trip_repeated_errors_warning'        => [
				'label'       => __( 'Repeated place errors warning', 'plan-your-day' ),
				'description' => __( 'Shown when repeated Google place errors stop trip preview loading early.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The trip preview stopped loading more places after repeated Google place errors. Try again later or remove any invalid stops.', 'plan-your-day' ),
			],
			'unresolved_waypoint_label'           => [
				'label'       => __( 'Unresolved waypoint label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Selected place needs attention', 'plan-your-day' ),
			],
			'unresolved_waypoint_address'         => [
				'label'       => __( 'Unresolved waypoint message', 'plan-your-day' ),
				'description' => __( 'Shown in place of the address when Google cannot load a selected place.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Google could not load this place right now. It is still selected and can be retried, moved, or removed.', 'plan-your-day' ),
			],
			'trip_count_single'                   => [
				'label'       => __( 'Single waypoint count label', 'plan-your-day' ),
				'description' => __( 'Use {count} where the number of selected waypoints should appear.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} waypoint selected', 'plan-your-day' ),
			],
			'trip_count_plural'                   => [
				'label'       => __( 'Plural waypoint count label', 'plan-your-day' ),
				'description' => __( 'Use {count} where the number of selected waypoints should appear.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} waypoints selected', 'plan-your-day' ),
			],
			'trip_route_unresolved'               => [
				'label'       => __( 'Unresolved trip route summary', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'One or more selected places still need to load before the walking trip can be previewed.', 'plan-your-day' ),
			],
			'trip_overview_template'              => [
				'label'       => __( 'Trip summary overview template', 'plan-your-day' ),
				'description' => __( 'Use {trip_count} for the waypoint count label and {route_description} for the route sentence. Leave blank to hide this overview sentence.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( '{trip_count}. {route_description}', 'plan-your-day' ),
			],
			'trip_unavailable_until_loaded_warning' => [
				'label'       => __( 'Trip preview unavailable warning', 'plan-your-day' ),
				'description' => __( 'Shown when one or more selected places are still unresolved.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The trip preview and Google Maps handoff will stay unavailable until every selected place loads successfully.', 'plan-your-day' ),
			],
			'trip_preview_key_warning'            => [
				'label'       => __( 'Trip preview key warning', 'plan-your-day' ),
				'description' => __( 'Shown when trip preview is enabled without a valid Google Maps Embed API key.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Add a valid Google Maps Embed API key before relying on the on-site trip preview.', 'plan-your-day' ),
			],
			'maps_link_label_trip'                => [
				'label'       => __( 'Trip-mode Google Maps link label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Open trip in Google Maps', 'plan-your-day' ),
			],
			'preview_mode_label_trip'             => [
				'label'       => __( 'Trip-mode preview label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Walking directions', 'plan-your-day' ),
			],
			'route_description_direct'            => [
				'label'       => __( 'Direct route summary template', 'plan-your-day' ),
				'description' => __( 'Use {start} for the trip start and {destination} for the final stop.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Walking directions run from {start} to {destination}.', 'plan-your-day' ),
			],
			'route_description_via'               => [
				'label'       => __( 'Route summary with stops template', 'plan-your-day' ),
				'description' => __( 'Use {start} for the trip start, {destination} for the final stop, and {via} for the intermediate stops.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Walking directions run from {start} to {destination} via {via}.', 'plan-your-day' ),
			],
			'label_list_pair'                     => [
				'label'       => __( 'Two-stop list template', 'plan-your-day' ),
				'description' => __( 'Use {first} and {second}.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{first} and {second}', 'plan-your-day' ),
			],
			'label_list_many'                     => [
				'label'       => __( 'Multi-stop list template', 'plan-your-day' ),
				'description' => __( 'Use {list} for the earlier stops and {last} for the final stop in the list.', 'plan-your-day' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{list}, and {last}', 'plan-your-day' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function preview_definitions(): array {
		return [
			'not_selected'                         => [
				'label'       => __( 'Not selected label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Not selected', 'plan-your-day' ),
			],
			'preview_card_heading'                 => [
				'label'       => __( 'Preview section heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip preview', 'plan-your-day' ),
			],
			'preview_card_help'                    => [
				'label'       => __( 'Preview section helper text', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper sentence.', 'plan-your-day' ),
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'The map starts as a Google place search and switches to walking directions once you add exact waypoints.', 'plan-your-day' ),
			],
			'preview_iframe_title'                 => [
				'label'       => __( 'Map iframe title', 'plan-your-day' ),
				'description' => __( 'Used as the accessible title for the embedded Google Map.', 'plan-your-day' ),
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google Maps trip preview', 'plan-your-day' ),
			],
			'summary_eyebrow'                      => [
				'label'       => __( 'Trip summary eyebrow', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this short label above the trip summary.', 'plan-your-day' ),
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Trip summary', 'plan-your-day' ),
			],
			'summary_active_search_label'          => [
				'label'       => __( 'Summary active search label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Active search', 'plan-your-day' ),
			],
			'summary_results_label'                => [
				'label'       => __( 'Summary results label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google results', 'plan-your-day' ),
			],
			'summary_start_label'                  => [
				'label'       => __( 'Summary start label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google Maps start', 'plan-your-day' ),
			],
			'summary_map_mode_label'               => [
				'label'       => __( 'Summary map mode label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Map mode', 'plan-your-day' ),
			],
			'summary_open_maps_label'              => [
				'label'       => __( 'Summary Google Maps label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Open in Google Maps', 'plan-your-day' ),
			],
			'preview_prompt_heading'               => [
				'label'       => __( 'No preview yet heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Start with a category search', 'plan-your-day' ),
			],
			'preview_prompt_body_with_categories'  => [
				'label'       => __( 'No preview yet message with category shortcuts', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper text when category shortcuts are available.', 'plan-your-day' ),
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Use the search box or choose a category to load Google results, then add the places you want to turn into trip waypoints.', 'plan-your-day' ),
			],
			'preview_prompt_body_no_categories'    => [
				'label'       => __( 'No preview yet message without category shortcuts', 'plan-your-day' ),
				'description' => __( 'Leave blank to hide this helper text when no category shortcuts are available.', 'plan-your-day' ),
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Use the search box to load Google results, then add the places you want to turn into trip waypoints.', 'plan-your-day' ),
			],
			'search_preview_unavailable_heading'   => [
				'label'       => __( 'Search preview unavailable heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Search preview unavailable', 'plan-your-day' ),
			],
			'search_preview_unavailable_body'      => [
				'label'       => __( 'Search preview unavailable message', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The on-page map preview needs a valid Google Maps Embed API key. The Google Maps search link still works.', 'plan-your-day' ),
			],
			'trip_preview_unavailable_heading'     => [
				'label'       => __( 'Trip preview unavailable heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip preview unavailable', 'plan-your-day' ),
			],
			'trip_preview_unavailable_body'        => [
				'label'       => __( 'Trip preview unavailable message', 'plan-your-day' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The on-page trip preview needs a valid Google Maps Embed API key. The Google Maps handoff link still works.', 'plan-your-day' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function status_definitions(): array {
		return [
			'hydration_loading_message'              => [
				'label'       => __( 'Verified loading message', 'plan-your-day' ),
				'description' => __( 'Shown while the planner reloads saved state through the verified request path.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => true,
				'default'     => __( 'Loading planner state through a verified request.', 'plan-your-day' ),
			],
			'loading_results_label'                 => [
				'label'       => __( 'Loading results count label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading Google results...', 'plan-your-day' ),
			],
			'loading_results_heading'               => [
				'label'       => __( 'Loading results heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading Google results', 'plan-your-day' ),
			],
			'loading_results_body'                  => [
				'label'       => __( 'Loading results message', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner is loading your saved search through the secure request path.', 'plan-your-day' ),
			],
			'loading_trip_count'                    => [
				'label'       => __( 'Loading trip count label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading trip waypoints...', 'plan-your-day' ),
			],
			'loading_trip_heading'                  => [
				'label'       => __( 'Loading trip heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading trip waypoints', 'plan-your-day' ),
			],
			'loading_trip_body'                     => [
				'label'       => __( 'Loading trip message', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner is loading your saved trip through the secure request path.', 'plan-your-day' ),
			],
			'loading_trip_preview_mode'             => [
				'label'       => __( 'Loading trip preview label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading trip preview', 'plan-your-day' ),
			],
			'loading_trip_preview_heading'          => [
				'label'       => __( 'Loading trip preview heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading trip preview', 'plan-your-day' ),
			],
			'loading_trip_preview_body'             => [
				'label'       => __( 'Loading trip preview message', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner is loading your saved trip through the secure request path.', 'plan-your-day' ),
			],
			'loading_search_preview_mode'           => [
				'label'       => __( 'Loading search preview label', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading search preview', 'plan-your-day' ),
			],
			'loading_search_preview_heading'        => [
				'label'       => __( 'Loading search preview heading', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading search preview', 'plan-your-day' ),
			],
			'loading_search_preview_body'           => [
				'label'       => __( 'Loading search preview message', 'plan-your-day' ),
				'description' => '',
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner is loading your saved search through the secure request path.', 'plan-your-day' ),
			],
			'request_failed'                        => [
				'label'       => __( 'Generic request failure message', 'plan-your-day' ),
				'description' => __( 'Shown when a frontend request fails without a more specific message.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner request could not be completed. Refresh the page and try again.', 'plan-your-day' ),
			],
			'request_verification_failed'           => [
				'label'       => __( 'Request verification failure message', 'plan-your-day' ),
				'description' => __( 'Shown when a planner request cannot be verified.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner request could not be verified. Refresh the page and try again.', 'plan-your-day' ),
			],
			'rate_limited'                          => [
				'label'       => __( 'Rate limit message', 'plan-your-day' ),
				'description' => __( 'Shown when the public planner rate limit is reached.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Planner requests are temporarily limited. Please wait a minute and try again.', 'plan-your-day' ),
			],
			'results_updated_announcement'          => [
				'label'       => __( 'Results updated announcement', 'plan-your-day' ),
				'description' => __( 'Announced to screen readers after search results change.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Results updated.', 'plan-your-day' ),
			],
			'loading_more_results_status'          => [
				'label'       => __( 'Loading more results status', 'plan-your-day' ),
				'description' => __( 'Announced while more search results are loading.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading more results...', 'plan-your-day' ),
			],
			'loaded_more_results_status'           => [
				'label'       => __( 'Loaded more results status', 'plan-your-day' ),
				'description' => __( 'Use {count} where the number of newly appended results should appear.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} more results loaded.', 'plan-your-day' ),
			],
			'no_more_results_status'               => [
				'label'       => __( 'No more results status', 'plan-your-day' ),
				'description' => __( 'Announced when no further paginated results are available.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'No more results are available.', 'plan-your-day' ),
			],
			'load_more_results_error_status'       => [
				'label'       => __( 'Load more error status', 'plan-your-day' ),
				'description' => __( 'Shown and announced when the next page of search results cannot be loaded.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => true,
				'default'     => __( 'Could not load more results. Please try again.', 'plan-your-day' ),
			],
			'category_results_expanded_announcement' => [
				'label'       => __( 'Category expanded announcement', 'plan-your-day' ),
				'description' => __( 'Use {category} where the category label should appear.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{category} results expanded.', 'plan-your-day' ),
			],
			'category_results_collapsed_announcement' => [
				'label'       => __( 'Category collapsed announcement', 'plan-your-day' ),
				'description' => __( 'Use {category} where the category label should appear.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{category} results collapsed.', 'plan-your-day' ),
			],
			'custom_results_expanded_announcement' => [
				'label'       => __( 'Custom results expanded announcement', 'plan-your-day' ),
				'description' => __( 'Announced to screen readers when the custom results panel expands.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Custom search results expanded.', 'plan-your-day' ),
			],
			'custom_results_collapsed_announcement' => [
				'label'       => __( 'Custom results collapsed announcement', 'plan-your-day' ),
				'description' => __( 'Announced to screen readers when the custom results panel collapses.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Custom search results collapsed.', 'plan-your-day' ),
			],
			'trip_updated_announcement'            => [
				'label'       => __( 'Trip updated announcement', 'plan-your-day' ),
				'description' => __( 'Announced to screen readers after trip waypoints change.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip updated.', 'plan-your-day' ),
			],
			'starting_point_updated_announcement'  => [
				'label'       => __( 'Starting point updated announcement', 'plan-your-day' ),
				'description' => __( 'Announced to screen readers after the starting point changes.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Starting point updated.', 'plan-your-day' ),
			],
			'open_maps_disabled_announcement'      => [
				'label'       => __( 'Open in Google Maps unavailable announcement', 'plan-your-day' ),
				'description' => __( 'Announced if the Google Maps handoff link is disabled.', 'plan-your-day' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => true,
				'default'     => __( 'Add at least one waypoint before opening the trip in Google Maps.', 'plan-your-day' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function distance_definitions(): array {
		return [
			'distance_under_threshold_without_reference' => [
				'label'       => __( 'Very short distance without start label', 'plan-your-day' ),
				'description' => __( 'Use {unit} where the distance unit abbreviation should appear.', 'plan-your-day' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Less than 0.1 {unit} away', 'plan-your-day' ),
			],
			'distance_under_threshold_with_reference' => [
				'label'       => __( 'Very short distance with start label', 'plan-your-day' ),
				'description' => __( 'Use {unit} for the distance unit abbreviation and {start} for the start label.', 'plan-your-day' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Less than 0.1 {unit} from {start}', 'plan-your-day' ),
			],
			'distance_approx_without_reference'    => [
				'label'       => __( 'Approximate distance without start label', 'plan-your-day' ),
				'description' => __( 'Use {distance} for the number and {unit} for the distance unit abbreviation.', 'plan-your-day' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Approx. {distance} {unit} away', 'plan-your-day' ),
			],
			'distance_approx_with_reference'       => [
				'label'       => __( 'Approximate distance with start label', 'plan-your-day' ),
				'description' => __( 'Use {distance} for the number, {unit} for the distance unit abbreviation, and {start} for the start label.', 'plan-your-day' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Approx. {distance} {unit} from {start}', 'plan-your-day' ),
			],
		];
	}
}
