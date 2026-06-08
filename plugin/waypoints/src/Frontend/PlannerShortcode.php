<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

defined( 'ABSPATH' ) || exit;

final class PlannerShortcode {
	public const TAG = 'waypoints';
	private const LEGACY_TAG = 'plan_your_day';

	private FrontendAssets $assets;
	private PlannerRenderer $renderer;

	public function __construct( PlannerRenderer $renderer, FrontendAssets $assets ) {
		$this->renderer = $renderer;
		$this->assets   = $assets;
	}

	public function register(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
		add_shortcode( self::LEGACY_TAG, [ $this, 'render' ] );
	}

	public function render( array|string $attributes = [], ?string $content = null, string $tag = self::TAG ): string {
		$attributes = shortcode_atts(
			[
				'action_url' => '',
			],
			is_array( $attributes ) ? $attributes : [],
			$tag
		);

		$action_url = esc_url_raw( (string) $attributes['action_url'] );

		$this->assets->enqueue();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public shortcode reads URL planner state; values are sanitized by RequestStateParser before use.
		return $this->renderer->render( $_GET, $action_url );
	}
}
