<?php
declare( strict_types=1 );

namespace {
	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( string $text ): string {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( string $text, ?string $domain = null ): string {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html_e' ) ) {
		function esc_html_e( string $text, ?string $domain = null ): void {
			echo $text;
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( string $text ): string {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_textarea' ) ) {
		function esc_textarea( string $text ): string {
			return $text;
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

	if ( ! function_exists( 'checked' ) ) {
		function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
			$output = $checked === $current ? 'checked="checked"' : '';

			if ( $display ) {
				echo $output;
			}

			return $output;
		}
	}

	if ( ! function_exists( 'wp_kses_post' ) ) {
		function wp_kses_post( string $text ): string {
			return $text;
		}
	}
}

namespace Acodebeard\PlanYourDay\Tests {

	use Acodebeard\PlanYourDay\Admin\SettingsPage;
	use Acodebeard\PlanYourDay\Google\GoogleApiCache;
	use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
	use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
	use Acodebeard\PlanYourDay\Settings\Settings;
	use PHPUnit\Framework\TestCase;

	final class SettingsPageTest extends TestCase {
		protected function setUp(): void {
			$GLOBALS['plan_your_day_test_options']           = [];
			$GLOBALS['plan_your_day_test_actions']           = [];
			$GLOBALS['plan_your_day_test_option_reads']      = [];
			$GLOBALS['plan_your_day_test_enqueued_styles']   = [];
			$GLOBALS['plan_your_day_test_enqueued_scripts']  = [];
			$_GET                                            = [];

			if ( ! defined( 'PLAN_YOUR_DAY_VERSION' ) ) {
				define( 'PLAN_YOUR_DAY_VERSION', 'test-version' );
			}

			if ( ! defined( 'PLAN_YOUR_DAY_PLUGIN_URL' ) ) {
				define( 'PLAN_YOUR_DAY_PLUGIN_URL', 'https://example.test/wp-content/plugins/plan-your-day/' );
			}
		}

		public function test_render_interface_copy_section_outputs_accordion_with_first_group_open(): void {
			$settings_page = $this->settings_page();

			ob_start();
			$settings_page->render_interface_copy_section();
			$output = (string) ob_get_clean();

			self::assertStringContainsString( 'plan-your-day-admin-accordion', $output );
			self::assertSame( 1, substr_count( $output, '<details class="plan-your-day-admin-accordion__item" open>' ) );
			self::assertStringContainsString( 'Top Section And Setup Notice', $output );
			self::assertStringContainsString( 'Setup notice heading', $output );
			self::assertStringContainsString( 'Distance Labels', $output );
		}

		public function test_enqueue_assets_only_runs_on_the_settings_screen(): void {
			$settings_page = $this->settings_page();

			$settings_page->enqueue_assets( 'dashboard_page_test' );

			self::assertSame( [], $GLOBALS['plan_your_day_test_enqueued_styles'] );
			self::assertSame( [], $GLOBALS['plan_your_day_test_enqueued_scripts'] );

			$_GET['page'] = Settings::PAGE_SLUG;
			$settings_page->enqueue_assets( 'settings_page_' . Settings::PAGE_SLUG );

			self::assertArrayHasKey( 'plan-your-day-admin-settings', $GLOBALS['plan_your_day_test_enqueued_styles'] );
			self::assertArrayHasKey( 'plan-your-day-admin-settings', $GLOBALS['plan_your_day_test_enqueued_scripts'] );
			self::assertSame(
				'https://example.test/wp-content/plugins/plan-your-day/assets/css/admin-settings.css',
				$GLOBALS['plan_your_day_test_enqueued_styles']['plan-your-day-admin-settings']['src']
			);
			self::assertSame(
				'https://example.test/wp-content/plugins/plan-your-day/assets/js/admin-settings.js',
				$GLOBALS['plan_your_day_test_enqueued_scripts']['plan-your-day-admin-settings']['src']
			);
		}

		public function test_categories_field_renders_starter_rows_when_no_categories_have_been_saved(): void {
			$settings_page = $this->settings_page();

			ob_start();
			$settings_page->render_field(
				[
					'key'         => 'categories',
					'type'        => 'categories',
					'description' => '',
					'attributes'  => [],
				]
			);
			$output = (string) ob_get_clean();

			self::assertStringContainsString( 'Coffee', $output );
			self::assertStringContainsString( 'coffee shops and cafes', $output );
			self::assertStringContainsString( 'View built-in starter categories', $output );
		}

		public function test_categories_field_template_keeps_new_row_form_controls(): void {
			$settings_page = $this->settings_page();

			ob_start();
			$settings_page->render_field(
				[
					'key'         => 'categories',
					'type'        => 'categories',
					'description' => '',
					'attributes'  => [],
				]
			);
			$output = (string) ob_get_clean();

			self::assertStringContainsString( '<template data-plan-category-row-template>', $output );
			self::assertStringContainsString( 'plan_your_day_settings[categories][__INDEX__][label]', $output );
			self::assertStringContainsString( 'plan_your_day_settings[categories][__INDEX__][description]', $output );
			self::assertStringContainsString( 'plan_your_day_settings[categories][__INDEX__][text_query]', $output );
		}

		private function settings_page(): SettingsPage {
			$settings = new Settings();

			return new SettingsPage(
				$settings,
				new GoogleApiCache(),
				$this->createMock( GoogleApiClientInterface::class ),
				new CategoryCatalog( $settings )
			);
		}
	}
}
