<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

defined( 'ABSPATH' ) || exit;

final class PlannerBlock {
	public const NAME = 'waypoints/planner';
	private const LEGACY_NAME = 'plan-your-day/planner';

	private PlannerRenderer $renderer;
	private FrontendAssets $assets;

	public function __construct( PlannerRenderer $renderer, FrontendAssets $assets ) {
		$this->renderer = $renderer;
		$this->assets   = $assets;
	}

	public function register(): void {
		if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
			return;
		}

		register_block_type_from_metadata(
			PLAN_YOUR_DAY_PLUGIN_DIR . 'blocks/planner',
			[
				'render_callback' => [ $this, 'render' ],
			]
		);

		if ( function_exists( 'register_block_type' ) ) {
			register_block_type(
				self::LEGACY_NAME,
				[
					'attributes'      => [
						'actionUrl' => [
							'type'    => 'string',
							'default' => '',
						],
					],
					'render_callback' => [ $this, 'render' ],
				]
			);
		}
	}

	public function render( array $attributes = [], string $content = '', mixed $block = null ): string {
		unset( $content, $block );

		$action_url = isset( $attributes['actionUrl'] ) ? esc_url_raw( (string) $attributes['actionUrl'] ) : '';

		$this->assets->enqueue();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public dynamic block reads URL planner state; values are sanitized by RequestStateParser before use.
		return $this->renderer->render( $_GET, $action_url );
	}
}
