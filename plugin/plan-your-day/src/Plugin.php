<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay;

use Acodebeard\PlanYourDay\Admin\SettingsPage;
use Acodebeard\PlanYourDay\Google\GoogleApiClient;
use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?Plugin $instance = null;
	private Settings $settings;
	private SettingsPage $settings_page;
	private ?GoogleApiClientInterface $google_api_client = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings      = new Settings();
		$this->settings_page = new SettingsPage( $this->settings );
	}

	public function init(): void {
		add_action( 'init', [ $this, 'load_textdomain' ], 0 );
		add_action( 'admin_init', [ $this->settings, 'register' ] );
		add_action( 'admin_menu', [ $this->settings_page, 'register' ] );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			PLAN_YOUR_DAY_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( PLAN_YOUR_DAY_PLUGIN_FILE ) ) . '/languages'
		);
	}

	public function settings(): Settings {
		return $this->settings;
	}

	public function google_api_client(): GoogleApiClientInterface {
		if ( null === $this->google_api_client ) {
			$this->google_api_client = new GoogleApiClient( $this->settings );
		}

		return $this->google_api_client;
	}
}
