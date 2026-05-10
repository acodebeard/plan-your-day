<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Tests;

use PHPUnit\Framework\TestCase;

final class UninstallTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['plan_your_day_test_options']    = [];
		$GLOBALS['plan_your_day_test_transients'] = [];
	}

	public function test_uninstall_removes_rate_limiter_state_and_lock_options(): void {
		update_option( 'plan_your_day_version', '1.0.0' );
		update_option( 'plan_your_day_schema_version', '1' );
		update_option( 'plan_your_day_settings', [ 'google_maps_embed_api_key' => 'test' ] );
		update_option( 'plan_your_day_rate_' . hash( 'sha256', 'browse|198.51.100.10' ), [ 1000.0 ] );
		update_option( 'plan_your_day_rate_lock_' . hash( 'sha256', 'route|198.51.100.10' ), 1005.0 );
		update_option( 'plan_your_day_unrelated', 'keep' );
		update_option( 'plan_your_day_google_cache_keys', [ 'pyd_google_text_search_test', 'not_plugin_owned' ] );
		set_transient( 'pyd_google_text_search_test', [ 'cached' => true ], 600 );
		set_transient( 'not_plugin_owned', [ 'cached' => true ], 600 );

		$this->run_uninstall();

		self::assertArrayNotHasKey( 'plan_your_day_version', $GLOBALS['plan_your_day_test_options'] );
		self::assertArrayNotHasKey( 'plan_your_day_schema_version', $GLOBALS['plan_your_day_test_options'] );
		self::assertArrayNotHasKey( 'plan_your_day_settings', $GLOBALS['plan_your_day_test_options'] );
		self::assertArrayNotHasKey( 'plan_your_day_google_cache_keys', $GLOBALS['plan_your_day_test_options'] );
		self::assertSame( 'keep', $GLOBALS['plan_your_day_test_options']['plan_your_day_unrelated'] ?? null );

		foreach ( array_keys( $GLOBALS['plan_your_day_test_options'] ) as $option_name ) {
			self::assertStringStartsNotWith( 'plan_your_day_rate_', $option_name );
		}

		self::assertArrayNotHasKey( 'pyd_google_text_search_test', $GLOBALS['plan_your_day_test_transients'] );
		self::assertArrayHasKey( 'not_plugin_owned', $GLOBALS['plan_your_day_test_transients'] );
	}

	public function test_uninstall_removes_google_cache_transients_tracked_by_array_entries(): void {
		update_option(
			'plan_your_day_google_cache_keys',
			[
				[
					'cache_key' => 'pyd_google_geocode_test',
					'scope'     => 'geocode',
					'place_id'  => '',
				],
				[
					'cache_key' => 'pyd_google_place_details_test',
					'scope'     => 'place_details',
					'place_id'  => 'place-1',
				],
				[
					'cache_key' => 'not_plugin_owned',
					'scope'     => 'test',
					'place_id'  => '',
				],
			]
		);
		set_transient( 'pyd_google_geocode_test', [ 'cached' => true ], 600 );
		set_transient( 'pyd_google_place_details_test', [ 'cached' => true ], 600 );
		set_transient( 'not_plugin_owned', [ 'cached' => true ], 600 );

		$this->run_uninstall();

		self::assertArrayNotHasKey( 'pyd_google_geocode_test', $GLOBALS['plan_your_day_test_transients'] );
		self::assertArrayNotHasKey( 'pyd_google_place_details_test', $GLOBALS['plan_your_day_test_transients'] );
		self::assertArrayHasKey( 'not_plugin_owned', $GLOBALS['plan_your_day_test_transients'] );
	}

	private function run_uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		global $wpdb;

		$wpdb = new class() {
			public string $options = 'wp_options';

			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			public function prepare( string $query, string $value ): string {
				return str_replace( '%s', "'" . addslashes( $value ) . "'", $query );
			}

			public function query( string $query ): int {
				if ( ! str_contains( $query, 'DELETE FROM wp_options' ) || ! str_contains( $query, 'option_name LIKE' ) ) {
					return 0;
				}

				$deleted = 0;

				foreach ( array_keys( $GLOBALS['plan_your_day_test_options'] ) as $option_name ) {
					if ( str_starts_with( $option_name, 'plan_your_day_rate_' ) ) {
						unset( $GLOBALS['plan_your_day_test_options'][ $option_name ] );
						$deleted++;
					}
				}

				return $deleted;
			}
		};

		require __DIR__ . '/../uninstall.php';
	}
}
