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

	public function render_google_cache_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__( 'Configure transient-based caching for successful Google API responses.', PLAN_YOUR_DAY_TEXT_DOMAIN )
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
				'<input id="%1$s" name="%2$s" type="number" min="%3$s" max="%4$s" value="%5$s" class="small-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) ( $attributes['min'] ?? 0 ) ),
				esc_attr( (string) ( $attributes['max'] ?? WEEK_IN_SECONDS ) ),
				esc_attr( (string) $value )
			);
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
