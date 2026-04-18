<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Admin;

use Acodebeard\PlanYourDay\Google\GoogleApiCache;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	private Settings $settings;
	private GoogleApiCache $google_api_cache;

	public function __construct( Settings $settings, GoogleApiCache $google_api_cache ) {
		$this->settings         = $settings;
		$this->google_api_cache = $google_api_cache;
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
		</div>
		<?php
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
