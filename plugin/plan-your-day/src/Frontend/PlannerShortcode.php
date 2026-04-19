<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

defined( 'ABSPATH' ) || exit;

final class PlannerShortcode {
	public const TAG = 'plan_your_day';

	private PlannerRenderer $renderer;

	public function __construct( PlannerRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	public function register(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
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

		return $this->renderer->render( $_GET, $action_url );
	}
}
