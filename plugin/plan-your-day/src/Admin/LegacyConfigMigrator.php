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

	public function has_legacy_config(): bool {
		$legacy_config = $this->get_legacy_config();

		return '' !== $legacy_config['default_location_label']
			|| '' !== $legacy_config['default_location_address']
			|| '' !== $legacy_config['google_maps_embed_api_key']
			|| '' !== $legacy_config['google_places_api_key']
			|| [] !== $legacy_config['categories'];
	}

	public function migration_is_recommended(): bool {
		return $this->has_legacy_config() && $this->plugin_settings_are_effectively_empty();
	}

	public function get_legacy_summary(): array {
		$legacy_config = $this->get_legacy_config();

		return [
			'has_default_location' => '' !== $legacy_config['default_location_label'] || '' !== $legacy_config['default_location_address'],
			'has_google_keys'      => '' !== $legacy_config['google_maps_embed_api_key'] || '' !== $legacy_config['google_places_api_key'],
			'category_count'       => count( $legacy_config['categories'] ),
		];
	}

	public function import(): array {
		$legacy_config     = $this->get_legacy_config();
		$current_settings  = $this->settings->get_all();
		$merged_settings   = $current_settings;
		$imported_fields   = 0;
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
		$runtime_defaults = function_exists( 'dkc_plan_get_runtime_defaults' ) ? (array) dkc_plan_get_runtime_defaults() : [];
		$start_points     = function_exists( 'dkc_plan_get_start_points' ) ? (array) dkc_plan_get_start_points() : [];
		$legacy_categories = function_exists( 'dkc_plan_get_category_catalog' ) ? (array) dkc_plan_get_category_catalog() : [];
		$default_start_key = isset( $start_points['pier'] ) ? 'pier' : ( isset( $start_points['default'] ) ? 'default' : '' );

		return [
			'default_location_label'    => '' !== $default_start_key ? Settings::sanitize_plain_text( (string) ( $start_points[ $default_start_key ]['label'] ?? '' ) ) : '',
			'default_location_address'  => Settings::sanitize_plain_textarea( (string) ( $runtime_defaults['pier_address'] ?? '' ) ),
			'default_location_latitude' => '',
			'default_location_longitude' => '',
			'default_location_place_id' => '',
			'allowed_start_modes'       => $this->legacy_allowed_start_modes( array_keys( $start_points ) ),
			'google_maps_embed_api_key' => $this->legacy_google_api_key(
				[ 'dkc_plan_get_google_embed_api_key' ],
				[ 'DKC_PLAN_GOOGLE_EMBED_API_KEY', 'DKC_PLAN_GOOGLE_API_KEY' ]
			),
			'google_places_api_key'     => $this->legacy_google_api_key(
				[ 'dkc_plan_get_google_places_api_key' ],
				[ 'DKC_PLAN_GOOGLE_PLACES_API_KEY', 'DKC_PLAN_GOOGLE_API_KEY' ]
			),
			'google_geocoding_api_key'  => $this->legacy_google_api_key(
				[ 'dkc_plan_get_google_geocoding_api_key' ],
				[ 'DKC_PLAN_GOOGLE_GEOCODING_API_KEY' ]
			),
			'categories'                => $this->legacy_categories( $legacy_categories ),
		];
	}

	private function legacy_allowed_start_modes( array $legacy_modes ): array {
		$mapped_modes = [];

		foreach ( $legacy_modes as $legacy_mode ) {
			$mapped_mode = match ( sanitize_key( (string) $legacy_mode ) ) {
				'current' => Settings::START_MODE_CURRENT,
				'custom'  => Settings::START_MODE_CUSTOM,
				'pier', 'default' => Settings::START_MODE_DEFAULT,
				default => '',
			};

			if ( '' !== $mapped_mode && ! in_array( $mapped_mode, $mapped_modes, true ) ) {
				$mapped_modes[] = $mapped_mode;
			}
		}

		return [] !== $mapped_modes ? $mapped_modes : [ Settings::START_MODE_DEFAULT, Settings::START_MODE_CUSTOM ];
	}

	private function legacy_google_api_key( array $callbacks, array $constants ): string {
		foreach ( $callbacks as $callback ) {
			if ( function_exists( $callback ) ) {
				$api_key = Settings::sanitize_api_key( $callback() );

				if ( '' !== $api_key ) {
					return $api_key;
				}
			}
		}

		foreach ( $constants as $constant_name ) {
			if ( defined( $constant_name ) ) {
				$api_key = Settings::sanitize_api_key( constant( $constant_name ) );

				if ( '' !== $api_key ) {
					return $api_key;
				}
			}
		}

		return '';
	}

	private function legacy_categories( array $legacy_categories ): array {
		$categories = [];
		$sort_order = 10;

		foreach ( $legacy_categories as $slug => $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$categories[] = [
				'slug'        => Settings::sanitize_plain_text( (string) $slug ),
				'label'       => Settings::sanitize_plain_text( (string) ( $category['label'] ?? '' ) ),
				'description' => Settings::sanitize_plain_textarea( (string) ( $category['description'] ?? '' ) ),
				'text_query'  => Settings::sanitize_plain_text( (string) ( $category['text_query'] ?? '' ) ),
				'enabled'     => true,
				'sort_order'  => $sort_order,
			];
			$sort_order += 10;
		}

		return Settings::sanitize_categories( $categories );
	}
}
