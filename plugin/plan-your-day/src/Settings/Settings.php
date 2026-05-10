<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Settings;

use Acodebeard\PlanYourDay\Frontend\InterfaceCopy;
use Acodebeard\PlanYourDay\Planner\CategoryCatalog;

defined( 'ABSPATH' ) || exit;

final class Settings {
	private const EDITABLE_CATEGORY_SCHEMA_VERSION = 2;
	public const OPTION_NAME = 'plan_your_day_settings';
	public const OPTION_GROUP = 'plan_your_day_settings';
	public const PAGE_SLUG = 'plan-your-day';
	public const START_MODE_CURRENT = 'current';
	public const START_MODE_DEFAULT = 'default';
	public const START_MODE_CUSTOM = 'custom';
	public const DISTANCE_UNIT_MILES = 'miles';
	public const DISTANCE_UNIT_KILOMETERS = 'kilometers';
	private ?array $cached_settings = null;

	public function __construct() {
		add_action( 'update_option_' . self::OPTION_NAME, [ $this, 'flush_cache' ], 10, 0 );
		add_action( 'add_option_' . self::OPTION_NAME, [ $this, 'flush_cache' ], 10, 0 );
		add_action( 'delete_option_' . self::OPTION_NAME, [ $this, 'flush_cache' ], 10, 0 );
	}

	public static function defaults(): array {
		return [
			'default_location_label'          => '',
			'default_location_address'        => '',
			'default_location_latitude'       => '',
			'default_location_longitude'      => '',
			'default_location_place_id'       => '',
			'allowed_start_modes'             => [
				self::START_MODE_DEFAULT,
				self::START_MODE_CUSTOM,
			],
			'max_waypoints'                   => 8,
			'result_count'                    => 8,
			'distance_unit'                   => self::DISTANCE_UNIT_MILES,
			'map_preview_enabled'             => true,
			'maps_handoff_enabled'            => true,
			'use_preset_categories'           => true,
			'categories'                      => [],
			'google_maps_embed_api_key'       => '',
			'google_places_api_key'           => '',
			'google_geocoding_api_key'        => '',
			'google_api_timeout'              => 15,
			'google_text_search_cache_ttl'   => 21600,
			'google_place_details_cache_ttl' => 86400,
			'google_geocoding_cache_ttl'     => 86400,
			'rate_limit_per_minute'           => 60,
			'trusted_proxy_cidrs'             => '',
			'interface_copy'                  => InterfaceCopy::defaults(),
		];
	}

	public static function default_categories(): array {
		return self::sanitize_categories( CategoryCatalog::default_rows() );
	}

	public static function start_mode_choices(): array {
		return [
			self::START_MODE_CURRENT => __( 'Current location handoff', 'plan-your-day' ),
			self::START_MODE_DEFAULT => __( 'Default location', 'plan-your-day' ),
			self::START_MODE_CUSTOM  => __( 'Custom starting point', 'plan-your-day' ),
		];
	}

	public static function distance_unit_choices(): array {
		return [
			self::DISTANCE_UNIT_MILES      => __( 'Miles', 'plan-your-day' ),
			self::DISTANCE_UNIT_KILOMETERS => __( 'Kilometers', 'plan-your-day' ),
		];
	}

