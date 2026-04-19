<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

defined( 'ABSPATH' ) || exit;

final class FrontendAssets {
	public const SCRIPT_HANDLE = 'plan-your-day';

	public function register(): void {
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
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}
}
