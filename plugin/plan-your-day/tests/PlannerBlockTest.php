<?php
declare( strict_types=1 );

namespace {
	if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
		function register_block_type_from_metadata( string $path, array $args = [] ): array {
			$GLOBALS['plan_your_day_test_registered_blocks'][] = [
				'path' => $path,
				'args' => $args,
			];

			return [
				'path' => $path,
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

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( string $text ): string {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html_e' ) ) {
		function esc_html_e( string $text, ?string $domain = null ): void {
			unset( $domain );
			echo esc_html( $text );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( string $text ): string {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( string $url ): string {
			return $url;
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( string $capability ): bool {
			unset( $capability );

			return false;
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( string $path = '' ): string {
			return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'rest_url' ) ) {
		function rest_url( string $path = '' ): string {
			return 'https://example.test/wp-json/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'disabled' ) ) {
		function disabled( mixed $disabled, mixed $current = true, bool $display = true ): string {
			$output = $disabled === $current ? 'disabled="disabled"' : '';

			if ( $display ) {
				echo $output;
			}

			return $output;
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

	if ( ! function_exists( 'wp_unique_id' ) ) {
		function wp_unique_id( string $prefix = '' ): string {
			$GLOBALS['plan_your_day_test_unique_id'] = (int) ( $GLOBALS['plan_your_day_test_unique_id'] ?? 0 ) + 1;

			return $prefix . (string) $GLOBALS['plan_your_day_test_unique_id'];
		}
	}
}

namespace Acodebeard\PlanYourDay\Tests {

	use Acodebeard\PlanYourDay\Frontend\FrontendAssets;
	use Acodebeard\PlanYourDay\Frontend\InterfaceCopy;
	use Acodebeard\PlanYourDay\Frontend\PlannerBlock;
	use Acodebeard\PlanYourDay\Frontend\PlannerRenderer;
	use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
	use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
	use Acodebeard\PlanYourDay\Planner\DistanceFormatter;
	use Acodebeard\PlanYourDay\Planner\MapUrlBuilder;
	use Acodebeard\PlanYourDay\Planner\PlannerPayloadBuilder;
	use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
	use Acodebeard\PlanYourDay\Planner\RequestStateParser;
	use Acodebeard\PlanYourDay\Planner\StartContextResolver;
	use Acodebeard\PlanYourDay\Planner\WaypointList;
	use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
	use Acodebeard\PlanYourDay\Settings\Settings;
	use PHPUnit\Framework\TestCase;

	final class PlannerBlockTest extends TestCase {
		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['plan_your_day_test_registered_blocks']   = [];
			$GLOBALS['plan_your_day_test_enqueued_styles']     = [];
			$GLOBALS['plan_your_day_test_enqueued_scripts']    = [];
			$GLOBALS['plan_your_day_test_unique_id']           = 0;
			$GLOBALS['plan_your_day_test_options']             = [];
			$_COOKIE                                           = [
				'plan_your_day_visitor' => str_repeat( 'ab', 24 ),
			];
			$_GET                                              = [];

			if ( ! defined( 'PLAN_YOUR_DAY_VERSION' ) ) {
				define( 'PLAN_YOUR_DAY_VERSION', 'test-version' );
			}

			if ( ! defined( 'PLAN_YOUR_DAY_PLUGIN_DIR' ) ) {
				define( 'PLAN_YOUR_DAY_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
			}
		}

		public function test_register_uses_block_metadata_with_render_callback(): void {
			$block = new PlannerBlock( $this->build_renderer(), new FrontendAssets() );

			$block->register();

			self::assertCount( 1, $GLOBALS['plan_your_day_test_registered_blocks'] );
			self::assertSame(
				PLAN_YOUR_DAY_PLUGIN_DIR . 'blocks/planner',
				$GLOBALS['plan_your_day_test_registered_blocks'][0]['path']
			);
			self::assertSame(
				[ $block, 'render' ],
				$GLOBALS['plan_your_day_test_registered_blocks'][0]['args']['render_callback'] ?? null
			);
		}

		public function test_render_enqueues_frontend_assets_and_uses_shared_renderer_output(): void {
			$GLOBALS['plan_your_day_test_options'][ Settings::OPTION_NAME ] = Settings::sanitize(
				array_merge(
					Settings::defaults(),
					[
						'default_location_label'   => 'Downtown',
						'default_location_address' => '123 Main St',
						'color_mode_default'       => 'dark',
						'interface_copy'           => array_merge(
							InterfaceCopy::defaults(),
							[
								'start_card_help'                => 'Editable starting point helper.',
								'custom_start_label'             => 'Editable custom start label',
								'start_mode_legend'              => 'Editable starting point legend',
								'start_mode_default_description' => 'Editable default start from {default_location}.',
								'update_results_button'          => 'Editable update button',
								'category_card_help'             => 'Editable search helper.',
								'category_search_label'          => 'Editable category search label',
								'category_search_button'         => 'Editable search button',
								'custom_results_heading'         => 'Editable results for {search}',
								'custom_results_description'     => 'Editable custom results description.',
								'more_results_button'            => 'Editable more results',
								'view_in_google_maps'            => 'Editable map link',
								'view_place_in_google_maps_aria' => 'Editable map link for {place}',
								'in_trip'                        => 'Editable in trip',
								'already_in_trip_aria'           => 'Editable already in trip for {place}',
								'add_to_trip'                    => 'Editable add to trip',
								'add_waypoint_aria'              => 'Editable add waypoint for {place}',
								'clear_trip'                     => 'Editable clear trip',
								'move_up'                        => 'Editable move up',
								'move_down'                      => 'Editable move down',
							]
						),
					]
				)
			);

			$block  = new PlannerBlock( $this->build_renderer(), new FrontendAssets() );
			$output = $block->render(
				[
					'actionUrl' => 'https://example.test/planner',
				]
			);

			self::assertArrayHasKey( FrontendAssets::STYLE_HANDLE, $GLOBALS['plan_your_day_test_enqueued_styles'] );
			self::assertArrayHasKey( FrontendAssets::SCRIPT_HANDLE, $GLOBALS['plan_your_day_test_enqueued_scripts'] );
			self::assertStringContainsString( 'class="plan-your-day"', $output );
			self::assertStringContainsString( 'data-plan-color-mode-default="dark"', $output );
			self::assertStringContainsString( '"colorModeDefault":"dark"', $output );
			self::assertStringContainsString( '"bootstrapUrl":"https:\/\/example.test\/wp-json\/plan-your-day\/v1\/bootstrap"', $output );
			self::assertStringContainsString( '"endpointToken":""', $output );
			self::assertStringNotContainsString( 'plan_your_day_visitor', $output );
			self::assertStringNotContainsString( hash_hmac( 'sha256', str_repeat( 'ab', 24 ), 'tests-auth|plan-your-day' ), $output );
			self::assertStringContainsString( 'action="https://example.test/planner#plan-your-day-1"', $output );
			self::assertStringNotContainsString( 'Editable starting point helper.', $output );
			self::assertStringNotContainsString( 'Editable custom start label', $output );
			self::assertStringNotContainsString( 'Editable starting point legend', $output );
			self::assertStringNotContainsString( 'Editable default start from Downtown.', $output );
			self::assertStringNotContainsString( 'Editable update button', $output );
			self::assertStringNotContainsString( 'Editable search helper.', $output );
			self::assertStringNotContainsString( 'Editable category search label', $output );
			self::assertStringNotContainsString( 'Editable search button', $output );
			self::assertStringNotContainsString( 'Editable results for', $output );
			self::assertStringNotContainsString( 'Editable custom results description.', $output );
			self::assertStringNotContainsString( 'Editable more results', $output );
			self::assertStringNotContainsString( 'Editable map link', $output );
			self::assertStringNotContainsString( 'Editable in trip', $output );
			self::assertStringNotContainsString( 'Editable add to trip', $output );
			self::assertStringNotContainsString( 'Editable clear trip', $output );
			self::assertStringNotContainsString( 'Editable move up', $output );
			self::assertStringNotContainsString( 'Editable move down', $output );
			self::assertStringContainsString( '<legend class="screen-reader-text">Starting point mode</legend>', $output );
			self::assertStringContainsString( '<span class="plan-your-day__start-description">Start from Downtown.</span>', $output );
			self::assertStringContainsString( '<button class="plan-your-day__submit" type="submit">Update results</button>', $output );
			self::assertStringContainsString( '<label for="plan-your-day-1-custom-start">Custom starting point</label>', $output );
			self::assertStringContainsString( '<p id="plan-your-day-1-category-help">Search for any category or use a category shortcut to load Google results.</p>', $output );
			self::assertStringContainsString( '<label for="plan-your-day-1-category-search" class="screen-reader-text">Search categories</label>', $output );
			self::assertStringContainsString( '<button class="plan-your-day__category-search-button" type="submit" data-plan-action="search-category-query">', $output );
			self::assertStringContainsString( '"searchResultsFor":"Results for {search}"', $output );
			self::assertStringContainsString( '"customSearchResultsDescription":"Custom category search results."', $output );
			self::assertStringContainsString( '"moreResultsButton":"More results"', $output );
			self::assertStringContainsString( '"viewInGoogleMaps":"View in Google Maps"', $output );
			self::assertStringContainsString( '"addToTrip":"Add to trip"', $output );
			self::assertStringContainsString( '"clearTrip":"Clear trip"', $output );
			self::assertStringContainsString( '"moveUp":"Move up"', $output );
			self::assertStringContainsString( '"moveDown":"Move down"', $output );
		}

		private function build_renderer(): PlannerRenderer {
			$settings             = new Settings();
			$category_catalog     = new CategoryCatalog( $settings );
			$waypoint_list        = new WaypointList( $settings );
			$request_state_parser = new RequestStateParser( $waypoint_list );
			$planner_state_builder = new PlannerStateBuilder(
				$settings,
				$category_catalog,
				$this->createMock( GoogleApiClientInterface::class ),
				$waypoint_list,
				new StartContextResolver( $settings ),
				new MapUrlBuilder(),
				new DistanceFormatter( $settings ),
				new RequestOriginValidator()
			);

			return new PlannerRenderer(
				$settings,
				$category_catalog,
				$request_state_parser,
				$planner_state_builder,
				new PlannerPayloadBuilder( $settings )
			);
		}
	}
}
