<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Admin;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class LegacyConfigMigrator {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function import_if_recommended(): array {
		if ( ! $this->migration_is_recommended() ) {
			return [
				'imported_fields'     => 0,
				'imported_categories' => 0,
			];
		}

		return $this->import();
	}

	public function migration_is_recommended(): bool {
		return $this->has_legacy_config() && $this->plugin_settings_are_effectively_empty();
	}

	public function has_legacy_config(): bool {
		$legacy_config = $this->get_legacy_config();

		return '' !== $legacy_config['default_location_label']
			|| '' !== $legacy_config['default_location_address']
			|| '' !== $legacy_config['google_maps_embed_api_key']
			|| '' !== $legacy_config['google_places_api_key']
			|| '' !== $legacy_config['google_geocoding_api_key']
			|| [] !== $legacy_config['categories'];
	}

	public function import(): array {
		$legacy_config       = $this->get_legacy_config();
		$current_settings    = $this->settings->get_all();
		$merged_settings     = $current_settings;
		$imported_fields     = 0;
		$imported_categories = 0;

		foreach ( [ 'default_location_label', 'default_location_address', 'default_location_place_id', 'google_maps_embed_api_key', 'google_places_api_key', 'google_geocoding_api_key' ] as $field_key ) {
			if ( '' === (string) $merged_settings[ $field_key ] && '' !== (string) $legacy_config[ $field_key ] ) {
				$merged_settings[ $field_key ] = $legacy_config[ $field_key ];
				++$imported_fields;
			}
		}

		foreach ( [ 'default_location_latitude', 'default_location_longitude' ] as $field_key ) {
			if ( '' === (string) $merged_settings[ $field_key ] && '' !== (string) $legacy_config[ $field_key ] ) {
				$merged_settings[ $field_key ] = $legacy_config[ $field_key ];
				++$imported_fields;
			}
		}

		if ( [] === (array) $merged_settings['categories'] && [] !== $legacy_config['categories'] ) {
			$merged_settings['categories'] = $legacy_config['categories'];
			$imported_categories           = count( $legacy_config['categories'] );
		}

		if ( [ Settings::START_MODE_DEFAULT, Settings::START_MODE_CUSTOM ] === array_values( (array) $merged_settings['allowed_start_modes'] ) && [] !== $legacy_config['allowed_start_modes'] ) {
			$merged_settings['allowed_start_modes'] = $legacy_config['allowed_start_modes'];
			++$imported_fields;
		}

		update_option( Settings::OPTION_NAME, Settings::sanitize( $merged_settings ) );

		return [
			'imported_fields'     => $imported_fields,
			'imported_categories' => $imported_categories,
		];
	}

	private function plugin_settings_are_effectively_empty(): bool {
		$settings = get_option( Settings::OPTION_NAME, [] );

		if ( ! is_array( $settings ) || [] === $settings ) {
			return true;
		}

		$settings = Settings::sanitize( $settings );

		return '' === $settings['default_location_label']
			&& '' === $settings['default_location_address']
			&& '' === $settings['google_maps_embed_api_key']
			&& '' === $settings['google_places_api_key']
			&& '' === $settings['google_geocoding_api_key']
			&& [] === $settings['categories'];
	}

	private function get_legacy_config(): array {
		$legacy_config = $this->load_legacy_config_file();

		if ( function_exists( 'apply_filters' ) ) {
			$legacy_config = apply_filters( 'plan_your_day_legacy_settings', $legacy_config );
		}

		if ( ! is_array( $legacy_config ) ) {
			$legacy_config = [];
		}

		return [
			'default_location_label'     => Settings::sanitize_plain_text( $legacy_config['default_location_label'] ?? '' ),
			'default_location_address'   => Settings::sanitize_plain_textarea( $legacy_config['default_location_address'] ?? '' ),
			'default_location_latitude'  => Settings::sanitize_coordinate( $legacy_config['default_location_latitude'] ?? '', -90, 90 ),
			'default_location_longitude' => Settings::sanitize_coordinate( $legacy_config['default_location_longitude'] ?? '', -180, 180 ),
			'default_location_place_id'  => Settings::sanitize_place_id( $legacy_config['default_location_place_id'] ?? '' ),
			'allowed_start_modes'        => $this->legacy_allowed_start_modes( $legacy_config['allowed_start_modes'] ?? [] ),
			'google_maps_embed_api_key'  => Settings::sanitize_api_key( $legacy_config['google_maps_embed_api_key'] ?? '' ),
			'google_places_api_key'      => Settings::sanitize_api_key( $legacy_config['google_places_api_key'] ?? '' ),
			'google_geocoding_api_key'   => Settings::sanitize_api_key( $legacy_config['google_geocoding_api_key'] ?? '' ),
			'categories'                 => $this->legacy_categories( $legacy_config['categories'] ?? [] ),
		];
	}

	private function load_legacy_config_file(): array {
		if ( ! defined( 'PLAN_YOUR_DAY_LEGACY_CONFIG_FILE' ) ) {
			return [];
		}

		$config_file = (string) constant( 'PLAN_YOUR_DAY_LEGACY_CONFIG_FILE' );

		if ( '' === $config_file || ! is_readable( $config_file ) ) {
			return [];
		}

		if ( ! defined( 'PLAN_YOUR_DAY_LEGACY_BOOTSTRAP' ) ) {
			define( 'PLAN_YOUR_DAY_LEGACY_BOOTSTRAP', true );
		}

		$legacy_config = require $config_file;

		return is_array( $legacy_config ) ? $legacy_config : [];
	}

	private function legacy_allowed_start_modes( array $legacy_modes ): array {
		$mapped_modes = [];

		foreach ( $legacy_modes as $legacy_mode ) {
			$mapped_mode = sanitize_key( (string) $legacy_mode );

			if ( in_array( $mapped_mode, array_keys( Settings::start_mode_choices() ), true ) && ! in_array( $mapped_mode, $mapped_modes, true ) ) {
				$mapped_modes[] = $mapped_mode;
			}
		}

		return [] !== $mapped_modes ? $mapped_modes : [ Settings::START_MODE_DEFAULT, Settings::START_MODE_CUSTOM ];
	}

	private function legacy_categories( array $legacy_categories ): array {
		$categories = [];
		$sort_order = 10;

		foreach ( $legacy_categories as $slug => $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$categories[] = [
				'slug'        => Settings::sanitize_plain_text(
					is_string( $slug ) && '' !== $slug
						? $slug
						: (string) ( $category['slug'] ?? '' )
				),
				'label'       => Settings::sanitize_plain_text( (string) ( $category['label'] ?? '' ) ),
				'description' => Settings::sanitize_plain_textarea( (string) ( $category['description'] ?? '' ) ),
				'text_query'  => Settings::sanitize_plain_text( (string) ( $category['text_query'] ?? '' ) ),
				'enabled'     => true === ( $category['enabled'] ?? true ),
				'sort_order'  => is_numeric( $category['sort_order'] ?? null ) ? (int) $category['sort_order'] : $sort_order,
			];
			$sort_order += 10;
		}

		return Settings::sanitize_categories( $categories );
	}
}
