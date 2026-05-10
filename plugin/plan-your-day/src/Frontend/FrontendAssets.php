<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

defined( 'ABSPATH' ) || exit;

final class FrontendAssets {
	public const STYLE_HANDLE = 'plan-your-day';
	public const SCRIPT_HANDLE = 'plan-your-day';
	public const BLOCK_EDITOR_SCRIPT_HANDLE = 'plan-your-day-block-editor';

	public function register(): void {
		wp_register_style(
			self::STYLE_HANDLE,
			PLAN_YOUR_DAY_PLUGIN_URL . 'assets/css/plan.min.css',
			[],
			PLAN_YOUR_DAY_VERSION
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			PLAN_YOUR_DAY_PLUGIN_URL . 'assets/js/plan.min.js',
			[],
			PLAN_YOUR_DAY_VERSION,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		wp_register_script(
			self::BLOCK_EDITOR_SCRIPT_HANDLE,
			PLAN_YOUR_DAY_PLUGIN_URL . 'assets/js/plan-block.js',
			[
				'wp-block-editor',
				'wp-blocks',
				'wp-components',
				'wp-element',
				'wp-i18n',
			],
			PLAN_YOUR_DAY_VERSION,
			true
		);
	}

	public function enqueue(): void {
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}
}
