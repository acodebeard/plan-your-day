<?php
declare( strict_types=1 );

namespace {
	if ( ! function_exists( 'wp_register_style' ) ) {
		function wp_register_style( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all' ): void {
			$GLOBALS['plan_your_day_test_registered_styles'][ $handle ] = [
				'src'   => $src,
				'deps'  => $deps,
				'ver'   => $ver,
				'media' => $media,
			];
		}
	}

	if ( ! function_exists( 'wp_register_script' ) ) {
		function wp_register_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = [] ): void {
			$GLOBALS['plan_your_day_test_registered_scripts'][ $handle ] = [
				'src'  => $src,
				'deps' => $deps,
				'ver'  => $ver,
				'args' => $args,
			];
		}
	}

	if ( ! function_exists( 'wp_enqueue_style' ) ) {
		function wp_enqueue_style( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all' ): void {
			$GLOBALS['plan_your_day_test_enqueued_styles'][ $handle ] = [
				'src'   => $src,
				'deps'  => $deps,
				'ver'   => $ver,
				'media' => $media,
			];
		}
	}

	if ( ! function_exists( 'wp_enqueue_script' ) ) {
		function wp_enqueue_script( string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = [] ): void {
			$GLOBALS['plan_your_day_test_enqueued_scripts'][ $handle ] = [
				'src'  => $src,
				'deps' => $deps,
				'ver'  => $ver,
				'args' => $args,
			];
		}
	}
}

namespace Acodebeard\PlanYourDay\Tests {

	use Acodebeard\PlanYourDay\Frontend\FrontendAssets;
	use PHPUnit\Framework\TestCase;

	final class FrontendAssetsTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['plan_your_day_test_registered_styles'] = [];
			$GLOBALS['plan_your_day_test_registered_scripts'] = [];
			$GLOBALS['plan_your_day_test_enqueued_styles']   = [];
			$GLOBALS['plan_your_day_test_enqueued_scripts']  = [];

			if ( ! defined( 'PLAN_YOUR_DAY_VERSION' ) ) {
				define( 'PLAN_YOUR_DAY_VERSION', 'test-version' );
			}

			if ( ! defined( 'PLAN_YOUR_DAY_PLUGIN_URL' ) ) {
				define( 'PLAN_YOUR_DAY_PLUGIN_URL', 'https://example.test/wp-content/plugins/plan-your-day/' );
			}
		}

		public function test_register_uses_minified_frontend_asset_paths(): void {
			$assets = new FrontendAssets();

			$assets->register();

			self::assertSame(
				'https://example.test/wp-content/plugins/plan-your-day/assets/css/plan.min.css',
				$GLOBALS['plan_your_day_test_registered_styles'][ FrontendAssets::STYLE_HANDLE ]['src'] ?? null
			);
			self::assertSame(
				'https://example.test/wp-content/plugins/plan-your-day/assets/js/plan.min.js',
				$GLOBALS['plan_your_day_test_registered_scripts'][ FrontendAssets::SCRIPT_HANDLE ]['src'] ?? null
			);
			self::assertSame(
				'https://example.test/wp-content/plugins/plan-your-day/assets/js/plan-block.js',
				$GLOBALS['plan_your_day_test_registered_scripts'][ FrontendAssets::BLOCK_EDITOR_SCRIPT_HANDLE ]['src'] ?? null
			);
			self::assertSame(
				[
					'wp-block-editor',
					'wp-blocks',
					'wp-components',
					'wp-element',
					'wp-i18n',
				],
				$GLOBALS['plan_your_day_test_registered_scripts'][ FrontendAssets::BLOCK_EDITOR_SCRIPT_HANDLE ]['deps'] ?? null
			);
		}

		public function test_enqueue_uses_registered_frontend_asset_handles(): void {
			$assets = new FrontendAssets();

			$assets->enqueue();

			self::assertArrayHasKey( FrontendAssets::STYLE_HANDLE, $GLOBALS['plan_your_day_test_enqueued_styles'] );
			self::assertArrayHasKey( FrontendAssets::SCRIPT_HANDLE, $GLOBALS['plan_your_day_test_enqueued_scripts'] );
		}
	}
}
