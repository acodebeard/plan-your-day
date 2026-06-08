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
				'label'       => __( 'Top Section And Setup Notice', 'waypoints' ),
				'description' => __( 'Edit the main heading, intro text, and the fallback notice shown when required planner settings are missing.', 'waypoints' ),
			],
			'starting_point'  => [
				'label'       => __( 'Starting Point', 'waypoints' ),
				'description' => __( 'Edit the starting-point labels, helper copy, and custom start instructions. Helper text fields may be left blank to hide them.', 'waypoints' ),
			],
			'search_results'  => [
				'label'       => __( 'Search And Results', 'waypoints' ),
				'description' => __( 'Edit the search section heading and category search placeholder.', 'waypoints' ),
			],
			'trip'            => [
				'label'       => __( 'Trip Builder', 'waypoints' ),
				'description' => __( 'Edit waypoint labels, empty states, route summaries, and trip-related warnings.', 'waypoints' ),
			],
			'preview'         => [
				'label'       => __( 'Preview And Summary', 'waypoints' ),
				'description' => __( 'Edit the preview card, Google Maps handoff labels, and summary field names. Optional helper text may be blank.', 'waypoints' ),
			],
			'status_messages' => [
				'label'       => __( 'Loading, Errors, And Live Announcements', 'waypoints' ),
				'description' => __( 'Edit loading text, frontend error messages, and screen-reader announcements triggered by dynamic planner updates.', 'waypoints' ),
			],
			'distance'        => [
				'label'       => __( 'Distance Labels', 'waypoints' ),
				'description' => __( 'Edit the distance text shown on search results. Use {distance}, {unit}, and {start} where needed.', 'waypoints' ),
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
				'label'       => __( 'Setup notice heading', 'waypoints' ),
				'description' => '',
				'group'       => 'general',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Waypoints setup needed', 'waypoints' ),
			],
			'setup_notice_body'      => [
				'label'       => __( 'Setup notice message', 'waypoints' ),
				'description' => __( 'Use {settings} where the missing setting list should appear.', 'waypoints' ),
				'group'       => 'general',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner needs required settings before it can render: {settings}.', 'waypoints' ),
			],
			'setup_notice_link'      => [
				'label'       => __( 'Setup notice link label', 'waypoints' ),
				'description' => '',
				'group'       => 'general',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Open Waypoints settings', 'waypoints' ),
			],
			'hero_eyebrow'           => [
				'label'       => __( 'Top section eyebrow', 'waypoints' ),
				'description' => __( 'Leave blank to hide this short label above the main heading.', 'waypoints' ),
				'group'       => 'general',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Trip builder', 'waypoints' ),
			],
			'hero_title'             => [
				'label'       => __( 'Main planner heading', 'waypoints' ),
				'description' => '',
				'group'       => 'general',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Waypoints', 'waypoints' ),
			],
			'hero_intro'             => [
				'label'       => __( 'Top section intro', 'waypoints' ),
				'description' => __( 'Leave blank to hide the introductory helper sentence.', 'waypoints' ),
				'group'       => 'general',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Search by category, choose real places from Google, then turn your picks into a walking trip.', 'waypoints' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function starting_point_definitions(): array {
		return [
			'start_card_heading'                 => [
				'label'       => __( 'Starting point heading', 'waypoints' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Starting point', 'waypoints' ),
			],
			'start_mode_current_label'           => [
				'label'       => __( 'Current location option label', 'waypoints' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Current location handoff', 'waypoints' ),
			],
			'start_mode_custom_label'            => [
				'label'       => __( 'Custom start option label', 'waypoints' ),
				'description' => '',
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Custom starting point', 'waypoints' ),
			],
			'start_mode_custom_description'      => [
				'label'       => __( 'Custom start option helper text', 'waypoints' ),
				'description' => __( 'Leave blank to hide this helper line.', 'waypoints' ),
				'group'       => 'starting_point',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Enter a hotel name, landmark, or street address.', 'waypoints' ),
			],
			'custom_start_placeholder'           => [
				'label'       => __( 'Custom start placeholder', 'waypoints' ),
				'description' => __( 'Leave blank to remove the placeholder text.', 'waypoints' ),
				'group'       => 'starting_point',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Hotel name or street address', 'waypoints' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function search_results_definitions(): array {
		return [
			'category_card_heading'                 => [
				'label'       => __( 'Search section heading', 'waypoints' ),
				'description' => '',
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'What are you looking for?', 'waypoints' ),
			],
			'category_search_placeholder'           => [
				'label'       => __( 'Category search placeholder', 'waypoints' ),
				'description' => __( 'Leave blank to remove the placeholder text.', 'waypoints' ),
				'group'       => 'search_results',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Search categories', 'waypoints' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function trip_definitions(): array {
		return [
			'trip_empty_heading'                  => [
				'label'       => __( 'Empty trip heading', 'waypoints' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Start building the trip', 'waypoints' ),
			],
			'trip_empty_body'                     => [
				'label'       => __( 'Empty trip message', 'waypoints' ),
				'description' => __( 'Leave blank to hide this helper text.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Search Google by category, then add the exact places you want as walking-trip waypoints.', 'waypoints' ),
			],
			'trip_card_heading'                   => [
				'label'       => __( 'Trip section heading', 'waypoints' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip waypoints', 'waypoints' ),
			],
			'trip_card_help'                      => [
				'label'       => __( 'Trip section helper text', 'waypoints' ),
				'description' => __( 'Leave blank to hide this helper sentence.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => false,
				'default'     => __( 'Add exact places from Google, then use the move controls to set the walking trip order.', 'waypoints' ),
			],
			'move_waypoint_up_aria'               => [
				'label'       => __( 'Move up screen reader label', 'waypoints' ),
				'description' => __( 'Use {place} where the place name should appear.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Move {place} up in the trip', 'waypoints' ),
			],
			'move_waypoint_down_aria'             => [
				'label'       => __( 'Move down screen reader label', 'waypoints' ),
				'description' => __( 'Use {place} where the place name should appear.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Move {place} down in the trip', 'waypoints' ),
			],
			'remove_waypoint_label'               => [
				'label'       => __( 'Remove waypoint button', 'waypoints' ),
				'description' => __( 'Use {place} where the place name should appear.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Remove {place}', 'waypoints' ),
			],
			'trip_not_started_label'              => [
				'label'       => __( 'Trip not started label', 'waypoints' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip not started', 'waypoints' ),
			],
			'trip_timeout_warning'                => [
				'label'       => __( 'Trip loading timeout warning', 'waypoints' ),
				'description' => __( 'Shown when some selected places cannot be loaded before the request deadline.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The trip preview stopped loading more places before the request timed out. Try again or remove a few stops.', 'waypoints' ),
			],
			'trip_place_skipped_warning'          => [
				'label'       => __( 'Skipped place warning', 'waypoints' ),
				'description' => __( 'Shown when one selected place fails to load from Google.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'One selected place could not be loaded from Google and was skipped.', 'waypoints' ),
			],
			'trip_repeated_errors_warning'        => [
				'label'       => __( 'Repeated place errors warning', 'waypoints' ),
				'description' => __( 'Shown when repeated Google place errors stop trip preview loading early.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The trip preview stopped loading more places after repeated Google place errors. Try again later or remove any invalid stops.', 'waypoints' ),
			],
			'unresolved_waypoint_label'           => [
				'label'       => __( 'Unresolved waypoint label', 'waypoints' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Selected place needs attention', 'waypoints' ),
			],
			'unresolved_waypoint_address'         => [
				'label'       => __( 'Unresolved waypoint message', 'waypoints' ),
				'description' => __( 'Shown in place of the address when Google cannot load a selected place.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Google could not load this place right now. It is still selected and can be retried, moved, or removed.', 'waypoints' ),
			],
			'trip_count_single'                   => [
				'label'       => __( 'Single waypoint count label', 'waypoints' ),
				'description' => __( 'Use {count} where the number of selected waypoints should appear.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} waypoint selected', 'waypoints' ),
			],
			'trip_count_plural'                   => [
				'label'       => __( 'Plural waypoint count label', 'waypoints' ),
				'description' => __( 'Use {count} where the number of selected waypoints should appear.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} waypoints selected', 'waypoints' ),
			],
			'waypoint_status_empty'               => [
				'label'       => __( 'Fixed waypoint status empty label', 'waypoints' ),
				'description' => __( 'Shown in the fixed bottom status button before any waypoints are selected.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Add some waypoints!', 'waypoints' ),
			],
			'waypoint_status_single'              => [
				'label'       => __( 'Fixed waypoint status single label', 'waypoints' ),
				'description' => __( 'Use {count} where the number of selected waypoints should appear.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} waypoint added', 'waypoints' ),
			],
			'waypoint_status_plural'              => [
				'label'       => __( 'Fixed waypoint status plural label', 'waypoints' ),
				'description' => __( 'Use {count} where the number of selected waypoints should appear.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} waypoints added', 'waypoints' ),
			],
			'trip_route_unresolved'               => [
				'label'       => __( 'Unresolved trip route summary', 'waypoints' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'One or more selected places still need to load before the walking trip can be previewed.', 'waypoints' ),
			],
			'trip_overview_template'              => [
				'label'       => __( 'Trip summary overview template', 'waypoints' ),
				'description' => __( 'Use {trip_count} for the waypoint count label and {route_description} for the route sentence. Leave blank to hide this overview sentence.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( '{trip_count}. {route_description}', 'waypoints' ),
			],
			'trip_unavailable_until_loaded_warning' => [
				'label'       => __( 'Trip preview unavailable warning', 'waypoints' ),
				'description' => __( 'Shown when one or more selected places are still unresolved.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The trip preview and Google Maps handoff will stay unavailable until every selected place loads successfully.', 'waypoints' ),
			],
			'trip_preview_key_warning'            => [
				'label'       => __( 'Trip preview key warning', 'waypoints' ),
				'description' => __( 'Shown when trip preview is enabled without a valid Google Maps Embed API key.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Add a valid Google Maps Embed API key before relying on the on-site trip preview.', 'waypoints' ),
			],
			'maps_link_label_trip'                => [
				'label'       => __( 'Trip-mode Google Maps link label', 'waypoints' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Open trip in Google Maps', 'waypoints' ),
			],
			'preview_mode_label_trip'             => [
				'label'       => __( 'Trip-mode preview label', 'waypoints' ),
				'description' => '',
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Walking directions', 'waypoints' ),
			],
			'route_description_direct'            => [
				'label'       => __( 'Direct route summary template', 'waypoints' ),
				'description' => __( 'Use {start} for the trip start and {destination} for the final stop.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Walking directions run from {start} to {destination}.', 'waypoints' ),
			],
			'route_description_via'               => [
				'label'       => __( 'Route summary with stops template', 'waypoints' ),
				'description' => __( 'Use {start} for the trip start, {destination} for the final stop, and {via} for the intermediate stops.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Walking directions run from {start} to {destination} via {via}.', 'waypoints' ),
			],
			'label_list_pair'                     => [
				'label'       => __( 'Two-stop list template', 'waypoints' ),
				'description' => __( 'Use {first} and {second}.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{first} and {second}', 'waypoints' ),
			],
			'label_list_many'                     => [
				'label'       => __( 'Multi-stop list template', 'waypoints' ),
				'description' => __( 'Use {list} for the earlier stops and {last} for the final stop in the list.', 'waypoints' ),
				'group'       => 'trip',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{list}, and {last}', 'waypoints' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function preview_definitions(): array {
		return [
			'not_selected'                         => [
				'label'       => __( 'Not selected label', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Not selected', 'waypoints' ),
			],
			'preview_card_heading'                 => [
				'label'       => __( 'Preview section heading', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip preview', 'waypoints' ),
			],
			'preview_card_help'                    => [
				'label'       => __( 'Preview section helper text', 'waypoints' ),
				'description' => __( 'Leave blank to hide this helper sentence.', 'waypoints' ),
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'The map starts as a Google place search and switches to walking directions once you add exact waypoints.', 'waypoints' ),
			],
			'preview_iframe_title'                 => [
				'label'       => __( 'Map iframe title', 'waypoints' ),
				'description' => __( 'Used as the accessible title for the embedded Google Map.', 'waypoints' ),
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google Maps trip preview', 'waypoints' ),
			],
			'summary_eyebrow'                      => [
				'label'       => __( 'Trip summary eyebrow', 'waypoints' ),
				'description' => __( 'Leave blank to hide this short label above the trip summary.', 'waypoints' ),
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => false,
				'default'     => __( 'Trip summary', 'waypoints' ),
			],
			'summary_active_search_label'          => [
				'label'       => __( 'Summary active search label', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Active search', 'waypoints' ),
			],
			'summary_results_label'                => [
				'label'       => __( 'Summary results label', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google results', 'waypoints' ),
			],
			'summary_start_label'                  => [
				'label'       => __( 'Summary start label', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Google Maps start', 'waypoints' ),
			],
			'summary_map_mode_label'               => [
				'label'       => __( 'Summary map mode label', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Map mode', 'waypoints' ),
			],
			'summary_open_maps_label'              => [
				'label'       => __( 'Summary Google Maps label', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Open in Google Maps', 'waypoints' ),
			],
			'preview_prompt_heading'               => [
				'label'       => __( 'No preview yet heading', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Start with a category search', 'waypoints' ),
			],
			'preview_prompt_body_with_categories'  => [
				'label'       => __( 'No preview yet message with category shortcuts', 'waypoints' ),
				'description' => __( 'Leave blank to hide this helper text when category shortcuts are available.', 'waypoints' ),
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Use the search box or choose a category to load Google results, then add the places you want to turn into trip waypoints.', 'waypoints' ),
			],
			'preview_prompt_body_no_categories'    => [
				'label'       => __( 'No preview yet message without category shortcuts', 'waypoints' ),
				'description' => __( 'Leave blank to hide this helper text when no category shortcuts are available.', 'waypoints' ),
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => false,
				'default'     => __( 'Use the search box to load Google results, then add the places you want to turn into trip waypoints.', 'waypoints' ),
			],
			'search_preview_unavailable_heading'   => [
				'label'       => __( 'Search preview unavailable heading', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Search preview unavailable', 'waypoints' ),
			],
			'search_preview_unavailable_body'      => [
				'label'       => __( 'Search preview unavailable message', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The on-page map preview needs a valid Google Maps Embed API key. The Google Maps search link still works.', 'waypoints' ),
			],
			'trip_preview_unavailable_heading'     => [
				'label'       => __( 'Trip preview unavailable heading', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip preview unavailable', 'waypoints' ),
			],
			'trip_preview_unavailable_body'        => [
				'label'       => __( 'Trip preview unavailable message', 'waypoints' ),
				'description' => '',
				'group'       => 'preview',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The on-page trip preview needs a valid Google Maps Embed API key. The Google Maps handoff link still works.', 'waypoints' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function status_definitions(): array {
		return [
			'hydration_loading_message'              => [
				'label'       => __( 'Verified loading message', 'waypoints' ),
				'description' => __( 'Shown while the planner reloads saved state through the verified request path.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => true,
				'default'     => __( 'Loading planner state through a verified request.', 'waypoints' ),
			],
			'request_failed'                        => [
				'label'       => __( 'Generic request failure message', 'waypoints' ),
				'description' => __( 'Shown when a frontend request fails without a more specific message.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner request could not be completed. Refresh the page and try again.', 'waypoints' ),
			],
			'request_verification_failed'           => [
				'label'       => __( 'Request verification failure message', 'waypoints' ),
				'description' => __( 'Shown when a planner request cannot be verified.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'The planner request could not be verified. Refresh the page and try again.', 'waypoints' ),
			],
			'rate_limited'                          => [
				'label'       => __( 'Rate limit message', 'waypoints' ),
				'description' => __( 'Shown when the public planner rate limit is reached.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 3,
				'required'    => true,
				'default'     => __( 'Planner requests are temporarily limited. Please wait a minute and try again.', 'waypoints' ),
			],
			'results_updated_announcement'          => [
				'label'       => __( 'Results updated announcement', 'waypoints' ),
				'description' => __( 'Announced to screen readers after search results change.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Results updated.', 'waypoints' ),
			],
			'loading_more_results_status'          => [
				'label'       => __( 'Loading more results status', 'waypoints' ),
				'description' => __( 'Announced while more search results are loading.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Loading more results...', 'waypoints' ),
			],
			'loaded_more_results_status'           => [
				'label'       => __( 'Loaded more results status', 'waypoints' ),
				'description' => __( 'Use {count} where the number of newly appended results should appear.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{count} more results loaded.', 'waypoints' ),
			],
			'no_more_results_status'               => [
				'label'       => __( 'No more results status', 'waypoints' ),
				'description' => __( 'Announced when no further paginated results are available.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'No more results are available.', 'waypoints' ),
			],
			'load_more_results_error_status'       => [
				'label'       => __( 'Load more error status', 'waypoints' ),
				'description' => __( 'Shown and announced when the next page of search results cannot be loaded.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => true,
				'default'     => __( 'Could not load more results. Please try again.', 'waypoints' ),
			],
			'category_results_expanded_announcement' => [
				'label'       => __( 'Category expanded announcement', 'waypoints' ),
				'description' => __( 'Use {category} where the category label should appear.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{category} results expanded.', 'waypoints' ),
			],
			'category_results_collapsed_announcement' => [
				'label'       => __( 'Category collapsed announcement', 'waypoints' ),
				'description' => __( 'Use {category} where the category label should appear.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( '{category} results collapsed.', 'waypoints' ),
			],
			'custom_results_expanded_announcement' => [
				'label'       => __( 'Custom results expanded announcement', 'waypoints' ),
				'description' => __( 'Announced to screen readers when the custom results panel expands.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Custom search results expanded.', 'waypoints' ),
			],
			'custom_results_collapsed_announcement' => [
				'label'       => __( 'Custom results collapsed announcement', 'waypoints' ),
				'description' => __( 'Announced to screen readers when the custom results panel collapses.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Custom search results collapsed.', 'waypoints' ),
			],
			'trip_updated_announcement'            => [
				'label'       => __( 'Trip updated announcement', 'waypoints' ),
				'description' => __( 'Announced to screen readers after trip waypoints change.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Trip updated.', 'waypoints' ),
			],
			'starting_point_updated_announcement'  => [
				'label'       => __( 'Starting point updated announcement', 'waypoints' ),
				'description' => __( 'Announced to screen readers after the starting point changes.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Starting point updated.', 'waypoints' ),
			],
			'open_maps_disabled_announcement'      => [
				'label'       => __( 'Open in Google Maps unavailable announcement', 'waypoints' ),
				'description' => __( 'Announced if the Google Maps handoff link is disabled.', 'waypoints' ),
				'group'       => 'status_messages',
				'type'        => 'textarea',
				'rows'        => 2,
				'required'    => true,
				'default'     => __( 'Add at least one waypoint before opening the trip in Google Maps.', 'waypoints' ),
			],
		];
	}

	/**
	 * @return array<string, array{label: string, description: string, group: string, type: string, required: bool, default: string, rows?: int}>
	 */
	private static function distance_definitions(): array {
		return [
			'distance_under_threshold_without_reference' => [
				'label'       => __( 'Very short distance without start label', 'waypoints' ),
				'description' => __( 'Use {unit} where the distance unit abbreviation should appear.', 'waypoints' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Less than 0.1 {unit} away', 'waypoints' ),
			],
			'distance_under_threshold_with_reference' => [
				'label'       => __( 'Very short distance with start label', 'waypoints' ),
				'description' => __( 'Use {unit} for the distance unit abbreviation and {start} for the start label.', 'waypoints' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Less than 0.1 {unit} from {start}', 'waypoints' ),
			],
			'distance_approx_without_reference'    => [
				'label'       => __( 'Approximate distance without start label', 'waypoints' ),
				'description' => __( 'Use {distance} for the number and {unit} for the distance unit abbreviation.', 'waypoints' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Approx. {distance} {unit} away', 'waypoints' ),
			],
			'distance_approx_with_reference'       => [
				'label'       => __( 'Approximate distance with start label', 'waypoints' ),
				'description' => __( 'Use {distance} for the number, {unit} for the distance unit abbreviation, and {start} for the start label.', 'waypoints' ),
				'group'       => 'distance',
				'type'        => 'text',
				'required'    => true,
				'default'     => __( 'Approx. {distance} {unit} from {start}', 'waypoints' ),
			],
		];
	}
}
