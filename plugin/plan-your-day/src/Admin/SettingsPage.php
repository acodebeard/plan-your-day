<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Admin;

use Acodebeard\PlanYourDay\Google\GoogleApiCache;
use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	private const GOOGLE_TEST_TRANSIENT_PREFIX = 'plan_your_day_google_test_';

	private Settings $settings;
	private GoogleApiCache $google_api_cache;
	private GoogleApiClientInterface $google_api_client;
	private CategoryCatalog $category_catalog;
	private LegacyConfigMigrator $legacy_config_migrator;

	public function __construct(
		Settings $settings,
		GoogleApiCache $google_api_cache,
		GoogleApiClientInterface $google_api_client,
		CategoryCatalog $category_catalog,
		LegacyConfigMigrator $legacy_config_migrator
	) {
		$this->settings          = $settings;
		$this->google_api_cache  = $google_api_cache;
		$this->google_api_client = $google_api_client;
		$this->category_catalog  = $category_catalog;
		$this->legacy_config_migrator = $legacy_config_migrator;
	}

	public function register(): void {
		add_options_page(
			__( 'Plan Your Day Settings', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Plan Your Day', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'manage_options',
			Settings::PAGE_SLUG,
			[ $this, 'render' ]
		);

		add_settings_section(
			'plan_your_day_default_location',
			__( 'Default Location', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			[ $this, 'render_default_location_section' ],
			Settings::PAGE_SLUG
		);

		$this->add_field(
			'default_location_label',
			__( 'Default location label', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Required. Human-readable label used for the default trip start.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'text',
			[],
			'plan_your_day_default_location'
		);

		$this->add_field(
			'default_location_address',
			__( 'Default location address or search phrase', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Required. Address, landmark, or search phrase used when the planner needs a stable starting area.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'textarea',
			[
				'rows' => 3,
			],
			'plan_your_day_default_location'
		);

		$this->add_field(
			'default_location_latitude',
			__( 'Default latitude', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Optional. Decimal latitude from -90 to 90 for distance hints and search biasing.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min'  => -90,
				'max'  => 90,
				'step' => 'any',
			],
			'plan_your_day_default_location'
		);

		$this->add_field(
			'default_location_longitude',
			__( 'Default longitude', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Optional. Decimal longitude from -180 to 180 for distance hints and search biasing.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min'  => -180,
				'max'  => 180,
				'step' => 'any',
			],
			'plan_your_day_default_location'
		);

		$this->add_field(
			'default_location_place_id',
			__( 'Default Google Place ID', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Optional. Google Place ID for the default location when a future workflow needs exact place details.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'text',
			[],
			'plan_your_day_default_location'
		);

		add_settings_section(
			'plan_your_day_planner_behavior',
			__( 'Planner Behavior', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			[ $this, 'render_planner_behavior_section' ],
			Settings::PAGE_SLUG
		);

		$this->add_field(
			'allowed_start_modes',
			__( 'Allowed start modes', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Choose which starting-point controls the planner may offer.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'checkbox_group',
			[
				'choices' => Settings::start_mode_choices(),
			],
			'plan_your_day_planner_behavior'
		);

		$this->add_field(
			'max_waypoints',
			__( 'Max waypoints', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Maximum selected Google places a public request may resolve.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min' => 1,
				'max' => 25,
			],
			'plan_your_day_planner_behavior'
		);

		$this->add_field(
			'result_count',
			__( 'Result count', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Maximum Google text-search results requested per browse action.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min' => 1,
				'max' => 20,
			],
			'plan_your_day_planner_behavior'
		);

		$this->add_field(
			'distance_unit',
			__( 'Distance unit', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Unit for approximate distance hints.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'select',
			[
				'choices' => Settings::distance_unit_choices(),
			],
			'plan_your_day_planner_behavior'
		);

		$this->add_field(
			'map_preview_enabled',
			__( 'Map preview', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Allow on-page Google Maps preview rendering when the frontend is added.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'checkbox',
			[],
			'plan_your_day_planner_behavior'
		);

		$this->add_field(
			'maps_handoff_enabled',
			__( 'Google Maps handoff', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Allow outbound Google Maps links when the frontend is added.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'checkbox',
			[],
			'plan_your_day_planner_behavior'
		);

		add_settings_section(
			'plan_your_day_categories',
			__( 'Categories', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			[ $this, 'render_categories_section' ],
			Settings::PAGE_SLUG
		);

		$this->add_field(
			'use_preset_categories',
			__( 'Preset category fallback', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'When no enabled custom categories are saved, show the built-in preset categories. Disable this to display no preset categories.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'checkbox',
			[],
			'plan_your_day_categories'
		);

		$this->add_field(
			'categories',
			__( 'Custom categories', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Manage the category list shown to visitors. The Google search query is the phrase sent to Google Places.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'categories',
			[],
			'plan_your_day_categories'
		);

		add_settings_section(
			'plan_your_day_google_api',
			__( 'Google API', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			[ $this, 'render_google_api_section' ],
			Settings::PAGE_SLUG
		);

		$this->add_field(
			'google_maps_embed_api_key',
			__( 'Maps Embed API key', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Browser-facing key for Google Maps Embed previews. This key can appear in frontend iframe URLs.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'password'
		);

		$this->add_field(
			'google_places_api_key',
			__( 'Places API key', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Server-side key for Places API (New) text search and place details. This key is never sent to browser config.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'password'
		);

		$this->add_field(
			'google_geocoding_api_key',
			__( 'Geocoding API key', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Optional server-side key for Geocoding API. Leave empty to use the Places API key for geocoding.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'password'
		);

		$this->add_field(
			'google_api_timeout',
			__( 'API timeout', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Request timeout in seconds. Saved values are clamped between 1 and 30 seconds.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min' => 1,
				'max' => 30,
			]
		);

		add_settings_section(
			'plan_your_day_google_cache',
			__( 'Google API Cache', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			[ $this, 'render_google_cache_section' ],
			Settings::PAGE_SLUG
		);

		$this->add_field(
			'google_text_search_cache_ttl',
			__( 'Text search cache TTL', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Seconds to cache successful Google text search responses. Use 0 to disable this cache.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min' => 0,
				'max' => WEEK_IN_SECONDS,
			],
			'plan_your_day_google_cache'
		);

		$this->add_field(
			'google_place_details_cache_ttl',
			__( 'Place details cache TTL', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Seconds to cache successful Google place details responses. Use 0 to disable this cache.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min' => 0,
				'max' => WEEK_IN_SECONDS,
			],
			'plan_your_day_google_cache'
		);

		$this->add_field(
			'google_geocoding_cache_ttl',
			__( 'Geocoding cache TTL', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Seconds to cache successful Google geocoding responses. Use 0 to disable this cache.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min' => 0,
				'max' => WEEK_IN_SECONDS,
			],
			'plan_your_day_google_cache'
		);

		add_settings_section(
			'plan_your_day_rate_limiting',
			__( 'Rate Limiting', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			[ $this, 'render_rate_limiting_section' ],
			Settings::PAGE_SLUG
		);

		$this->add_field(
			'rate_limit_per_minute',
			__( 'Requests per minute', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Configuration value for the later public endpoint rate limiter.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'number',
			[
				'min' => 1,
				'max' => 600,
			],
			'plan_your_day_rate_limiting'
		);

		add_settings_section(
			'plan_your_day_advanced',
			__( 'Advanced', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			[ $this, 'render_advanced_section' ],
			Settings::PAGE_SLUG
		);

		$this->add_field(
			'trusted_proxy_cidrs',
			__( 'Trusted proxy CIDRs', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			__( 'Optional. One IP or CIDR per line. Invalid entries are discarded on save.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			'textarea',
			[
				'rows' => 5,
			],
			'plan_your_day_advanced'
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Plan Your Day settings.', PLAN_YOUR_DAY_TEXT_DOMAIN ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_cache_notice(); ?>
			<?php $this->render_google_test_notice(); ?>
			<?php $this->render_legacy_import_notice(); ?>
			<?php $this->render_setup_status_panel(); ?>
			<?php $this->render_legacy_migration_panel(); ?>
			<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
				<?php
				settings_fields( Settings::OPTION_GROUP );
				do_settings_sections( Settings::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Cache Tools', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Clear cached Google API responses after changing cache settings or troubleshooting provider data.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="plan_your_day_clear_google_cache" />
				<?php wp_nonce_field( 'plan_your_day_clear_google_cache' ); ?>
				<?php submit_button( __( 'Clear Google API cache', PLAN_YOUR_DAY_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
			</form>
			<hr />
			<h2><?php esc_html_e( 'Google API Test', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Run a lightweight admin-only probe using the configured default location, categories, and server-side Google keys.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="plan_your_day_test_google_api" />
				<?php wp_nonce_field( 'plan_your_day_test_google_api' ); ?>
				<?php submit_button( __( 'Run Google API test', PLAN_YOUR_DAY_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
			</form>
			<?php $this->render_google_test_results_panel(); ?>
		</div>
		<?php
	}

	private function render_setup_status_panel(): void {
		$checks              = $this->build_setup_status_checks();
		$ready_check_count   = 0;
		$warning_check_count = 0;
		$optional_check_count = 0;

		foreach ( $checks as $check ) {
			if ( 'success' === $check['type'] ) {
				++$ready_check_count;
			} elseif ( 'warning' === $check['type'] ) {
				++$warning_check_count;
			} else {
				++$optional_check_count;
			}
		}
		?>
		<h2><?php esc_html_e( 'Setup Status', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h2>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: number of passing checks, 2: number of warnings, 3: number of optional checks. */
					__( '%1$d checks look ready, %2$d still need attention, and %3$d are optional.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
					$ready_check_count,
					$warning_check_count,
					$optional_check_count
				)
			);
			?>
		</p>
		<table class="widefat striped" style="margin-bottom:1.5rem;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Area', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Status', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Details', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $checks as $check ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
						<td>
							<strong><?php echo esc_html( $check['status'] ); ?></strong>
						</td>
						<td><?php echo esc_html( $check['detail'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function build_setup_status_checks(): array {
		$custom_categories = $this->settings->get_categories();
		$active_categories = $this->category_catalog->get_all();
		$missing_required  = $this->settings->get_missing_required_settings();
		$embed_key         = $this->settings->get_google_maps_embed_api_key();
		$places_key        = $this->settings->get_google_places_api_key();
		$geocoding_key     = $this->settings->get_google_geocoding_api_key();
		$legacy_summary    = $this->legacy_config_migrator->get_legacy_summary();

		return [
			[
				'label'  => __( 'Required location settings', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'status' => [] === $missing_required ? __( 'Ready', PLAN_YOUR_DAY_TEXT_DOMAIN ) : __( 'Needs setup', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'detail' => [] === $missing_required
					? __( 'The planner has the required default location label and address.', PLAN_YOUR_DAY_TEXT_DOMAIN )
					: sprintf(
						/* translators: %s is a comma-separated list of missing settings. */
						__( 'Missing: %s.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
						implode( ', ', $missing_required )
					),
				'type'   => [] === $missing_required ? 'success' : 'warning',
			],
			[
				'label'  => __( 'Places API key', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'status' => '' !== $places_key ? __( 'Ready', PLAN_YOUR_DAY_TEXT_DOMAIN ) : __( 'Missing', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'detail' => '' !== $places_key
					? __( 'Server-side Places requests can run.', PLAN_YOUR_DAY_TEXT_DOMAIN )
					: __( 'Add a Places API key before trying browse or place-detail requests.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'type'   => '' !== $places_key ? 'success' : 'warning',
			],
			[
				'label'  => __( 'Geocoding configuration', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'status' => '' !== $geocoding_key ? __( 'Ready', PLAN_YOUR_DAY_TEXT_DOMAIN ) : __( 'Missing', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'detail' => '' !== $geocoding_key
					? ( $geocoding_key === $places_key
						? __( 'Geocoding will use the Places API key fallback.', PLAN_YOUR_DAY_TEXT_DOMAIN )
						: __( 'A dedicated Geocoding API key is configured.', PLAN_YOUR_DAY_TEXT_DOMAIN ) )
					: __( 'Add a Geocoding API key or a Places API key fallback before testing location resolution.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'type'   => '' !== $geocoding_key ? 'success' : 'warning',
			],
			[
				'label'  => __( 'Maps Embed preview key', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'status' => '' !== $embed_key ? __( 'Ready', PLAN_YOUR_DAY_TEXT_DOMAIN ) : __( 'Optional', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'detail' => '' !== $embed_key
					? __( 'On-page Google Maps embeds can render when preview mode is enabled.', PLAN_YOUR_DAY_TEXT_DOMAIN )
					: __( 'Missing this key only affects on-page embed previews; Google Maps handoff links can still work.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'type'   => '' !== $embed_key ? 'success' : 'optional',
			],
			[
				'label'  => __( 'Planner categories', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'status' => [] !== $active_categories ? __( 'Ready', PLAN_YOUR_DAY_TEXT_DOMAIN ) : __( 'Needs setup', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'detail' => [] !== $active_categories
					? sprintf(
						/* translators: 1: active category count, 2: custom category count. */
						__( '%1$d active categories are available. %2$d custom categories are saved.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
						count( $active_categories ),
						count( $custom_categories )
					)
					: __( 'No active categories are available. Add at least one custom category or re-enable the preset fallback.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'type'   => [] !== $active_categories ? 'success' : 'warning',
			],
			[
				'label'  => __( 'Legacy config migration', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'status' => $this->legacy_config_migrator->migration_is_recommended()
					? __( 'Available', PLAN_YOUR_DAY_TEXT_DOMAIN )
					: ( $this->legacy_config_migrator->has_legacy_config() ? __( 'Detected', PLAN_YOUR_DAY_TEXT_DOMAIN ) : __( 'Not needed', PLAN_YOUR_DAY_TEXT_DOMAIN ) ),
				'detail' => $this->legacy_config_migrator->has_legacy_config()
					? sprintf(
						/* translators: %d is the number of detected legacy categories. */
						__( 'Legacy config was detected for migration, including %d category entries when available. The importer only copies values into plugin settings.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
						$legacy_summary['category_count']
					)
					: __( 'No legacy standalone config was detected in the current WordPress runtime.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'type'   => $this->legacy_config_migrator->migration_is_recommended() ? 'warning' : 'optional',
			],
		];
	}

	public function render_google_api_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Configure only the Google keys and request behavior needed by the backend Google client.', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_default_location_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Set the required generic starting area. Site-specific destination values belong here, not in plugin defaults.', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_planner_behavior_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Register conservative behavior limits for later renderer and REST endpoint work.', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_categories_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Use custom categories to replace the temporary built-in set. Disable the preset fallback if you want the public planner to show no preset categories until custom ones are saved.', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_google_cache_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Configure transient-based caching for successful Google API responses.', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_rate_limiting_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Store rate-limit configuration for later endpoint enforcement. This issue does not implement the limiter.', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_advanced_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Advanced networking settings used by later security and rate-limit code.', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_missing_required_settings_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || $this->settings->has_required_settings() ) {
			return;
		}

		$missing = implode( ', ', $this->settings->get_missing_required_settings() );
		$url     = add_query_arg( 'page', Settings::PAGE_SLUG, admin_url( 'options-general.php' ) );

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( sprintf( __( 'Plan Your Day needs required settings before the public planner can render: %s.', PLAN_YOUR_DAY_TEXT_DOMAIN ), $missing ) ),
			esc_url( $url ),
			esc_html__( 'Open settings', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function render_legacy_config_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! $this->legacy_config_migrator->migration_is_recommended() ) {
			return;
		}

		$url = add_query_arg( 'page', Settings::PAGE_SLUG, admin_url( 'options-general.php' ) );

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Plan Your Day detected legacy standalone config that can be imported into plugin settings.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			esc_url( $url ),
			esc_html__( 'Open the migration tool', PLAN_YOUR_DAY_TEXT_DOMAIN )
		);
	}

	public function handle_clear_google_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Plan Your Day settings.', PLAN_YOUR_DAY_TEXT_DOMAIN ) );
		}

		check_admin_referer( 'plan_your_day_clear_google_cache' );

		$cleared = $this->google_api_cache->clear();
		$redirect_url = add_query_arg(
			[
				'page'                        => Settings::PAGE_SLUG,
				'plan_your_day_cache_cleared' => $cleared,
			],
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_import_legacy_config(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Plan Your Day settings.', PLAN_YOUR_DAY_TEXT_DOMAIN ) );
		}

		check_admin_referer( 'plan_your_day_import_legacy_config' );

		$results = $this->legacy_config_migrator->import();
		$this->google_api_cache->clear();

		$redirect_url = add_query_arg(
			[
				'page'                              => Settings::PAGE_SLUG,
				'plan_your_day_legacy_imported'     => 1,
				'plan_your_day_imported_fields'     => (int) $results['imported_fields'],
				'plan_your_day_imported_categories' => (int) $results['imported_categories'],
			],
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_test_google_api(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Plan Your Day settings.', PLAN_YOUR_DAY_TEXT_DOMAIN ) );
		}

		check_admin_referer( 'plan_your_day_test_google_api' );

		$results      = $this->run_google_api_test();
		$transient_key = $this->google_test_transient_key();

		if ( '' !== $transient_key ) {
			set_transient( $transient_key, $results, 10 * MINUTE_IN_SECONDS );
		}

		$redirect_url = add_query_arg(
			[
				'page'                        => Settings::PAGE_SLUG,
				'plan_your_day_google_tested' => 1,
			],
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function run_google_api_test(): array {
		$default_address = $this->settings->get_default_location_address();
		$categories      = $this->category_catalog->get_all();
		$first_category  = is_array( reset( $categories ) ) ? reset( $categories ) : [];
		$probe_query     = $this->build_google_test_query( $default_address, $first_category );
		$geocode_result  = $this->google_api_client->geocode( $default_address );
		$origin_latitude = $this->settings->get_default_location_latitude();
		$origin_longitude = $this->settings->get_default_location_longitude();

		if ( $geocode_result->is_success() ) {
			$origin_latitude  = isset( $geocode_result->data()['latitude'] ) ? (float) $geocode_result->data()['latitude'] : $origin_latitude;
			$origin_longitude = isset( $geocode_result->data()['longitude'] ) ? (float) $geocode_result->data()['longitude'] : $origin_longitude;
		}

		$text_search_result = $this->google_api_client->text_search( $probe_query, $origin_latitude, $origin_longitude );
		$places             = $text_search_result->is_success() ? (array) ( $text_search_result->data()['places'] ?? [] ) : [];
		$first_place        = is_array( $places[0] ?? null ) ? $places[0] : [];
		$place_id           = is_scalar( $first_place['id'] ?? null ) ? (string) $first_place['id'] : '';
		$place_details_result = '' !== $place_id
			? $this->google_api_client->place_details( $place_id )
			: null;
		$checks = [
			[
				'label'   => __( 'Geocoding probe', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'success' => $geocode_result->is_success(),
				'detail'  => $geocode_result->is_success()
					? sprintf(
						/* translators: 1: latitude, 2: longitude. */
						__( 'Resolved the default location to %1$s, %2$s.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
						(string) $origin_latitude,
						(string) $origin_longitude
					)
					: $geocode_result->message(),
			],
			[
				'label'   => __( 'Text search probe', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'success' => $text_search_result->is_success(),
				'detail'  => $text_search_result->is_success()
					? sprintf(
						/* translators: 1: query text, 2: result count. */
						__( 'Query "%1$s" returned %2$d place results.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
						$probe_query,
						count( $places )
					)
					: $text_search_result->message(),
			],
			[
				'label'   => __( 'Place details probe', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'success' => $place_details_result instanceof \Acodebeard\PlanYourDay\Google\GoogleApiResult && $place_details_result->is_success(),
				'detail'  => null === $place_details_result
					? __( 'Skipped because the text search probe did not return a place ID to inspect.', PLAN_YOUR_DAY_TEXT_DOMAIN )
					: ( $place_details_result->is_success()
						? sprintf(
							/* translators: %s is a place label. */
							__( 'Loaded details for %s.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
							(string) ( $place_details_result->data()['place']['label'] ?? $place_id )
						)
						: $place_details_result->message() ),
			],
		];
		$success_count = 0;

		foreach ( $checks as $check ) {
			if ( ! empty( $check['success'] ) ) {
				++$success_count;
			}
		}

		return [
			'checked_at'     => current_time( 'mysql' ),
			'probe_query'    => $probe_query,
			'success_count'  => $success_count,
			'total_count'    => count( $checks ),
			'checks'         => $checks,
		];
	}

	private function build_google_test_query( string $default_address, array $category ): string {
		$text_query = trim( sanitize_text_field( (string) ( $category['text_query'] ?? '' ) ) );
		$address    = trim( sanitize_text_field( $default_address ) );

		if ( '' !== $text_query && '' !== $address ) {
			return $text_query . ' near ' . $address;
		}

		if ( '' !== $address ) {
			return 'points of interest near ' . $address;
		}

		return 'points of interest';
	}

	private function render_google_test_notice(): void {
		if ( ! isset( $_GET['plan_your_day_google_tested'] ) || is_array( $_GET['plan_your_day_google_tested'] ) ) {
			return;
		}

		$results = $this->get_google_test_results();

		if ( null === $results ) {
			return;
		}

		$is_success = (int) ( $results['success_count'] ?? 0 ) === (int) ( $results['total_count'] ?? 0 );

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			$is_success ? 'notice-success' : 'notice-warning',
			esc_html(
				sprintf(
					/* translators: 1: passed checks, 2: total checks. */
					__( 'Google API test completed: %1$d of %2$d probes passed.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
					(int) ( $results['success_count'] ?? 0 ),
					(int) ( $results['total_count'] ?? 0 )
				)
			)
		);
	}

	private function render_legacy_import_notice(): void {
		$imported            = filter_input( INPUT_GET, 'plan_your_day_legacy_imported', FILTER_VALIDATE_INT );
		$imported_fields     = filter_input( INPUT_GET, 'plan_your_day_imported_fields', FILTER_VALIDATE_INT );
		$imported_categories = filter_input( INPUT_GET, 'plan_your_day_imported_categories', FILTER_VALIDATE_INT );

		if ( 1 !== $imported ) {
			return;
		}

		printf(
			'<div class="notice notice-success inline"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: imported field count, 2: imported category count. */
					__( 'Imported legacy config into plugin settings. %1$d individual settings and %2$d categories were copied where plugin settings were empty.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
					max( 0, (int) $imported_fields ),
					max( 0, (int) $imported_categories )
				)
			)
		);
	}

	private function render_legacy_migration_panel(): void {
		if ( ! $this->legacy_config_migrator->has_legacy_config() ) {
			return;
		}

		$legacy_summary = $this->legacy_config_migrator->get_legacy_summary();
		?>
		<h2><?php esc_html_e( 'Legacy Migration', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h2>
		<p>
			<?php esc_html_e( 'Import legacy standalone planner values into plugin settings. This tool only copies configuration into the plugin and does not modify legacy files automatically.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
		</p>
		<ul style="list-style:disc; padding-left:1.5rem; margin-bottom:1rem;">
			<?php if ( $legacy_summary['has_default_location'] ) : ?>
				<li><?php esc_html_e( 'A legacy default location was detected.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></li>
			<?php endif; ?>
			<?php if ( $legacy_summary['has_google_keys'] ) : ?>
				<li><?php esc_html_e( 'Legacy Google API keys were detected.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></li>
			<?php endif; ?>
			<?php if ( $legacy_summary['category_count'] > 0 ) : ?>
				<li>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d is the number of detected legacy categories. */
							__( '%d legacy categories were detected.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
							$legacy_summary['category_count']
						)
					);
					?>
				</li>
			<?php endif; ?>
		</ul>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="plan_your_day_import_legacy_config" />
			<?php wp_nonce_field( 'plan_your_day_import_legacy_config' ); ?>
			<?php submit_button( __( 'Import legacy config', PLAN_YOUR_DAY_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
		</form>
		<hr />
		<?php
	}

	private function render_google_test_results_panel(): void {
		$results = $this->get_google_test_results();

		if ( null === $results ) {
			return;
		}
		?>
		<h3><?php esc_html_e( 'Latest Google API test results', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h3>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: checked datetime, 2: probe query. */
					__( 'Checked at %1$s using the probe query "%2$s".', PLAN_YOUR_DAY_TEXT_DOMAIN ),
					(string) ( $results['checked_at'] ?? '' ),
					(string) ( $results['probe_query'] ?? '' )
				)
			);
			?>
		</p>
		<table class="widefat striped" style="margin-top:0.75rem;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Probe', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Result', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
					<th><?php esc_html_e( 'Details', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( (array) ( $results['checks'] ?? [] ) as $check ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) ( $check['label'] ?? '' ) ); ?></strong></td>
						<td>
							<strong><?php echo ! empty( $check['success'] ) ? esc_html__( 'Passed', PLAN_YOUR_DAY_TEXT_DOMAIN ) : esc_html__( 'Needs attention', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></strong>
						</td>
						<td><?php echo esc_html( (string) ( $check['detail'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function get_google_test_results(): ?array {
		$transient_key = $this->google_test_transient_key();

		if ( '' === $transient_key ) {
			return null;
		}

		$results = get_transient( $transient_key );

		return is_array( $results ) ? $results : null;
	}

	private function google_test_transient_key(): string {
		$user_id = get_current_user_id();

		return $user_id > 0 ? self::GOOGLE_TEST_TRANSIENT_PREFIX . $user_id : '';
	}

	private function add_field(
		string $key,
		string $label,
		string $description,
		string $type,
		array $attributes = [],
		string $section = 'plan_your_day_google_api'
	): void {
		add_settings_field(
			'plan_your_day_' . $key,
			$label,
			[ $this, 'render_field' ],
			Settings::PAGE_SLUG,
			$section,
			[
				'attributes'  => $attributes,
				'description' => $description,
				'key'         => $key,
				'type'        => $type,
			]
		);
	}

	public function render_field( array $args ): void {
		$key         = (string) ( $args['key'] ?? '' );
		$type        = (string) ( $args['type'] ?? 'text' );
		$description = (string) ( $args['description'] ?? '' );
		$attributes  = is_array( $args['attributes'] ?? null ) ? $args['attributes'] : [];
		$settings    = $this->settings->get_all();
		$value       = $settings[ $key ] ?? '';
		$name        = Settings::OPTION_NAME . '[' . $key . ']';
		$id          = 'plan_your_day_' . $key;

		if ( 'number' === $type ) {
			printf(
				'<input id="%1$s" name="%2$s" type="number" min="%3$s" max="%4$s" step="%5$s" value="%6$s" class="small-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) ( $attributes['min'] ?? 0 ) ),
				esc_attr( (string) ( $attributes['max'] ?? WEEK_IN_SECONDS ) ),
				esc_attr( (string) ( $attributes['step'] ?? 1 ) ),
				esc_attr( (string) $value )
			);
		} elseif ( 'textarea' === $type ) {
			printf(
				'<textarea id="%1$s" name="%2$s" rows="%3$s" class="large-text">%4$s</textarea>',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) ( $attributes['rows'] ?? 3 ) ),
				esc_textarea( (string) $value )
			);
		} elseif ( 'checkbox' === $type ) {
			printf(
				'<input type="hidden" name="%1$s" value="0" /><label for="%2$s"><input id="%2$s" name="%1$s" type="checkbox" value="1" %3$s /> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $id ),
				checked( true, (bool) $value, false ),
				esc_html__( 'Enabled', PLAN_YOUR_DAY_TEXT_DOMAIN )
			);
		} elseif ( 'checkbox_group' === $type ) {
			$this->render_checkbox_group( $name, $id, is_array( $value ) ? $value : [], $attributes );
		} elseif ( 'select' === $type ) {
			$this->render_select( $name, $id, (string) $value, $attributes );
		} elseif ( 'categories' === $type ) {
			$this->render_categories_editor( $name );
		} else {
			printf(
				'<input id="%1$s" name="%2$s" type="%3$s" value="%4$s" class="regular-text" autocomplete="off" spellcheck="false" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $type ),
				esc_attr( (string) $value )
			);
		}

		if ( '' !== $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
	}

	private function render_categories_editor( string $name ): void {
		$categories  = $this->settings->get_categories();
		$row_index   = 0;
		$seed_rows   = CategoryCatalog::preset_rows();
		$next_sort   = ( count( $categories ) + 1 ) * 10;
		?>
		<div class="plan-your-day-categories-editor" data-plan-category-editor>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Enabled', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Label', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Description', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Google search query', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Sort', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
						<th><?php esc_html_e( 'Remove', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody data-plan-category-rows>
					<?php foreach ( $categories as $category ) : ?>
						<?php $this->render_category_editor_row( $name, $row_index++, is_array( $category ) ? $category : [] ); ?>
					<?php endforeach; ?>
					<?php
					$this->render_category_editor_row(
						$name,
						$row_index++,
						[
							'slug'        => '',
							'label'       => '',
							'description' => '',
							'text_query'  => '',
							'enabled'     => true,
							'sort_order'  => $next_sort,
						]
					);
					?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" data-plan-add-category><?php esc_html_e( 'Add category', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></button>
			</p>
			<p class="description">
				<?php esc_html_e( 'Leave a row empty to ignore it. Use the sort number to control the public order. Built-in presets remain available only through the fallback toggle above.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
			</p>
			<details>
				<summary><?php esc_html_e( 'View built-in preset categories', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></summary>
				<ul>
					<?php foreach ( $seed_rows as $seed_row ) : ?>
						<li>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: preset category label, 2: Google query. */
									__( '%1$s: %2$s', PLAN_YOUR_DAY_TEXT_DOMAIN ),
									(string) $seed_row['label'],
									(string) $seed_row['text_query']
								)
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
			<template data-plan-category-row-template>
				<?php
				ob_start();
				$this->render_category_editor_row(
					$name,
					'__INDEX__',
					[
						'slug'        => '',
						'label'       => '',
						'description' => '',
						'text_query'  => '',
						'enabled'     => true,
						'sort_order'  => $next_sort,
					]
				);
					echo wp_kses_post( trim( (string) ob_get_clean() ) );
				?>
			</template>
		</div>
		<script>
		(() => {
			const editor = document.currentScript?.previousElementSibling;
			if (!(editor instanceof HTMLElement)) {
				return;
			}

			const rows = editor.querySelector('[data-plan-category-rows]');
			const template = editor.querySelector('[data-plan-category-row-template]');
			const addButton = editor.querySelector('[data-plan-add-category]');

			if (!(rows instanceof HTMLElement) || !(template instanceof HTMLTemplateElement) || !(addButton instanceof HTMLButtonElement)) {
				return;
			}

			let nextIndex = rows.querySelectorAll('tr').length;

			addButton.addEventListener('click', () => {
				const markup = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
				rows.insertAdjacentHTML('beforeend', markup);
				nextIndex += 1;
			});
		})();
		</script>
		<?php
	}

	private function render_category_editor_row( string $name, int|string $index, array $category ): void {
		$row_name    = $name . '[' . $index . ']';
		$slug        = sanitize_title( (string) ( $category['slug'] ?? '' ) );
		$label       = (string) ( $category['label'] ?? '' );
		$description = (string) ( $category['description'] ?? '' );
		$text_query  = (string) ( $category['text_query'] ?? '' );
		$enabled     = ! array_key_exists( 'enabled', $category ) || (bool) $category['enabled'];
		$sort_order  = isset( $category['sort_order'] ) && is_numeric( $category['sort_order'] ) ? (int) $category['sort_order'] : 0;
		?>
		<tr>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $row_name . '[enabled]' ); ?>" value="0" />
				<input type="checkbox" name="<?php echo esc_attr( $row_name . '[enabled]' ); ?>" value="1" <?php checked( $enabled ); ?> />
				<input type="hidden" name="<?php echo esc_attr( $row_name . '[slug]' ); ?>" value="<?php echo esc_attr( $slug ); ?>" />
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $row_name . '[label]' ); ?>" value="<?php echo esc_attr( $label ); ?>" class="regular-text" />
			</td>
			<td>
				<textarea name="<?php echo esc_attr( $row_name . '[description]' ); ?>" rows="2" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $row_name . '[text_query]' ); ?>" value="<?php echo esc_attr( $text_query ); ?>" class="regular-text" />
			</td>
			<td>
				<input type="number" name="<?php echo esc_attr( $row_name . '[sort_order]' ); ?>" value="<?php echo esc_attr( (string) $sort_order ); ?>" min="0" max="999" step="1" class="small-text" />
			</td>
			<td>
				<input type="checkbox" name="<?php echo esc_attr( $row_name . '[remove]' ); ?>" value="1" />
			</td>
		</tr>
		<?php
	}

	private function render_checkbox_group( string $name, string $id, array $values, array $attributes ): void {
		$choices         = is_array( $attributes['choices'] ?? null ) ? $attributes['choices'] : [];
		$selected_values = [];

		foreach ( $values as $value ) {
			if ( is_scalar( $value ) ) {
				$selected_values[] = (string) $value;
			}
		}

		foreach ( $choices as $choice_value => $choice_label ) {
			$choice_id = $id . '_' . sanitize_key( (string) $choice_value );

			printf(
				'<label for="%1$s" style="display:block;margin:.25em 0;"><input id="%1$s" name="%2$s[]" type="checkbox" value="%3$s" %4$s /> %5$s</label>',
				esc_attr( $choice_id ),
				esc_attr( $name ),
				esc_attr( (string) $choice_value ),
				checked( in_array( (string) $choice_value, $selected_values, true ), true, false ),
				esc_html( (string) $choice_label )
			);
		}
	}

	private function render_select( string $name, string $id, string $value, array $attributes ): void {
		$choices = is_array( $attributes['choices'] ?? null ) ? $attributes['choices'] : [];

		printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );

		foreach ( $choices as $choice_value => $choice_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $choice_value ),
				selected( (string) $choice_value, $value, false ),
				esc_html( (string) $choice_label )
			);
		}

		echo '</select>';
	}

	private function render_cache_notice(): void {
		if ( ! isset( $_GET['plan_your_day_cache_cleared'] ) ) {
			return;
		}

		if ( is_array( $_GET['plan_your_day_cache_cleared'] ) ) {
			return;
		}

		$cleared = absint( wp_unslash( $_GET['plan_your_day_cache_cleared'] ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( _n( 'Cleared %d Google API cache item.', 'Cleared %d Google API cache items.', $cleared, PLAN_YOUR_DAY_TEXT_DOMAIN ), $cleared ) )
		);
	}
}
