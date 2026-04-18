<?php
declare( strict_types=1 );

namespace PlanYourDay;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			PLAN_YOUR_DAY_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( PLAN_YOUR_DAY_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
