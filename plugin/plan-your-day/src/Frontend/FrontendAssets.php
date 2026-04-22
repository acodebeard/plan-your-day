<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

defined( 'ABSPATH' ) || exit;

final class FrontendAssets {
	public const STYLE_HANDLE = 'plan-your-day';
	public const SCRIPT_HANDLE = 'plan-your-day';
	private const LEGACY_INTEGRATION_STYLE_HANDLE = 'dkc-plan';
	private const LEGACY_INTEGRATION_SCRIPT_HANDLE = 'dkc-plan';

	public function register(): void {
		wp_register_style(
			self::STYLE_HANDLE,
			PLAN_YOUR_DAY_PLUGIN_URL . 'assets/css/plan.css',
			[],
			PLAN_YOUR_DAY_VERSION
		);

		wp_register_script(
			self::SCRIPT_HANDLE,
			PLAN_YOUR_DAY_PLUGIN_URL . 'assets/js/plan.js',
			[],
			PLAN_YOUR_DAY_VERSION,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
	}

	public function enqueue(): void {
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	public function dequeue_conflicting_assets(): void {
		if ( wp_script_is( self::LEGACY_INTEGRATION_SCRIPT_HANDLE, 'enqueued' ) || wp_script_is( self::LEGACY_INTEGRATION_SCRIPT_HANDLE, 'registered' ) ) {
			wp_dequeue_script( self::LEGACY_INTEGRATION_SCRIPT_HANDLE );
			wp_deregister_script( self::LEGACY_INTEGRATION_SCRIPT_HANDLE );
		}

		/*
		 * A legacy integration stylesheet can still be printed in <head> before the
		 * shortcode renders, so this primarily prevents any later re-enqueue on the
		 * same request.
		 */
		if ( wp_style_is( self::LEGACY_INTEGRATION_STYLE_HANDLE, 'enqueued' ) || wp_style_is( self::LEGACY_INTEGRATION_STYLE_HANDLE, 'registered' ) ) {
			wp_dequeue_style( self::LEGACY_INTEGRATION_STYLE_HANDLE );
			wp_deregister_style( self::LEGACY_INTEGRATION_STYLE_HANDLE );
		}
	}
}