	public function register(): void {
		add_filter( 'option_page_capability_' . self::OPTION_GROUP, [ $this, 'option_page_capability' ] );

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize' ],
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			]
		);
	}

	public function option_page_capability(): string {
		return 'manage_options';
	}

	public static function sanitize( mixed $raw_settings ): array {
		$raw_settings = is_array( $raw_settings ) ? wp_unslash( $raw_settings ) : [];
		$defaults     = self::defaults();

		return [
			'default_location_label'          => self::sanitize_plain_text( $raw_settings['default_location_label'] ?? $defaults['default_location_label'] ),
			'default_location_address'        => self::sanitize_plain_textarea( $raw_settings['default_location_address'] ?? $defaults['default_location_address'] ),
			'default_location_latitude'       => self::sanitize_coordinate( $raw_settings['default_location_latitude'] ?? $defaults['default_location_latitude'], -90, 90 ),
			'default_location_longitude'      => self::sanitize_coordinate( $raw_settings['default_location_longitude'] ?? $defaults['default_location_longitude'], -180, 180 ),
			'default_location_place_id'       => self::sanitize_place_id( $raw_settings['default_location_place_id'] ?? $defaults['default_location_place_id'] ),
			'allowed_start_modes'             => self::sanitize_allowed_start_modes( $raw_settings['allowed_start_modes'] ?? $defaults['allowed_start_modes'] ),
			'max_waypoints'                   => self::sanitize_integer( $raw_settings['max_waypoints'] ?? $defaults['max_waypoints'], 1, 25, $defaults['max_waypoints'] ),
			'result_count'                    => self::sanitize_integer( $raw_settings['result_count'] ?? $defaults['result_count'], 1, 20, $defaults['result_count'] ),
			'distance_unit'                   => self::sanitize_distance_unit( $raw_settings['distance_unit'] ?? $defaults['distance_unit'] ),
			'map_preview_enabled'             => self::sanitize_boolean( $raw_settings['map_preview_enabled'] ?? $defaults['map_preview_enabled'] ),
			'maps_handoff_enabled'            => self::sanitize_boolean( $raw_settings['maps_handoff_enabled'] ?? $defaults['maps_handoff_enabled'] ),
			'use_preset_categories'           => self::sanitize_boolean( $raw_settings['use_preset_categories'] ?? $defaults['use_preset_categories'] ),
			'categories'                      => self::sanitize_categories( $raw_settings['categories'] ?? $defaults['categories'] ),
			'google_maps_embed_api_key'       => self::sanitize_api_key( $raw_settings['google_maps_embed_api_key'] ?? '' ),
			'google_places_api_key'           => self::sanitize_api_key( $raw_settings['google_places_api_key'] ?? '' ),
			'google_geocoding_api_key'        => self::sanitize_api_key( $raw_settings['google_geocoding_api_key'] ?? '' ),
			'google_api_timeout'              => self::sanitize_timeout( $raw_settings['google_api_timeout'] ?? 15 ),
			'google_text_search_cache_ttl'   => self::sanitize_cache_ttl( $raw_settings['google_text_search_cache_ttl'] ?? 21600 ),
			'google_place_details_cache_ttl' => self::sanitize_cache_ttl( $raw_settings['google_place_details_cache_ttl'] ?? 86400 ),
			'google_geocoding_cache_ttl'     => self::sanitize_cache_ttl( $raw_settings['google_geocoding_cache_ttl'] ?? 86400 ),
			'rate_limit_per_minute'           => self::sanitize_integer( $raw_settings['rate_limit_per_minute'] ?? $defaults['rate_limit_per_minute'], 1, 600, $defaults['rate_limit_per_minute'] ),
			'trusted_proxy_cidrs'             => self::sanitize_trusted_proxy_cidrs( $raw_settings['trusted_proxy_cidrs'] ?? $defaults['trusted_proxy_cidrs'] ),
			'interface_copy'                  => InterfaceCopy::sanitize( $raw_settings['interface_copy'] ?? $defaults['interface_copy'] ),
		];
	}

	private static function scalar_to_string( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	public static function sanitize_plain_text( mixed $value ): string {
		return trim( sanitize_text_field( self::scalar_to_string( $value ) ) );
	}

	public static function sanitize_plain_textarea( mixed $value ): string {
		return trim( sanitize_textarea_field( self::scalar_to_string( $value ) ) );
	}

	public static function sanitize_api_key( mixed $api_key ): string {
		$api_key = trim( sanitize_text_field( self::scalar_to_string( $api_key ) ) );

		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $api_key );
	}

	public static function sanitize_place_id( mixed $place_id ): string {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', trim( self::scalar_to_string( $place_id ) ) );
	}

	public static function sanitize_timeout( mixed $timeout ): int {
		$timeout = absint( $timeout );

		if ( $timeout < 1 ) {
			return 15;
		}

		return min( 30, $timeout );
	}

	public static function sanitize_cache_ttl( mixed $ttl ): int {
		return min( WEEK_IN_SECONDS, absint( $ttl ) );
	}

	public static function sanitize_integer( mixed $value, int $minimum, int $maximum, int $default ): int {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		return max( $minimum, min( $maximum, absint( $value ) ) );
	}

	public static function sanitize_boolean( mixed $value ): bool {
		return '1' === self::scalar_to_string( $value ) || 1 === $value || true === $value;
	}

	public static function sanitize_coordinate( mixed $coordinate, float $minimum, float $maximum ): string {
		$coordinate = trim( self::scalar_to_string( $coordinate ) );

		if ( '' === $coordinate || ! is_numeric( $coordinate ) ) {
			return '';
		}

		$coordinate = (float) $coordinate;

		if ( $coordinate < $minimum || $coordinate > $maximum ) {
			return '';
		}

		return rtrim( rtrim( sprintf( '%.6F', $coordinate ), '0' ), '.' );
	}

	public static function sanitize_allowed_start_modes( mixed $start_modes ): array {
		$allowed_modes = array_keys( self::start_mode_choices() );
		$start_modes   = is_array( $start_modes ) ? $start_modes : [ $start_modes ];
		$sanitized     = [];

		foreach ( $start_modes as $start_mode ) {
			$start_mode = sanitize_key( self::scalar_to_string( $start_mode ) );

			if ( in_array( $start_mode, $allowed_modes, true ) && ! in_array( $start_mode, $sanitized, true ) ) {
				$sanitized[] = $start_mode;
			}
		}

		return [] !== $sanitized ? $sanitized : [ self::START_MODE_DEFAULT ];
	}

	public static function sanitize_distance_unit( mixed $distance_unit ): string {
		$distance_unit = sanitize_key( self::scalar_to_string( $distance_unit ) );

		return array_key_exists( $distance_unit, self::distance_unit_choices() )
			? $distance_unit
			: self::DISTANCE_UNIT_MILES;
	}

	public static function sanitize_categories( mixed $categories ): array {
		if ( ! is_array( $categories ) ) {
			return [];
		}

		$sanitized_categories = [];
		$used_slugs           = [];

		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			if ( self::sanitize_boolean( $category['remove'] ?? false ) ) {
				continue;
			}

			$label       = self::sanitize_plain_text( $category['label'] ?? '' );
			$description = self::sanitize_plain_textarea( $category['description'] ?? '' );
			$text_query  = self::sanitize_plain_text( $category['text_query'] ?? '' );
			$enabled     = self::sanitize_boolean( $category['enabled'] ?? true );
			$sort_order  = self::sanitize_integer( $category['sort_order'] ?? 0, 0, 999, 0 );
			$slug        = self::sanitize_category_slug( $category['slug'] ?? '' );

			if ( '' === $label && '' === $description && '' === $text_query ) {
				continue;
			}

			if ( '' === $label || '' === $text_query ) {
				continue;
			}

			if ( '' === $slug ) {
				$slug = self::sanitize_category_slug( $label );
			}

			if ( '' === $slug ) {
				$slug = self::sanitize_category_slug( $text_query );
			}

			if ( '' === $slug ) {
				$slug = 'category';
			}

			$slug = self::make_unique_category_slug( $slug, $used_slugs );

			$used_slugs[] = $slug;

			$sanitized_categories[] = [
				'slug'        => $slug,
				'label'       => $label,
				'description' => $description,
				'text_query'  => $text_query,
				'enabled'     => $enabled,
				'sort_order'  => $sort_order,
			];
		}

		usort(
			$sanitized_categories,
			static function ( array $left, array $right ): int {
				if ( $left['sort_order'] === $right['sort_order'] ) {
					return [ $left['label'], $left['slug'] ] <=> [ $right['label'], $right['slug'] ];
				}

				return $left['sort_order'] <=> $right['sort_order'];
			}
		);

		return array_values( $sanitized_categories );
	}

	private static function sanitize_category_slug( mixed $slug ): string {
		return sanitize_title( self::scalar_to_string( $slug ) );
	}

	private static function make_unique_category_slug( string $slug, array $used_slugs ): string {
		if ( ! in_array( $slug, $used_slugs, true ) ) {
			return $slug;
		}

		$suffix       = 2;
		$base_slug    = $slug;
		$candidate    = $base_slug . '-' . $suffix;

		while ( in_array( $candidate, $used_slugs, true ) ) {
			++$suffix;
			$candidate = $base_slug . '-' . $suffix;
		}

		return $candidate;
	}

	public static function sanitize_trusted_proxy_cidrs( mixed $cidrs ): string {
		$cidrs = preg_split( '/[\r\n,]+/', self::scalar_to_string( $cidrs ) );

		if ( ! is_array( $cidrs ) ) {
			return '';
		}

		$valid_cidrs = [];

		foreach ( $cidrs as $cidr ) {
			$cidr = trim( sanitize_text_field( $cidr ) );

			if ( self::is_valid_ip_or_cidr( $cidr ) ) {
				$valid_cidrs[] = $cidr;
			}
		}

		return implode( "\n", array_values( array_unique( $valid_cidrs ) ) );
	}

	public static function is_valid_ip_or_cidr( string $cidr ): bool {
		if ( '' === $cidr ) {
			return false;
		}

		$parts = explode( '/', $cidr, 2 );
		$ip    = $parts[0];
		$ip_bin = inet_pton( $ip );

		// Zone identifiers are interface-local syntax and should not be used in trusted proxy lists.
		if ( str_contains( $ip, '%' ) || false === $ip_bin ) {
			return false;
		}

		if ( ! isset( $parts[1] ) ) {
			return true;
		}

		if ( ! ctype_digit( $parts[1] ) ) {
			return false;
		}

		$mask_bits = (int) $parts[1];
		$max_bits  = strlen( $ip_bin ) * 8;

		return $mask_bits >= 0 && $mask_bits <= $max_bits;
	}

	public function get_all(): array {
		if ( null !== $this->cached_settings ) {
			return $this->cached_settings;
		}

		$settings = get_option( self::OPTION_NAME, [] );
		$settings = is_array( $settings ) ? $settings : [];

		$this->cached_settings = array_merge( self::defaults(), self::sanitize( $settings ) );

		return $this->cached_settings;
	}

	public function flush_cache(): void {
		$this->cached_settings = null;
	}

	public function maybe_upgrade(): void {
		$this->maybe_update_stored_plugin_version();

		$current_schema_version = (int) get_option( 'plan_your_day_schema_version', 0 );

		if ( $current_schema_version >= self::EDITABLE_CATEGORY_SCHEMA_VERSION ) {
			return;
		}

		$this->seed_default_categories_if_needed();
		update_option( 'plan_your_day_schema_version', self::EDITABLE_CATEGORY_SCHEMA_VERSION );
	}

	public function seed_default_categories_if_needed(): bool {
		$settings = get_option( self::OPTION_NAME, [] );
		$settings = is_array( $settings ) ? $settings : [];

		if ( ! $this->should_seed_default_categories( $settings ) ) {
			return false;
		}

		$settings['categories'] = self::default_categories();
		update_option( self::OPTION_NAME, self::sanitize( array_merge( self::defaults(), $settings ) ) );

		return true;
	}

	private function maybe_update_stored_plugin_version(): void {
		if ( ! defined( 'PLAN_YOUR_DAY_VERSION' ) ) {
			return;
		}

		$current_version = get_option( 'plan_your_day_version', '' );

		if ( PLAN_YOUR_DAY_VERSION === $current_version ) {
			return;
		}

		update_option( 'plan_your_day_version', PLAN_YOUR_DAY_VERSION );
	}

	public function get_missing_required_settings(): array {
		$settings = $this->get_all();
		$missing  = [];

		if ( '' === $settings['default_location_label'] ) {
			$missing['default_location_label'] = __( 'Default location label', 'plan-your-day' );
		}

		if ( '' === $settings['default_location_address'] ) {
			$missing['default_location_address'] = __( 'Default location address or search phrase', 'plan-your-day' );
		}

		return $missing;
	}

	public function has_required_settings(): bool {
		return [] === $this->get_missing_required_settings();
	}

	public function get_default_location_label(): string {
		$settings = $this->get_all();

		return $settings['default_location_label'];
	}

	public function get_default_location_address(): string {
		$settings = $this->get_all();

		return $settings['default_location_address'];
	}

	public function get_default_location_latitude(): ?float {
		$settings = $this->get_all();

		return '' === $settings['default_location_latitude'] ? null : (float) $settings['default_location_latitude'];
	}

	public function get_default_location_longitude(): ?float {
		$settings = $this->get_all();

		return '' === $settings['default_location_longitude'] ? null : (float) $settings['default_location_longitude'];
	}

	public function get_default_location_place_id(): string {
		$settings = $this->get_all();

		return $settings['default_location_place_id'];
	}

	public function get_allowed_start_modes(): array {
		$settings = $this->get_all();

		return $settings['allowed_start_modes'];
	}

	public function get_max_waypoints(): int {
		$settings = $this->get_all();

		return $settings['max_waypoints'];
	}

	public function get_result_count(): int {
		$settings = $this->get_all();

		return $settings['result_count'];
	}

	public function get_distance_unit(): string {
		$settings = $this->get_all();

		return $settings['distance_unit'];
	}

	public function is_map_preview_enabled(): bool {
		$settings = $this->get_all();

		return $settings['map_preview_enabled'];
	}

	public function is_maps_handoff_enabled(): bool {
		$settings = $this->get_all();

		return $settings['maps_handoff_enabled'];
	}

	public function use_preset_categories(): bool {
		$settings = $this->get_all();

		return $settings['use_preset_categories'];
	}

	public function get_saved_categories(): array {
		$settings = $this->get_all();

		return is_array( $settings['categories'] ) ? array_values( $settings['categories'] ) : [];
	}

	public function get_categories(): array {
		$settings   = $this->get_all();
		$categories = $this->get_saved_categories();

		if ( [] !== $categories ) {
			return $categories;
		}

		if ( ! $settings['use_preset_categories'] ) {
			return [];
		}

		return self::default_categories();
	}

	public function get_google_maps_embed_api_key(): string {
		$settings = $this->get_all();

		return $settings['google_maps_embed_api_key'];
	}

	public function get_google_places_api_key(): string {
		$settings = $this->get_all();

		return $settings['google_places_api_key'];
	}

	public function get_google_geocoding_api_key(): string {
		$settings        = $this->get_all();
		$geocoding_key   = $settings['google_geocoding_api_key'];
		$places_api_key  = $settings['google_places_api_key'];

		return '' !== $geocoding_key ? $geocoding_key : $places_api_key;
	}

	public function get_google_api_timeout(): int {
		$settings = $this->get_all();

		return $settings['google_api_timeout'];
	}

	public function get_google_text_search_cache_ttl(): int {
		$settings = $this->get_all();

		return $settings['google_text_search_cache_ttl'];
	}

	public function get_google_place_details_cache_ttl(): int {
		$settings = $this->get_all();

		return $settings['google_place_details_cache_ttl'];
	}

	public function get_google_geocoding_cache_ttl(): int {
		$settings = $this->get_all();

		return $settings['google_geocoding_cache_ttl'];
	}

	public function get_rate_limit_per_minute(): int {
		$settings = $this->get_all();

		return $settings['rate_limit_per_minute'];
	}

	public function get_trusted_proxy_cidrs(): array {
		$settings = $this->get_all();
		$cidrs    = preg_split( '/\r\n|\r|\n/', $settings['trusted_proxy_cidrs'] );

		return is_array( $cidrs ) ? array_values( array_filter( $cidrs ) ) : [];
	}

	public function get_frontend_copy(): array {
		$settings = $this->get_all();

		return InterfaceCopy::resolve_values( $settings['interface_copy'] ?? [] );
	}

	public function get_frontend_copy_value( string $key ): string {
		$copy = $this->get_frontend_copy();

		return isset( $copy[ $key ] ) ? (string) $copy[ $key ] : InterfaceCopy::default_value( $key );
	}

	public function format_frontend_copy( string $key, array $tokens = [] ): string {
		return InterfaceCopy::format( $this->get_frontend_copy_value( $key ), $tokens );
	}

	private function should_seed_default_categories( array $settings ): bool {
		$saved_categories = self::sanitize_categories( $settings['categories'] ?? [] );
		$use_fallback     = array_key_exists( 'use_preset_categories', $settings )
			? self::sanitize_boolean( $settings['use_preset_categories'] )
			: self::defaults()['use_preset_categories'];

		return [] === $saved_categories && $use_fallback;
	}
}
