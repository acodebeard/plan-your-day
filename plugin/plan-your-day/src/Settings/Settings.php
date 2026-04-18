<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public const OPTION_NAME = 'plan_your_day_settings';
	public const OPTION_GROUP = 'plan_your_day_settings';
	public const PAGE_SLUG = 'plan-your-day';

	public static function defaults(): array {
		return [
			'google_maps_embed_api_key'       => '',
			'google_places_api_key'           => '',
			'google_geocoding_api_key'        => '',
			'google_api_timeout'              => 15,
			'google_text_search_cache_ttl'   => 21600,
			'google_place_details_cache_ttl' => 86400,
			'google_geocoding_cache_ttl'     => 86400,
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

		return [
			'google_maps_embed_api_key'       => self::sanitize_api_key( $raw_settings['google_maps_embed_api_key'] ?? '' ),
			'google_places_api_key'           => self::sanitize_api_key( $raw_settings['google_places_api_key'] ?? '' ),
			'google_geocoding_api_key'        => self::sanitize_api_key( $raw_settings['google_geocoding_api_key'] ?? '' ),
			'google_api_timeout'              => self::sanitize_timeout( $raw_settings['google_api_timeout'] ?? 15 ),
			'google_text_search_cache_ttl'   => self::sanitize_cache_ttl( $raw_settings['google_text_search_cache_ttl'] ?? 21600 ),
			'google_place_details_cache_ttl' => self::sanitize_cache_ttl( $raw_settings['google_place_details_cache_ttl'] ?? 86400 ),
			'google_geocoding_cache_ttl'     => self::sanitize_cache_ttl( $raw_settings['google_geocoding_cache_ttl'] ?? 86400 ),
		];
	}

	public static function sanitize_api_key( mixed $api_key ): string {
		$api_key = trim( sanitize_text_field( (string) $api_key ) );

		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $api_key );
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

	public function get_all(): array {
		$settings = get_option( self::OPTION_NAME, [] );
		$settings = is_array( $settings ) ? $settings : [];

		return array_merge( self::defaults(), self::sanitize( $settings ) );
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
}
