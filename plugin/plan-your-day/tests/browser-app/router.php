<?php
declare( strict_types=1 );

require __DIR__ . '/bootstrap.php';

use Acodebeard\PlanYourDay\Frontend\FrontendAssets;
use Acodebeard\PlanYourDay\Frontend\PlannerBlock;
use Acodebeard\PlanYourDay\Frontend\PlannerRenderer;
use Acodebeard\PlanYourDay\Frontend\PlannerShortcode;
use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Planner\DistanceFormatter;
use Acodebeard\PlanYourDay\Planner\MapUrlBuilder;
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
use Acodebeard\PlanYourDay\Tests\BrowserSmokeGoogleApiClient;

$request_path = (string) ( parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ) ?? '/' );
$static_path  = dirname( __DIR__, 4 ) . $request_path;

if ( '/' !== $request_path && is_file( $static_path ) ) {
	return false;
}

switch ( $request_path ) {
	case '/__health':
		plan_your_day_browser_send_text( 'ok' );
		return;

	case '/__reset':
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			plan_your_day_browser_send_text( 'Method Not Allowed', 405 );
			return;
		}

		plan_your_day_browser_reset_state();
		http_response_code( 204 );
		return;

	case '/':
	case '/shortcode':
		plan_your_day_browser_render_page( 'shortcode' );
		return;

	case '/block':
		plan_your_day_browser_render_page( 'block' );
		return;

	case '/plain':
		plan_your_day_browser_render_page( 'plain' );
		return;

	case '/wp-json/plan-your-day/v1/browse':
		plan_your_day_browser_dispatch_rest( 'browse' );
		return;

	case '/wp-json/plan-your-day/v1/route':
		plan_your_day_browser_dispatch_rest( 'route' );
		return;
}

plan_your_day_browser_send_text( 'Not Found', 404 );

function plan_your_day_browser_app(): array {
	static $app = null;

	if ( is_array( $app ) ) {
		return $app;
	}

	$settings                 = new Settings();
	$category_catalog         = new CategoryCatalog( $settings );
	$waypoint_list            = new WaypointList( $settings );
	$request_state_parser     = new RequestStateParser( $waypoint_list );
	$start_context_resolver   = new StartContextResolver( $settings );
	$map_url_builder          = new MapUrlBuilder();
	$distance_formatter       = new DistanceFormatter( $settings );
	$request_origin_validator = new RequestOriginValidator();
	$google_api_client        = new BrowserSmokeGoogleApiClient();
	$planner_state_builder    = new PlannerStateBuilder(
		$settings,
		$category_catalog,
		$google_api_client,
		$waypoint_list,
		$start_context_resolver,
		$map_url_builder,
		$distance_formatter,
		$request_origin_validator
	);
	$planner_payload_builder  = new PlannerPayloadBuilder( $settings );
	$visitor_token_manager    = new VisitorTokenManager();
	$frontend_assets          = new FrontendAssets();
	$frontend_assets->register();

	$planner_renderer = new PlannerRenderer(
		$settings,
		$category_catalog,
		$request_state_parser,
		$planner_state_builder,
		$planner_payload_builder,
		$visitor_token_manager
	);

	$planner_routes = new PlannerRoutes(
		$request_state_parser,
		$planner_state_builder,
		$planner_payload_builder,
		$request_origin_validator,
		$visitor_token_manager,
		new RateLimiter( $settings, new ClientIpResolver( $settings ) ),
		$settings
	);

	$app = [
		'shortcode' => new PlannerShortcode( $planner_renderer, $frontend_assets ),
		'block'     => new PlannerBlock( $planner_renderer, $frontend_assets ),
		'routes'    => $planner_routes,
	];

	return $app;
}

function plan_your_day_browser_render_page( string $page ): void {
	$app      = plan_your_day_browser_app();
	$base_url = plan_your_day_browser_base_url();
	$title    = 'Plan Your Day Browser Smoke';
	$content  = '<main class="browser-app"><h1>Plan Your Day Browser Smoke</h1><p>No planner is rendered on this page.</p></main>';

	if ( 'shortcode' === $page ) {
		$title   = 'Plan Your Day Shortcode Smoke';
		$content = $app['shortcode']->render(
			[
				'action_url' => $base_url . '/shortcode',
			]
		);
	} elseif ( 'block' === $page ) {
		$title   = 'Plan Your Day Block Smoke';
		$content = $app['block']->render(
			[
				'actionUrl' => $base_url . '/block',
			]
		);
	}

	header( 'Content-Type: text/html; charset=utf-8' );

	echo '<!DOCTYPE html>';
	echo '<html lang="en">';
	echo '<head>';
	echo '<meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<title>' . esc_html( $title ) . '</title>';
	echo plan_your_day_browser_render_styles();
	echo '</head>';
	echo '<body>';
	echo $content;
	echo plan_your_day_browser_render_scripts();
	echo '</body>';
	echo '</html>';
}

function plan_your_day_browser_dispatch_rest( string $route_name ): void {
	$app     = plan_your_day_browser_app();
	$body    = (string) file_get_contents( 'php://input' );
	$decoded = json_decode( $body, true );
	$params  = is_array( $decoded ) ? $decoded : [];
	$request = new WP_REST_Request( 'POST', '/plan-your-day/v1/' . $route_name );

	foreach ( $params as $key => $value ) {
		$request->set_param( (string) $key, $value );
	}

	$result = 'browse' === $route_name
		? $app['routes']->browse( $request )
		: $app['routes']->route( $request );

	if ( $result instanceof WP_Error ) {
		plan_your_day_browser_send_json(
			[
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'data'    => $result->get_error_data(),
			],
			(int) ( $result->get_error_data()['status'] ?? 500 )
		);
		return;
	}

	if ( $result instanceof WP_REST_Response ) {
		plan_your_day_browser_send_json( $result->get_data(), $result->get_status() );
		return;
	}

	plan_your_day_browser_send_json( [], 500 );
}

function plan_your_day_browser_send_json( array $data, int $status = 200 ): void {
	http_response_code( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo (string) wp_json_encode( $data );
}

function plan_your_day_browser_send_text( string $text, int $status = 200 ): void {
	http_response_code( $status );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $text;
}
