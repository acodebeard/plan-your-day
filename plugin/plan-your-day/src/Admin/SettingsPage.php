<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Admin;

use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
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
			'number'
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Plan Your Day settings.', PLAN_YOUR_DAY_TEXT_DOMAIN ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
				<?php
				settings_fields( Settings::OPTION_GROUP );
				do_settings_sections( Settings::PAGE_SLUG );
				submit_button();
				?>
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

	private function add_field( string $key, string $label, string $description, string $type ): void {
		add_settings_field(
			'plan_your_day_' . $key,
			$label,
			[ $this, 'render_field' ],
			Settings::PAGE_SLUG,
			'plan_your_day_google_api',
			[
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
		$settings    = $this->settings->get_all();
		$value       = $settings[ $key ] ?? '';
		$name        = Settings::OPTION_NAME . '[' . $key . ']';
		$id          = 'plan_your_day_' . $key;

		if ( 'number' === $type ) {
			printf(
				'<input id="%1$s" name="%2$s" type="number" min="1" max="30" value="%3$s" class="small-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
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
}
