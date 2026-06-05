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

	if ( ! function_exists( 'add_options_page' ) ) {
		function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ): void {
			$GLOBALS['plan_your_day_test_options_pages'][] = [
				'page_title' => $page_title,
				'menu_title' => $menu_title,
				'capability' => $capability,
				'menu_slug'  => $menu_slug,
				'callback'   => $callback,
			];
		}
	}

	if ( ! function_exists( 'add_settings_section' ) ) {
		function add_settings_section( string $id, string $title, callable $callback, string $page ): void {
			$GLOBALS['plan_your_day_test_settings_sections'][ $id ] = [
				'title'    => $title,
				'callback' => $callback,
				'page'     => $page,
			];
		}
	}

	if ( ! function_exists( 'add_settings_field' ) ) {
		function add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = [] ): void {
			$GLOBALS['plan_your_day_test_settings_fields'][ $id ] = [
				'title'    => $title,
				'callback' => $callback,
				'page'     => $page,
				'section'  => $section,
				'args'     => $args,
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
			$GLOBALS['plan_your_day_test_options_pages']     = [];
			$GLOBALS['plan_your_day_test_settings_sections'] = [];
			$GLOBALS['plan_your_day_test_settings_fields']   = [];
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

		public function test_render_interface_copy_section_outputs_collapsed_accordion_groups(): void {
			$settings_page = $this->settings_page();

			ob_start();
			$settings_page->render_interface_copy_section();
			$output = (string) ob_get_clean();

			self::assertStringContainsString( 'plan-your-day-admin-accordion', $output );
			self::assertSame( 0, substr_count( $output, '<details class="plan-your-day-admin-accordion__item" open>' ) );
			self::assertStringContainsString( 'Top Section And Setup Notice', $output );
			self::assertStringContainsString( 'Setup notice heading', $output );
			self::assertStringContainsString( 'Distance Labels', $output );
		}

		public function test_register_categories_editor_field_has_no_duplicate_label(): void {
			$settings_page = $this->settings_page();

			$settings_page->register();

			$field = $GLOBALS['plan_your_day_test_settings_fields']['plan_your_day_categories'] ?? [];

			self::assertSame( '', $field['title'] ?? null );
			self::assertSame( 'plan_your_day_categories', $field['section'] ?? null );
			self::assertSame( 'plan-your-day-categories-field', $field['args']['class'] ?? null );
		}

		public function test_starting_point_cleanup_candidate_fields_are_removed_from_interface_copy_admin(): void {
			$settings_page = $this->settings_page();

			ob_start();
			$settings_page->render_interface_copy_section();
			$output = (string) ob_get_clean();

			self::assertSame( 0, substr_count( $output, 'data-plan-interface-copy-candidate="fixed-default"' ) );
			self::assertStringNotContainsString( 'plan-your-day-interface-copy-candidate-badge', $output );
			self::assertStringContainsString( 'Custom start option helper text', $output );
			self::assertStringContainsString( 'data-plan-interface-copy-key="start_mode_custom_description"', $output );
			self::assertStringContainsString( 'Search section heading', $output );
			self::assertStringContainsString( 'data-plan-interface-copy-key="category_card_heading"', $output );
			self::assertStringContainsString( 'Category search placeholder', $output );
			self::assertStringContainsString( 'data-plan-interface-copy-key="category_search_placeholder"', $output );
			self::assertStringNotContainsString( 'Current location option helper text', $output );
			self::assertStringNotContainsString( 'Current location status message', $output );
			self::assertStringNotContainsString( 'Default fallback summary phrase', $output );
			self::assertStringNotContainsString( 'Starting point helper text', $output );
			self::assertStringNotContainsString( 'Custom start field label', $output );
			self::assertStringNotContainsString( 'Missing custom start warning', $output );
			self::assertStringNotContainsString( 'Default fallback summary label', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_mode_current_description"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_current_message"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_default_fallback_summary"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_card_help"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="custom_start_label"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_custom_missing_message"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_default_fallback_label"', $output );
			self::assertStringNotContainsString( 'Starting point options label', $output );
			self::assertStringNotContainsString( 'Default location option helper text', $output );
			self::assertStringNotContainsString( 'Update results button', $output );
			self::assertStringNotContainsString( 'Current location summary label', $output );
			self::assertStringNotContainsString( 'Current location summary phrase', $output );
			self::assertStringNotContainsString( 'Default location fallback label', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_mode_legend"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_mode_default_description"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="update_results_button"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_current_handoff_label"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_current_handoff_summary"', $output );
			self::assertStringNotContainsString( 'data-plan-interface-copy-key="start_default_location_fallback"', $output );

			foreach (
				[
					'Search section helper text',
					'Category search field label',
					'Category search button',
					'Custom search results heading',
					'Custom search results helper text',
					'More results button',
					'Result map link label',
					'Result map link screen reader label',
					'Already added label',
					'Already added screen reader label',
					'Add to trip button',
					'Add to trip screen reader label',
					'Results unavailable heading',
					'Results unavailable message',
					'No search yet heading',
					'No search yet message with category shortcuts',
					'No search yet message without category shortcuts',
					'No matching results heading',
					'No matching results message',
					'Search-mode Google Maps link label',
					'Search-mode preview label',
					'Initial search overview with category shortcuts',
					'Single result count label',
					'Plural result count label',
					'No results loaded label',
					'Search overview after results load',
					'Search preview key warning',
					'Initial search overview without category shortcuts',
					'Clear trip button',
					'Move up button',
					'Move down button',
					'Loading results count label',
					'Loading results heading',
					'Loading results message',
					'Loading trip count label',
					'Loading trip heading',
					'Loading trip message',
					'Loading trip preview label',
					'Loading trip preview heading',
					'Loading trip preview message',
					'Loading search preview label',
					'Loading search preview heading',
					'Loading search preview message',
				] as $removed_label
			) {
				self::assertStringNotContainsString( $removed_label, $output );
			}

			foreach (
				[
					'category_card_help',
					'category_search_label',
					'category_search_button',
					'custom_results_heading',
					'custom_results_description',
					'more_results_button',
					'view_in_google_maps',
					'view_place_in_google_maps_aria',
					'in_trip',
					'already_in_trip_aria',
					'add_to_trip',
					'add_waypoint_aria',
					'search_results_unavailable_heading',
					'search_results_unavailable_body',
					'search_results_prompt_heading',
					'search_results_prompt_body_with_categories',
					'search_results_prompt_body_no_categories',
					'no_matching_results_heading',
					'no_matching_results_body',
					'maps_link_label_search',
					'preview_mode_label_search',
					'overview_initial_with_categories',
					'search_results_count_single',
					'search_results_count_plural',
					'no_results_loaded_label',
					'overview_browse_search',
					'search_preview_key_warning',
					'overview_initial_no_categories',
					'clear_trip',
					'move_up',
					'move_down',
					'loading_results_label',
					'loading_results_heading',
					'loading_results_body',
					'loading_trip_count',
					'loading_trip_heading',
					'loading_trip_body',
					'loading_trip_preview_mode',
					'loading_trip_preview_heading',
					'loading_trip_preview_body',
					'loading_search_preview_mode',
					'loading_search_preview_heading',
					'loading_search_preview_body',
				] as $removed_key
			) {
				self::assertStringNotContainsString( 'data-plan-interface-copy-key="' . $removed_key . '"', $output );
			}
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
			self::assertStringContainsString( 'data-plan-category-row', $output );
			self::assertStringContainsString( 'data-plan-category-drag-handle', $output );
			self::assertStringContainsString( 'draggable="true"', $output );
			self::assertStringContainsString( 'data-plan-category-sort-order', $output );
			self::assertStringContainsString( 'plan_your_day_settings[categories][__INDEX__][label]', $output );
			self::assertStringContainsString( 'plan_your_day_settings[categories][__INDEX__][description]', $output );
			self::assertStringContainsString( 'plan_your_day_settings[categories][__INDEX__][text_query]', $output );
			self::assertStringContainsString( 'type="hidden" name="plan_your_day_settings[categories][__INDEX__][sort_order]"', $output );
			self::assertStringNotContainsString( 'type="number" name="plan_your_day_settings[categories][__INDEX__][sort_order]"', $output );
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
