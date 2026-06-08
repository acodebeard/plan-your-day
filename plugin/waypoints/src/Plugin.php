<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay;

use Acodebeard\PlanYourDay\Admin\SettingsPage;
use Acodebeard\PlanYourDay\Frontend\FrontendAssets;
use Acodebeard\PlanYourDay\Frontend\PlannerBlock;
use Acodebeard\PlanYourDay\Frontend\PlannerRenderer;
use Acodebeard\PlanYourDay\Frontend\PlannerShortcode;
use Acodebeard\PlanYourDay\Google\CachedGoogleApiClient;
use Acodebeard\PlanYourDay\Google\GoogleApiClient;
use Acodebeard\PlanYourDay\Google\GoogleApiCache;
use Acodebeard\PlanYourDay\Google\GoogleApiClientInterface;
use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Planner\DistanceFormatter;
use Acodebeard\PlanYourDay\Planner\MapUrlBuilder;
use Acodebeard\PlanYourDay\Planner\PlaceParser;
use Acodebeard\PlanYourDay\Planner\PlannerPayloadBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
use Acodebeard\PlanYourDay\Planner\RequestStateParser;
use Acodebeard\PlanYourDay\Planner\StartContextResolver;
use Acodebeard\PlanYourDay\Planner\WaypointList;
use Acodebeard\PlanYourDay\Rest\PlannerRoutes;
use Acodebeard\PlanYourDay\Security\ClientIpResolver;
use Acodebeard\PlanYourDay\Security\RateLimiter;
use Acodebeard\PlanYourDay\Security\RequestOriginValidator;
use Acodebeard\PlanYourDay\Security\VisitorTokenManager;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?Plugin $instance = null;
	private Settings $settings;
	private SettingsPage $settings_page;
	private GoogleApiCache $google_api_cache;
	private ?GoogleApiClientInterface $google_api_client = null;
	private ?PlannerStateBuilder $planner_state_builder = null;
	private CategoryCatalog $category_catalog;
	private PlaceParser $place_parser;
	private WaypointList $waypoint_list;
	private RequestStateParser $request_state_parser;
	private StartContextResolver $start_context_resolver;
	private MapUrlBuilder $map_url_builder;
	private DistanceFormatter $distance_formatter;
	private RequestOriginValidator $request_origin_validator;
	private VisitorTokenManager $visitor_token_manager;
	private ClientIpResolver $client_ip_resolver;
	private RateLimiter $rate_limiter;
	private PlannerPayloadBuilder $planner_payload_builder;
	private FrontendAssets $frontend_assets;
	private PlannerRenderer $planner_renderer;
	private PlannerShortcode $planner_shortcode;
	private PlannerBlock $planner_block;
	private PlannerRoutes $planner_routes;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings                 = new Settings();
		$this->google_api_cache         = new GoogleApiCache();
		$this->category_catalog         = new CategoryCatalog( $this->settings );
		$this->place_parser             = new PlaceParser();
		$this->waypoint_list            = new WaypointList( $this->settings );
		$this->request_state_parser     = new RequestStateParser( $this->waypoint_list );
		$this->start_context_resolver   = new StartContextResolver( $this->settings );
		$this->map_url_builder          = new MapUrlBuilder();
		$this->distance_formatter       = new DistanceFormatter( $this->settings );
		$this->request_origin_validator = new RequestOriginValidator();
		$this->visitor_token_manager    = new VisitorTokenManager();
		$this->client_ip_resolver       = new ClientIpResolver( $this->settings );
		$this->rate_limiter             = new RateLimiter( $this->settings, $this->client_ip_resolver );
		$this->planner_payload_builder  = new PlannerPayloadBuilder( $this->settings );
		$this->settings_page            = new SettingsPage(
			$this->settings,
			$this->google_api_cache,
			$this->google_api_client(),
			$this->category_catalog
		);
		$this->frontend_assets          = new FrontendAssets();
		$this->planner_renderer         = new PlannerRenderer(
			$this->settings,
			$this->category_catalog,
			$this->request_state_parser,
			$this->planner_state_builder(),
			$this->planner_payload_builder
		);
		$this->planner_shortcode        = new PlannerShortcode( $this->planner_renderer, $this->frontend_assets );
		$this->planner_block            = new PlannerBlock( $this->planner_renderer, $this->frontend_assets );
		$this->planner_routes           = new PlannerRoutes(
			$this->request_state_parser,
			$this->planner_state_builder(),
			$this->planner_payload_builder,
			$this->request_origin_validator,
			$this->visitor_token_manager,
			$this->rate_limiter,
			$this->settings
		);
	}

	public function init(): void {
		add_action( 'init', [ $this->settings, 'maybe_upgrade' ], 1 );
		add_action( 'init', [ $this->frontend_assets, 'register' ], 2 );
		add_action( 'init', [ $this->planner_shortcode, 'register' ] );
		add_action( 'init', [ $this->planner_block, 'register' ] );
		add_action( 'rest_api_init', [ $this->planner_routes, 'register' ] );
		add_action( 'admin_init', [ $this->settings, 'register' ] );
		add_action( 'admin_menu', [ $this->settings_page, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this->settings_page, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ $this->settings_page, 'render_missing_required_settings_notice' ] );
		add_action( 'admin_post_plan_your_day_clear_google_cache', [ $this->settings_page, 'handle_clear_google_cache' ] );
		add_action( 'admin_post_plan_your_day_clear_google_cache_scope', [ $this->settings_page, 'handle_clear_google_cache_scope' ] );
		add_action( 'admin_post_plan_your_day_clear_google_cache_place', [ $this->settings_page, 'handle_clear_google_cache_place' ] );
		add_action( 'admin_post_plan_your_day_test_google_api', [ $this->settings_page, 'handle_test_google_api' ] );
	}

	public function settings(): Settings {
		return $this->settings;
	}

	public function google_api_client(): GoogleApiClientInterface {
		if ( null === $this->google_api_client ) {
			$this->google_api_client = new CachedGoogleApiClient(
				new GoogleApiClient( $this->settings, null, $this->place_parser ),
				$this->settings,
				$this->google_api_cache
			);
		}

		return $this->google_api_client;
	}

	public function google_api_cache(): GoogleApiCache {
		return $this->google_api_cache;
	}

	public function category_catalog(): CategoryCatalog {
		return $this->category_catalog;
	}

	public function place_parser(): PlaceParser {
		return $this->place_parser;
	}

	public function waypoint_list(): WaypointList {
		return $this->waypoint_list;
	}

	public function request_state_parser(): RequestStateParser {
		return $this->request_state_parser;
	}

	public function start_context_resolver(): StartContextResolver {
		return $this->start_context_resolver;
	}

	public function map_url_builder(): MapUrlBuilder {
		return $this->map_url_builder;
	}

	public function distance_formatter(): DistanceFormatter {
		return $this->distance_formatter;
	}

	public function request_origin_validator(): RequestOriginValidator {
		return $this->request_origin_validator;
	}

	public function planner_state_builder(): PlannerStateBuilder {
		if ( null === $this->planner_state_builder ) {
			$this->planner_state_builder = new PlannerStateBuilder(
				$this->settings,
				$this->category_catalog,
				$this->google_api_client(),
				$this->waypoint_list,
				$this->start_context_resolver,
				$this->map_url_builder,
				$this->distance_formatter,
				$this->request_origin_validator
			);
		}

		return $this->planner_state_builder;
	}

	public function planner_payload_builder(): PlannerPayloadBuilder {
		return $this->planner_payload_builder;
	}
}
