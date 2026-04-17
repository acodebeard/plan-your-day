<?php
declare(strict_types=1);

/*
 * Destination Kona Coast standalone trip planner.
 *
 * This file intentionally contains the small WordPress compatibility layer,
 * the Google API request logic, the focused JSON endpoints, and the rendered
 * planner markup. The goal for /plan-your-day is that index.php can run on a
 * plain PHP server without loading WordPress or any theme files from /dkc.
 */

/*
 * Never leak PHP errors to HTTP responses. An operator who wants
 * browser-visible errors back on for debugging can still enable
 * them explicitly via php.ini or php_admin_value; the defaults
 * here are log-only. Any runtime notice or warning would otherwise
 * corrupt JSON endpoint responses and expose local paths.
 */
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/*
 * Load the standalone Google Maps API key from a separate local PHP config.
 *
 * A .php config file is safer than .inc on this Apache/PHP stack because it is
 * executed by PHP instead of being treated as a raw downloadable file. The
 * filename is intentionally short and random, and the bootstrap constant lets
 * the config refuse direct browser requests if server file-deny rules are ever
 * unavailable.
 */
define('DKC_PLAN_STANDALONE_BOOTSTRAP', true);
require_once __DIR__ . '/c88e3e98.php';

/*
 * The original component was written for WordPress. These constants mirror the
 * WordPress time helpers used by the planner cache and rate limiter so the
 * copied business logic can stay readable in its standalone form.
 */
if (!defined('MINUTE_IN_SECONDS')) {
	define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
	define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
	define('DAY_IN_SECONDS', 86400);
}

/*
 * Sessions provide a lightweight local store for nonces, rate counters, and
 * short-lived Google response caches. This keeps the standalone copy free of
 * database requirements while still avoiding repeated API calls during normal
 * use.
 */
if (PHP_SESSION_NONE === session_status()) {
	session_start();
}

if (!function_exists('apply_filters')) {
	/**
	 * Standalone no-op replacement for WordPress filters.
	 *
	 * The copied planner still calls filter hooks at its configuration points.
	 * Returning the supplied value preserves that API shape without requiring the
	 * WordPress plugin system.
	 */
	function apply_filters(string $hook_name, $value)
	{
		return $value;
	}
}

if (!function_exists('wp_json_encode')) {
	/**
	 * Encode data for HTML script blocks, cache keys, and JSON responses.
	 *
	 * The flags match the behavior the planner needs most: readable URLs and
	 * safe handling of invalid UTF-8 instead of failing silently.
	 */
	function wp_json_encode($value): string
	{
		$encoded = json_encode(
			$value,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
		);

		return false === $encoded ? 'null' : $encoded;
	}
}

if (!function_exists('wp_unslash')) {
	/**
	 * Recursively remove slashes from request data.
	 *
	 * WordPress historically adds slashes to request globals. Plain PHP does not,
	 * but keeping this helper means the copied sanitization flow remains
	 * consistent and harmless in both cases.
	 */
	function wp_unslash($value)
	{
		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return is_string($value) ? stripslashes($value) : $value;
	}
}

if (!function_exists('sanitize_key')) {
	/**
	 * Sanitize a key used for known enum-like values such as category slugs.
	 */
	function sanitize_key(string $key): string
	{
		return preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
	}
}

if (!function_exists('sanitize_text_field')) {
	/**
	 * Sanitize short free-text values from the planner query string.
	 *
	 * This strips tags and control characters, then collapses whitespace so a
	 * custom start address remains readable without allowing markup through.
	 */
	function sanitize_text_field(string $value): string
	{
		$value = strip_tags($value);
		$value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
		$value = preg_replace('/\s+/u', ' ', (string) $value);

		return trim((string) $value);
	}
}

if (!function_exists('esc_html')) {
	/**
	 * Escape text for safe HTML output.
	 */
	function esc_html($value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_attr')) {
	/**
	 * Escape text for safe HTML attribute output.
	 */
	function esc_attr($value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('esc_url')) {
	/**
	 * Escape URLs used in href, action, and iframe src attributes.
	 *
	 * The planner builds its own trusted Google URLs, but output escaping still
	 * protects against malformed request data entering the rendered attributes.
	 */
	function esc_url($url): string
	{
		$url = filter_var(trim((string) $url), FILTER_SANITIZE_URL);

		return htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('checked')) {
	/**
	 * Echo a checked attribute when two values match.
	 */
	function checked($checked, $current = true, bool $display = true): string
	{
		$result = (string) $checked === (string) $current ? ' checked="checked"' : '';

		if ($display) {
			echo $result;
		}

		return $result;
	}
}

if (!function_exists('number_format_i18n')) {
	/**
	 * Format numbers for display in this standalone English-language prototype.
	 */
	function number_format_i18n(float $number, int $decimals = 0): string
	{
		return number_format($number, $decimals);
	}
}

if (!function_exists('get_transient')) {
	/**
	 * Read a short-lived session cache item.
	 *
	 * Google responses and rate counters use the same transient-style API as the
	 * WordPress version. Expired items are removed as they are read.
	 */
	function get_transient(string $key)
	{
		$item = $_SESSION['dkc_plan_transients'][$key] ?? null;

		if (!is_array($item) || !array_key_exists('value', $item)) {
			return false;
		}

		if ((int) ($item['expires'] ?? 0) < time()) {
			unset($_SESSION['dkc_plan_transients'][$key]);

			return false;
		}

		return $item['value'];
	}
}

if (!function_exists('set_transient')) {
	/**
	 * Store a short-lived session cache item.
	 */
	function set_transient(string $key, $value, int $expiration): bool
	{
		$_SESSION['dkc_plan_transients'][$key] = [
			'expires' => time() + max(1, $expiration),
			'value'   => $value,
		];

		return true;
	}
}

if (!class_exists('DKC_Plan_Http_Error')) {
	/**
	 * Minimal error object used by the standalone HTTP helpers.
	 */
	class DKC_Plan_Http_Error
	{
		public string $message;

		public function __construct(string $message)
		{
			$this->message = $message;
		}
	}
}

if (!function_exists('is_wp_error')) {
	/**
	 * Match the WordPress error check used by the copied Google request code.
	 */
	function is_wp_error($value): bool
	{
		return $value instanceof DKC_Plan_Http_Error;
	}
}

if (!function_exists('dkc_plan_http_header_lines')) {
	/**
	 * Convert associative request headers into the line format expected by curl
	 * and PHP stream contexts.
	 */
	function dkc_plan_http_header_lines(array $headers): array
	{
		$lines = [];

		foreach ($headers as $header_name => $header_value) {
			$lines[] = $header_name . ': ' . $header_value;
		}

		return $lines;
	}
}

if (!function_exists('dkc_plan_http_request')) {
	/**
	 * Make a small outbound HTTP request for Google APIs.
	 *
	 * Curl is used when available because it gives reliable status codes and
	 * timeout handling. The stream fallback keeps the standalone copy usable on
	 * simple PHP installs where curl is not enabled.
	 */
	function dkc_plan_http_request(string $method, string $url, array $args = [])
	{
		$timeout = (int) ($args['timeout'] ?? 15);
		$headers = (array) ($args['headers'] ?? []);
		$body = isset($args['body']) ? (string) $args['body'] : '';

		if (function_exists('curl_init')) {
			$handle = curl_init($url);

			if (false === $handle) {
				return new DKC_Plan_Http_Error('The HTTP request could not be initialized.');
			}

			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);

			if (!empty($headers)) {
				curl_setopt($handle, CURLOPT_HTTPHEADER, dkc_plan_http_header_lines($headers));
			}

			if ('POST' === $method) {
				curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
			}

			$response_body = curl_exec($handle);
			$response_code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

			if (false === $response_body) {
				$error_message = curl_error($handle);
				curl_close($handle);

				return new DKC_Plan_Http_Error($error_message ?: 'The HTTP request failed.');
			}

			curl_close($handle);

			return [
				'response' => [
					'code' => $response_code,
				],
				'body'     => (string) $response_body,
			];
		}

		$context = stream_context_create(
			[
				'http' => [
					'method'        => $method,
					'timeout'       => $timeout,
					'ignore_errors' => true,
					'header'        => implode("\r\n", dkc_plan_http_header_lines($headers)),
					'content'       => 'POST' === $method ? $body : '',
				],
			]
		);
		$response_body = @file_get_contents($url, false, $context);

		if (false === $response_body) {
			return new DKC_Plan_Http_Error('The HTTP request failed.');
		}

		$response_code = 0;

		if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
			$response_code = (int) $matches[1];
		}

		return [
			'response' => [
				'code' => $response_code,
			],
			'body'     => (string) $response_body,
		];
	}
}

if (!function_exists('wp_remote_get')) {
	/**
	 * Standalone GET helper with the same response shape the copied code expects.
	 */
	function wp_remote_get(string $url, array $args = [])
	{
		return dkc_plan_http_request('GET', $url, $args);
	}
}

if (!function_exists('wp_remote_post')) {
	/**
	 * Standalone POST helper with the same response shape the copied code expects.
	 */
	function wp_remote_post(string $url, array $args = [])
	{
		return dkc_plan_http_request('POST', $url, $args);
	}
}

if (!function_exists('wp_remote_retrieve_response_code')) {
	/**
	 * Extract the HTTP status code from a standalone response array.
	 */
	function wp_remote_retrieve_response_code($response): int
	{
		return (int) ($response['response']['code'] ?? 0);
	}
}

if (!function_exists('wp_remote_retrieve_body')) {
	/**
	 * Extract the body from a standalone response array.
	 */
	function wp_remote_retrieve_body($response): string
	{
		return (string) ($response['body'] ?? '');
	}
}

if (!function_exists('wp_create_nonce')) {
	/**
	 * Create or reuse a session nonce for the public JSON endpoints.
	 *
	 * This is not a WordPress nonce clone; it is a straightforward same-session
	 * token that prevents routine cross-site endpoint calls in this standalone
	 * prototype.
	 */
	function wp_create_nonce(string $action): string
	{
		if (!isset($_SESSION['dkc_plan_nonces'][$action])) {
			$_SESSION['dkc_plan_nonces'][$action] = bin2hex(random_bytes(16));
		}

		return (string) $_SESSION['dkc_plan_nonces'][$action];
	}
}

if (!function_exists('wp_verify_nonce')) {
	/**
	 * Verify the standalone same-session endpoint nonce.
	 */
	function wp_verify_nonce(string $nonce, string $action): bool
	{
		$expected = (string) ($_SESSION['dkc_plan_nonces'][$action] ?? '');

		return '' !== $expected && hash_equals($expected, $nonce);
	}
}

if (!function_exists('wp_send_json_success')) {
	/**
	 * Send a successful JSON endpoint response and stop page rendering.
	 */
	function wp_send_json_success(array $data = [], int $status_code = 200): void
	{
		http_response_code($status_code);
		header('Content-Type: application/json; charset=UTF-8');
		echo wp_json_encode(
			[
				'success' => true,
				'data'    => $data,
			]
		);
		exit;
	}
}

if (!function_exists('wp_send_json_error')) {
	/**
	 * Send an error JSON endpoint response and stop page rendering.
	 */
	function wp_send_json_error(array $data = [], int $status_code = 400): void
	{
		http_response_code($status_code);
		header('Content-Type: application/json; charset=UTF-8');
		echo wp_json_encode(
			[
				'success' => false,
				'data'    => $data,
			]
		);
		exit;
	}
}

if (!function_exists('dkc_plan_current_url')) {
	/**
	 * Build the current standalone index URL without preserving query params.
	 *
	 * The planner appends its own state params as needed, so the base action and
	 * JSON endpoint URLs should stay clean.
	 */
	function dkc_plan_current_url(): string
	{
		$is_https = !empty($_SERVER['HTTPS']) && 'off' !== strtolower((string) $_SERVER['HTTPS']);
		$scheme = $is_https ? 'https' : 'http';
		$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
		$request_path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
		$path = $request_path ?: (string) ($_SERVER['SCRIPT_NAME'] ?? '/plan-your-day/index.php');

		if ('' === $path || '/' !== $path[0]) {
			$path = '/' . $path;
		}

		return $scheme . '://' . $host . $path;
	}
}

if (!defined('DKC_PLAN_MAX_WAYPOINTS')) {
	define('DKC_PLAN_MAX_WAYPOINTS', 8);
}

/*
 * The planner is rendered inside the front-page template and also serves small
 * JSON responses for focused client-side updates. Keeping the shared logic in a
 * dedicated include lets the template and AJAX handlers stay in sync.
 */
if (!function_exists('dkc_plan_get_google_api_key')) {
	/**
	 * Return the Google API key used by the planner.
	 *
	 * Key values must come from configuration or filters so credentials do not
	 * live in committed theme code.
	 */
	function dkc_plan_get_google_api_key(): string
	{
		$default_key = '';

		if (defined('DKC_PLAN_GOOGLE_API_KEY')) {
			$default_key = (string) DKC_PLAN_GOOGLE_API_KEY;
		}

		$default_key = (string) apply_filters('dkc_plan_google_embed_api_key', $default_key);

		return (string) apply_filters('dkc_plan_google_api_key', $default_key);
	}
}

if (!function_exists('dkc_plan_get_google_embed_api_key')) {
	/**
	 * Backwards-compatible embed key accessor.
	 */
	function dkc_plan_get_google_embed_api_key(): string
	{
		return dkc_plan_get_google_api_key();
	}
}

if (!function_exists('dkc_plan_get_google_places_api_key')) {
	/**
	 * Return the API key used for Places text search and place details.
	 */
	function dkc_plan_get_google_places_api_key(): string
	{
		return (string) apply_filters('dkc_plan_google_places_api_key', dkc_plan_get_google_api_key());
	}
}

if (!function_exists('dkc_plan_get_category_catalog')) {
	/**
	 * Return the hardcoded category catalog for the local prototype.
	 *
	 * These entries describe search intent only. DKC names the type of outing the
	 * visitor wants, while Google returns the exact places.
	 */
	function dkc_plan_get_category_catalog(): array
	{
		return [
			'coffee' => [
				'label'       => 'Coffee',
				'description' => 'Search Google for coffee shops, cafes, tastings, and easy morning stops.',
				'text_query'  => 'coffee shops and cafes',
			],
			'food' => [
				'label'       => 'Food',
				'description' => 'Search Google for restaurants, quick bites, and broader local food options.',
				'text_query'  => 'restaurants and local food',
			],
			'shopping' => [
				'label'       => 'Shopping',
				'description' => 'Search Google for boutiques, markets, and places to browse local goods.',
				'text_query'  => 'shopping and local boutiques',
			],
			'beaches' => [
				'label'       => 'Beaches',
				'description' => 'Search Google for beaches, shoreline access, and relaxed oceanfront stops.',
				'text_query'  => 'beaches',
			],
			'history-culture' => [
				'label'       => 'History / culture',
				'description' => 'Search Google for museums, landmarks, heritage sites, and cultural experiences.',
				'text_query'  => 'history and culture',
			],
			'scenic' => [
				'label'       => 'Scenic spots',
				'description' => 'Search Google for viewpoints, waterfront stretches, and scenic lookouts.',
				'text_query'  => 'scenic spots and viewpoints',
			],
			'activities' => [
				'label'       => 'Other tourist activities',
				'description' => 'Search Google for tours, family-friendly attractions, and broader things to do.',
				'text_query'  => 'tours and activities',
			],
		];
	}
}

if (!function_exists('dkc_plan_get_start_points')) {
	/**
	 * Return supported starting-point modes and the explanatory UI copy.
	 */
	function dkc_plan_get_start_points(): array
	{
		return [
			'current' => [
				'label'       => 'Current location',
				'description' => 'Best for the Google Maps handoff. The on-page results and preview fall back to Kailua Pier.',
			],
			'pier' => [
				'label'       => 'Kailua Pier',
				'description' => 'Use Kailua Pier as a stable start for search results and route previews.',
			],
			'custom' => [
				'label'       => 'Custom starting point',
				'description' => 'Use a hotel, resort, vacation rental, or another address as the trip start.',
			],
		];
	}
}

if (!function_exists('dkc_plan_sanitize_place_id')) {
	/**
	 * Sanitize a Google place ID passed through the query string.
	 */
	function dkc_plan_sanitize_place_id(string $place_id): string
	{
		return preg_replace('/[^A-Za-z0-9_-]/', '', trim($place_id));
	}
}

if (!function_exists('dkc_plan_get_max_waypoints')) {
	/**
	 * Return the maximum number of selected places the planner will resolve.
	 *
	 * This limit keeps public requests bounded before the code reaches Google
	 * Place Details or Google Maps handoff URL construction. A filter remains
	 * available for site-specific tuning, but the value is forced to at least one
	 * so custom filters cannot accidentally disable all trip building.
	 */
	function dkc_plan_get_max_waypoints(): int
	{
		return max(1, (int) apply_filters('dkc_plan_max_waypoints', DKC_PLAN_MAX_WAYPOINTS));
	}
}

if (!function_exists('dkc_plan_normalize_waypoint_ids')) {
	/**
	 * Keep only valid waypoint IDs, remove duplicates, and preserve trip order.
	 */
	function dkc_plan_normalize_waypoint_ids(array $waypoint_ids): array
	{
		$normalized_waypoint_ids = [];
		$max_waypoints           = dkc_plan_get_max_waypoints();

		foreach ($waypoint_ids as $waypoint_id) {
			$waypoint_id = dkc_plan_sanitize_place_id((string) $waypoint_id);

			if ('' === $waypoint_id || in_array($waypoint_id, $normalized_waypoint_ids, true)) {
				continue;
			}

			$normalized_waypoint_ids[] = $waypoint_id;

			if (count($normalized_waypoint_ids) >= $max_waypoints) {
				break;
			}
		}

		return $normalized_waypoint_ids;
	}
}

if (!function_exists('dkc_plan_build_query')) {
	/**
	 * RFC3986 encoding keeps Google Maps URLs predictable when labels or
	 * addresses contain spaces and punctuation.
	 */
	function dkc_plan_build_query(array $params): string
	{
		return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	}
}

if (!function_exists('dkc_plan_build_state_url')) {
	/**
	 * Build a shareable planner URL from the current state.
	 */
	function dkc_plan_build_state_url(string $action_url, array $params, string $section_id): string
	{
		$url = $action_url;

		if (!empty($params)) {
			$url .= '?' . dkc_plan_build_query($params);
		}

		return $url . '#' . $section_id;
	}
}

if (!function_exists('dkc_plan_format_label_list')) {
	/**
	 * Build a human-readable list for summaries and helper copy.
	 */
	function dkc_plan_format_label_list(array $labels): string
	{
		$labels = array_values(
			array_filter(
				array_map(
					static function ($label): string {
						return trim((string) $label);
					},
					$labels
				)
			)
		);

		$label_count = count($labels);

		if (0 === $label_count) {
			return '';
		}

		if (1 === $label_count) {
			return $labels[0];
		}

		if (2 === $label_count) {
			return $labels[0] . ' and ' . $labels[1];
		}

		$last_label = array_pop($labels);

		return implode(', ', $labels) . ', and ' . $last_label;
	}
}

if (!function_exists('dkc_plan_get_search_context')) {
	/**
	 * Determine the effective search and preview context for the chosen start
	 * mode. Current location and empty custom starts still need a safe server-
	 * side fallback for the on-page results list and iframe preview.
	 */
	function dkc_plan_get_search_context(array $args): array
	{
		$start_points        = $args['start_points'];
		$start_mode          = $args['start_mode'];
		$custom_start        = $args['custom_start'];
		$pier_address        = $args['pier_address'];
		$preview_start_label = $start_points['pier']['label'];
		$handoff_start_label = $start_points['pier']['label'];
		$handoff_summary     = $start_points['pier']['label'];
		$search_area         = $pier_address;
		$messages            = [];
		$use_current_handoff = false;
		$start_note_text     = 'The on-page results and preview use Kailua Pier as the trip start. Google Maps will do the same.';

		if ('current' === $start_mode) {
			$handoff_start_label = 'Current location';
			$handoff_summary     = 'your current location';
			$use_current_handoff = true;
			$start_note_text     = 'The on-page results and preview use Kailua Pier so they work without geolocation. Google Maps will start from the current location.';
			$messages[]          = [
				'type' => 'note',
				'text' => 'The on-page results and preview use Kailua Pier so they work without geolocation. Google Maps will start from the current location when you hand the trip off.',
			];
		} elseif ('custom' === $start_mode && '' !== $custom_start) {
			$search_area         = $custom_start;
			$preview_start_label = $custom_start;
			$handoff_start_label = $custom_start;
			$handoff_summary     = $custom_start;
			$start_note_text     = 'The on-page results, preview, and Google Maps handoff all use your custom starting point.';
		} elseif ('custom' === $start_mode) {
			$handoff_start_label = 'Kailua Pier fallback';
			$handoff_summary     = 'Kailua Pier until you add a custom starting point';
			$start_note_text     = 'Add a custom address to replace the Kailua Pier fallback for search results, the route preview, and the Google Maps handoff.';
			$messages[]          = [
				'type' => 'warning',
				'text' => 'Add a hotel or address to replace the Kailua Pier fallback before finalizing the trip start.',
			];
		}

		return [
			'search_area'         => $search_area,
			'preview_start_label' => $preview_start_label,
			'handoff_start_label' => $handoff_start_label,
			'handoff_summary'     => $handoff_summary,
			'use_current_handoff' => $use_current_handoff,
			'start_note_text'     => $start_note_text,
			'messages'            => $messages,
		];
	}
}

if (!function_exists('dkc_plan_build_category_query')) {
	/**
	 * Build the Google category search phrase for either the on-page results or
	 * the Google Maps handoff.
	 */
	function dkc_plan_build_category_query(array $category, string $search_area, bool $use_current_location = false): string
	{
		$text_query = trim((string) ($category['text_query'] ?? $category['label'] ?? ''));

		if ('' === $text_query) {
			return '';
		}

		if ($use_current_location) {
			return $text_query . ' near me';
		}

		if ('' === trim($search_area)) {
			return $text_query;
		}

		return $text_query . ' near ' . $search_area;
	}
}

if (!function_exists('dkc_plan_parse_google_place')) {
	/**
	 * Shape a Google place response into the planner's simpler internal format.
	 */
	function dkc_plan_parse_google_place(array $place): array
	{
		$place_id  = dkc_plan_sanitize_place_id((string) ($place['id'] ?? ''));
		$label     = trim((string) ($place['displayName']['text'] ?? ''));
		$address   = trim((string) ($place['formattedAddress'] ?? ''));
		$maps_uri  = trim((string) ($place['googleMapsUri'] ?? ''));
		$latitude  = isset($place['location']['latitude']) ? (float) $place['location']['latitude'] : null;
		$longitude = isset($place['location']['longitude']) ? (float) $place['location']['longitude'] : null;

		return [
			'id'        => $place_id,
			'label'     => '' !== $label ? $label : $address,
			'address'   => $address,
			'maps_uri'  => $maps_uri,
			'latitude'  => $latitude,
			'longitude' => $longitude,
			'is_valid'  => '' !== $place_id && ('' !== $label || '' !== $address),
		];
	}
}

if (!function_exists('dkc_plan_get_google_cache_key')) {
	/**
	 * Build a short transient key for server-side Google responses.
	 *
	 * The API key is intentionally reduced to a hash fragment so transient names
	 * never expose credentials, while still keeping cached responses separated
	 * when environments use different Google keys or restrictions.
	 */
	function dkc_plan_get_google_cache_key(string $scope, array $parts, string $api_key): string
	{
		$cache_parts = [
			'scope' => $scope,
			'key'   => substr(md5($api_key), 0, 12),
			'parts' => $parts,
		];

		return 'dkc_plan_google_' . md5(wp_json_encode($cache_parts));
	}
}

if (!function_exists('dkc_plan_request_text_search')) {
	/**
	 * Request Google Places Text Search results for the active category query.
	 *
	 * This keeps the real place discovery server-side, which means the first
	 * render, refreshes, and shared URLs can all rebuild the same results list.
	 */
	function dkc_plan_request_text_search(string $query, string $api_key, ?float $origin_latitude = null, ?float $origin_longitude = null): array
	{
		if ('' === $query) {
			return [
				'places' => [],
				'error'  => '',
			];
		}

		if ('' === trim($api_key)) {
			return [
				'places' => [],
				'error'  => 'Add a valid Google Places API key to load Google place results on-site.',
			];
		}

		$cache_key = dkc_plan_get_google_cache_key(
			'text_search',
			[
				'query'     => $query,
				'latitude'  => $origin_latitude,
				'longitude' => $origin_longitude,
			],
			$api_key
		);
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$request_body = [
			'textQuery'      => $query,
			'pageSize'       => 16,
			'rankPreference' => 'DISTANCE',
		];

		if (null !== $origin_latitude && null !== $origin_longitude) {
			/*
			 * Biasing the search around the chosen starting area keeps Google
			 * results focused on Kona without DKC naming exact businesses.
			 */
			$request_body['locationBias'] = [
				'circle' => [
					'center' => [
						'latitude'  => $origin_latitude,
						'longitude' => $origin_longitude,
					],
					'radius' => 15000.0,
				],
			];
		}

		$response = wp_remote_post(
			'https://places.googleapis.com/v1/places:searchText',
			[
				'timeout' => 15,
				'headers' => [
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $api_key,
					/*
					 * The field mask limits the response to the data the planner
					 * actually renders or needs later for trip selection links.
					 * Coordinates support a small distance hint in the results UI.
					 */
					'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.googleMapsUri,places.location',
				],
				'body'    => wp_json_encode($request_body),
			]
		);

		if (is_wp_error($response)) {
			$result = [
				'places' => [],
				'error'  => 'Google place results are unavailable right now.',
			];

			set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

			return $result;
		}

		$response_code = (int) wp_remote_retrieve_response_code($response);
		$body          = json_decode((string) wp_remote_retrieve_body($response), true);

		if ($response_code < 200 || $response_code >= 300) {
			$result = [
				'places' => [],
				'error'  => 'Google place results are unavailable right now.',
			];

			set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

			return $result;
		}

		$places = [];

		foreach ((array) ($body['places'] ?? []) as $place) {
			$parsed_place = dkc_plan_parse_google_place((array) $place);

			if (!$parsed_place['is_valid']) {
				continue;
			}

			unset($parsed_place['is_valid']);
			$places[] = $parsed_place;
		}

		$result = [
			'places' => $places,
			'error'  => '',
		];

		set_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);

		return $result;
	}
}

if (!function_exists('dkc_plan_request_place_details')) {
	/**
	 * Request Google Place Details for a selected waypoint ID.
	 */
	function dkc_plan_request_place_details(string $place_id, string $api_key): array
	{
		$place_id = dkc_plan_sanitize_place_id($place_id);

		if ('' === $place_id || '' === trim($api_key)) {
			return [
				'place' => [],
				'error' => 'Google place details are unavailable right now.',
			];
		}

		$cache_key = dkc_plan_get_google_cache_key(
			'place_details',
			[
				'place_id' => $place_id,
			],
			$api_key
		);
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://places.googleapis.com/v1/places/' . rawurlencode($place_id),
			[
				'timeout' => 15,
				'headers' => [
					'X-Goog-Api-Key'   => $api_key,
					'X-Goog-FieldMask' => 'id,displayName,formattedAddress,googleMapsUri',
				],
			]
		);

		if (is_wp_error($response)) {
			$result = [
				'place' => [],
				'error' => 'Google place details are unavailable right now.',
			];

			set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

			return $result;
		}

		$response_code = (int) wp_remote_retrieve_response_code($response);
		$body          = json_decode((string) wp_remote_retrieve_body($response), true);

		if ($response_code < 200 || $response_code >= 300) {
			$result = [
				'place' => [],
				'error' => 'Google place details are unavailable right now.',
			];

			set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

			return $result;
		}

		$place = dkc_plan_parse_google_place((array) $body);

		if (!$place['is_valid']) {
			$result = [
				'place' => [],
				'error' => 'Google place details are unavailable right now.',
			];

			set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

			return $result;
		}

		unset($place['is_valid']);

		$result = [
			'place' => $place,
			'error' => '',
		];

		set_transient($cache_key, $result, DAY_IN_SECONDS);

		return $result;
	}
}

if (!function_exists('dkc_plan_request_geocode_location')) {
	/**
	 * Geocode the effective search area once so each Google result can show a
	 * simple distance hint from the current on-page starting area.
	 */
	function dkc_plan_request_geocode_location(string $address, string $api_key): array
	{
		$address = trim($address);

		if ('' === $address || '' === trim($api_key)) {
			return [
				'latitude'  => null,
				'longitude' => null,
			];
		}

		$cache_key = dkc_plan_get_google_cache_key(
			'geocode',
			[
				'address' => $address,
			],
			$api_key
		);
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://maps.googleapis.com/maps/api/geocode/json?' . dkc_plan_build_query(
				[
					'address' => $address,
					'key'     => $api_key,
				]
			),
			[
				'timeout' => 15,
			]
		);

		if (is_wp_error($response)) {
			$result = [
				'latitude'  => null,
				'longitude' => null,
			];

			set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

			return $result;
		}

		$response_code = (int) wp_remote_retrieve_response_code($response);
		$body          = json_decode((string) wp_remote_retrieve_body($response), true);
		$location      = (array) ($body['results'][0]['geometry']['location'] ?? []);

		if (
			$response_code < 200 ||
			$response_code >= 300 ||
			'OK' !== (string) ($body['status'] ?? '') ||
			!isset($location['lat'], $location['lng'])
		) {
			$result = [
				'latitude'  => null,
				'longitude' => null,
			];

			set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

			return $result;
		}

		$result = [
			'latitude'  => (float) $location['lat'],
			'longitude' => (float) $location['lng'],
		];

		set_transient($cache_key, $result, DAY_IN_SECONDS);

		return $result;
	}
}

if (!function_exists('dkc_plan_calculate_distance_miles')) {
	/**
	 * Calculate a straight-line distance between two coordinate pairs.
	 *
	 * Google still owns the real walking route. This only supports a compact,
	 * local-prototype distance hint in the results list.
	 */
	function dkc_plan_calculate_distance_miles(float $origin_latitude, float $origin_longitude, float $destination_latitude, float $destination_longitude): float
	{
		$earth_radius_miles       = 3958.8;
		$latitude_delta           = deg2rad($destination_latitude - $origin_latitude);
		$longitude_delta          = deg2rad($destination_longitude - $origin_longitude);
		$origin_latitude_rad      = deg2rad($origin_latitude);
		$destination_latitude_rad = deg2rad($destination_latitude);
		$haversine                = sin($latitude_delta / 2) ** 2
			+ cos($origin_latitude_rad) * cos($destination_latitude_rad) * sin($longitude_delta / 2) ** 2;
		$central_angle            = 2 * asin(min(1, sqrt($haversine)));

		return $earth_radius_miles * $central_angle;
	}
}

if (!function_exists('dkc_plan_build_distance_label')) {
	/**
	 * Build a concise UI label for a Google result distance hint.
	 */
	function dkc_plan_build_distance_label(float $distance_miles, string $reference_label): string
	{
		$reference_label = trim($reference_label);

		if ($distance_miles < 0.1) {
			if ('' === $reference_label) {
				return 'Less than 0.1 mi away';
			}

			return sprintf('Less than 0.1 mi from %s', $reference_label);
		}

		$rounded_distance = $distance_miles >= 10
			? number_format_i18n($distance_miles, 0)
			: number_format_i18n($distance_miles, 1);

		if ('' === $reference_label) {
			return sprintf('Approx. %s mi away', $rounded_distance);
		}

		return sprintf('Approx. %s mi from %s', $rounded_distance, $reference_label);
	}
}

if (!function_exists('dkc_plan_get_trip_waypoints')) {
	/**
	 * Resolve the selected waypoint IDs into full Google place details in the
	 * exact user-chosen order.
	 */
	function dkc_plan_get_trip_waypoints(array $waypoint_ids, string $api_key): array
	{
		$waypoint_ids = dkc_plan_normalize_waypoint_ids($waypoint_ids);
		$waypoints    = [];
		$messages     = [];

		if (!empty($waypoint_ids) && '' === trim($api_key)) {
			return [
				'waypoints' => [],
				'messages'  => [
					[
						'type' => 'warning',
						'text' => 'Add a valid Google Places API key to load exact Google place details for the trip on-site.',
					],
				],
			];
		}

		foreach ($waypoint_ids as $waypoint_id) {
			$detail_response = dkc_plan_request_place_details($waypoint_id, $api_key);

			if (!empty($detail_response['error']) || empty($detail_response['place'])) {
				$messages[] = [
					'type' => 'warning',
					'text' => 'One selected place could not be loaded from Google and was skipped.',
				];
				continue;
			}

			$waypoints[] = $detail_response['place'];
		}

		return [
			'waypoints' => $waypoints,
			'messages'  => $messages,
		];
	}
}

if (!function_exists('dkc_plan_get_empty_results_state')) {
	/**
	 * Return the results-panel empty state based on the current search state.
	 */
	function dkc_plan_get_empty_results_state(array $planner_state): array
	{
		if (!empty($planner_state['search_results_error'])) {
			return [
				'heading' => 'Google results unavailable',
				'body'    => 'Google place results are unavailable right now. Try again later or open the Google Maps handoff link.',
			];
		}

		if (!$planner_state['has_category']) {
			return [
				'heading' => 'Pick a category to search Google',
				'body'    => 'Choose coffee, food, beaches, shopping, history / culture, or another category to load real place results.',
			];
		}

		return [
			'heading' => 'No matching Google results',
			'body'    => 'Try a different category or change the starting area to search a different part of Kona.',
		];
	}
}

if (!function_exists('dkc_plan_get_empty_preview_state')) {
	/**
	 * Return the preview-panel empty state for the current planner mode.
	 */
	function dkc_plan_get_empty_preview_state(array $planner_state): array
	{
		if (!$planner_state['has_category'] && !$planner_state['has_trip']) {
			return [
				'heading' => 'Start with a category search',
				'body'    => 'Choose a category to load Google results, then add the places you want to turn into trip waypoints.',
			];
		}

		if ($planner_state['has_category'] && !$planner_state['has_trip']) {
			return [
				'heading' => 'Search preview unavailable',
				'body'    => 'The on-page map preview needs a valid Google Maps Embed API key. The Google Maps search link still works.',
			];
		}

		return [
			'heading' => 'Trip preview unavailable',
			'body'    => 'The on-page trip preview needs a valid Google Maps Embed API key. The Google Maps handoff link still works.',
		];
	}
}

if (!function_exists('dkc_plan_build_state_params')) {
	/**
	 * Build canonical planner query-string params that preserve category, trip,
	 * and start state without temporary submit-action fields.
	 */
	function dkc_plan_build_state_params(array $planner_state): array
	{
		$params = [];

		if ('' !== $planner_state['category_key']) {
			$params['category'] = $planner_state['category_key'];
		}

		if (!empty($planner_state['selected_waypoint_ids'])) {
			$params['waypoints'] = array_values($planner_state['selected_waypoint_ids']);
		}

		if ('pier' !== $planner_state['start_mode']) {
			$params['start_mode'] = $planner_state['start_mode'];
		}

		if ('custom' === $planner_state['start_mode'] && '' !== $planner_state['custom_start']) {
			$params['custom_start'] = $planner_state['custom_start'];
		}

		return $params;
	}
}

if (!function_exists('dkc_plan_move_waypoint_ids')) {
	/**
	 * Return the reordered waypoint IDs after moving one entry up or down.
	 */
	function dkc_plan_move_waypoint_ids(array $waypoint_ids, string $place_id, string $direction): array
	{
		$current_index = array_search($place_id, $waypoint_ids, true);

		if (false === $current_index) {
			return $waypoint_ids;
		}

		$next_index = 'up' === $direction ? $current_index - 1 : $current_index + 1;

		if ($next_index < 0 || $next_index >= count($waypoint_ids)) {
			return $waypoint_ids;
		}

		$reordered_waypoint_ids = $waypoint_ids;
		$moved_waypoint_id      = $reordered_waypoint_ids[$current_index];

		array_splice($reordered_waypoint_ids, $current_index, 1);
		array_splice($reordered_waypoint_ids, $next_index, 0, [$moved_waypoint_id]);

		return array_values($reordered_waypoint_ids);
	}
}

if (!function_exists('dkc_plan_build_trip_state')) {
	/**
	 * Build the full server-rendered planner state from the query string.
	 *
	 * The initial render includes everything needed for the no-JavaScript
	 * experience. Focused JSON requests can opt out of results or waypoint-detail
	 * work so ordinary interactions avoid rebuilding the whole planner payload.
	 */
	function dkc_plan_build_trip_state(array $args): array
	{
		$category_catalog       = $args['category_catalog'];
		$start_points           = $args['start_points'];
		$pier_address           = $args['pier_address'];
		$embed_api_key          = trim((string) $args['embed_api_key']);
		$places_api_key         = trim((string) $args['places_api_key']);
		$requested_category     = sanitize_key((string) $args['category_key']);
		$category_key           = isset($category_catalog[$requested_category]) ? $requested_category : '';
		$selected_waypoint_ids  = dkc_plan_normalize_waypoint_ids((array) $args['selected_waypoint_ids']);
		$requested_start        = sanitize_key((string) $args['start_mode']);
		$start_mode             = isset($start_points[$requested_start]) ? $requested_start : 'pier';
		$custom_start           = trim(sanitize_text_field((string) $args['custom_start']));
		$include_results        = !isset($args['include_results']) || (bool) $args['include_results'];
		$include_trip_waypoints = !isset($args['include_trip_waypoints']) || (bool) $args['include_trip_waypoints'];
		$messages               = [];
		$category               = '' !== $category_key ? $category_catalog[$category_key] : [];
		$search_context         = dkc_plan_get_search_context(
			[
				'start_points' => $start_points,
				'start_mode'   => $start_mode,
				'custom_start' => $custom_start,
				'pier_address' => $pier_address,
			]
		);
		$messages               = array_merge($messages, $search_context['messages']);
		$search_query           = '' !== $category_key
			? dkc_plan_build_category_query($category, $search_context['search_area'])
			: '';
		$handoff_search_query   = '' !== $category_key
			? dkc_plan_build_category_query($category, $search_context['search_area'], $search_context['use_current_handoff'])
			: '';
		$search_origin_coordinates = [
			'latitude'  => null,
			'longitude' => null,
		];
		$search_results       = [];
		$search_results_error = '';
		$trip_waypoints       = [];
		$iframe_src           = '';
		$maps_url             = '';
		$maps_link_label      = 'Explore in Google Maps';
		$preview_mode_label   = 'Google place search';
		$overview             = 'Choose a category to load Google results, then add exact places to your trip.';
		$trip_count_label     = 'Trip not started';

		if ($include_results && '' !== $category_key) {
			$search_origin_coordinates = dkc_plan_request_geocode_location($search_context['search_area'], $places_api_key);
			$text_search_response      = dkc_plan_request_text_search(
				$search_query,
				$places_api_key,
				$search_origin_coordinates['latitude'],
				$search_origin_coordinates['longitude']
			);
			$search_results            = $text_search_response['places'];
			$search_results_error      = $text_search_response['error'];

			/*
			 * Result distances are a lightweight straight-line hint from the
			 * current on-page starting area, not the final walking route.
			 */
			if (
				null !== $search_origin_coordinates['latitude'] &&
				null !== $search_origin_coordinates['longitude']
			) {
				foreach ($search_results as $result_index => $result) {
					if (null === $result['latitude'] || null === $result['longitude']) {
						continue;
					}

					$distance_miles = dkc_plan_calculate_distance_miles(
						(float) $search_origin_coordinates['latitude'],
						(float) $search_origin_coordinates['longitude'],
						(float) $result['latitude'],
						(float) $result['longitude']
					);

					$search_results[$result_index]['distance_label'] = dkc_plan_build_distance_label(
						$distance_miles,
						$search_context['preview_start_label']
					);
				}
			}

			if ('' !== $search_results_error) {
				$messages[] = [
					'type' => 'warning',
					'text' => $search_results_error,
				];
			}
		}

		if ($include_trip_waypoints && !empty($selected_waypoint_ids)) {
			$trip_waypoint_response = dkc_plan_get_trip_waypoints($selected_waypoint_ids, $places_api_key);
			$trip_waypoints         = $trip_waypoint_response['waypoints'];
			$messages               = array_merge($messages, $trip_waypoint_response['messages']);
			$selected_waypoint_ids  = array_values(
				array_map(
					static function (array $waypoint): string {
						return $waypoint['id'];
					},
					$trip_waypoints
				)
			);
		}

		$has_category         = '' !== $category_key;
		$has_trip             = !empty($trip_waypoints);
		$search_results_count = count($search_results);
		$search_results_label = $has_category
			? sprintf('%d Google result%s', $search_results_count, 1 === $search_results_count ? '' : 's')
			: 'No Google results loaded';

		if ($has_trip) {
			$trip_count_label      = sprintf('%d waypoint%s selected', count($trip_waypoints), 1 === count($trip_waypoints) ? '' : 's');
			$preview_mode_label    = 'Walking directions';
			$maps_link_label       = 'Open trip in Google Maps';
			$destination           = $trip_waypoints[count($trip_waypoints) - 1];
			$intermediates         = count($trip_waypoints) > 1 ? array_slice($trip_waypoints, 0, -1) : [];
			$waypoint_labels       = array_map(
				static function (array $waypoint): string {
					return $waypoint['label'];
				},
				$trip_waypoints
			);
			$trip_route_description = '';

			if (empty($intermediates)) {
				$trip_route_description = sprintf(
					'Walking directions run from %s to %s.',
					$search_context['handoff_summary'],
					$destination['label']
				);
			} elseif (1 === count($intermediates)) {
				$trip_route_description = sprintf(
					'Walking directions run from %s to %s via %s.',
					$search_context['handoff_summary'],
					$destination['label'],
					$intermediates[0]['label']
				);
			} else {
				$trip_route_description = sprintf(
					'Walking directions run from %s to %s via %s.',
					$search_context['handoff_summary'],
					$destination['label'],
					dkc_plan_format_label_list(array_slice($waypoint_labels, 0, -1))
				);
			}

			$overview = sprintf(
				'%s. %s',
				$trip_count_label,
				$trip_route_description
			);

			if ('' !== $embed_api_key) {
				$iframe_params = [
					'key'         => $embed_api_key,
					'origin'      => $search_context['search_area'],
					'destination' => 'place_id:' . $destination['id'],
					'mode'        => 'walking',
				];

				if (!empty($intermediates)) {
					$iframe_params['waypoints'] = implode(
						'|',
						array_map(
							static function (array $waypoint): string {
								return 'place_id:' . $waypoint['id'];
							},
							$intermediates
						)
					);
				}

				$iframe_src = 'https://www.google.com/maps/embed/v1/directions?' . dkc_plan_build_query($iframe_params);
			} else {
				$messages[] = [
					'type' => 'warning',
					'text' => 'Add a valid Google Maps Embed API key before relying on the on-site trip preview outside this local prototype.',
				];
			}

			$maps_params = [
				'api'                  => '1',
				'destination'          => $destination['address'] ? $destination['address'] : $destination['label'],
				'destination_place_id' => $destination['id'],
				'travelmode'           => 'walking',
			];

			if ('pier' === $start_mode || ('custom' === $start_mode && '' === $custom_start)) {
				$maps_params['origin'] = $pier_address;
			} elseif ('custom' === $start_mode && '' !== $custom_start) {
				$maps_params['origin'] = $custom_start;
			}

			if (!empty($intermediates)) {
				$maps_params['waypoints'] = implode(
					'|',
					array_map(
						static function (array $waypoint): string {
							return $waypoint['address'] ? $waypoint['address'] : $waypoint['label'];
						},
						$intermediates
					)
				);
				$maps_params['waypoint_place_ids'] = implode(
					'|',
					array_map(
						static function (array $waypoint): string {
							return $waypoint['id'];
						},
						$intermediates
					)
				);
			}

			$maps_url = 'https://www.google.com/maps/dir/?' . dkc_plan_build_query($maps_params);
		} elseif ($has_category) {
			$overview = sprintf(
				'Browsing Google results for %s near %s. Add any result to start building a walking trip.',
				$category['label'],
				$search_context['handoff_summary']
			);

			if ('' !== $embed_api_key && '' !== $search_query) {
				$iframe_params = [
					'key' => $embed_api_key,
					'q'   => $search_query,
				];

				$iframe_src = 'https://www.google.com/maps/embed/v1/search?' . dkc_plan_build_query($iframe_params);
			} elseif ('' === $embed_api_key) {
				$messages[] = [
					'type' => 'warning',
					'text' => 'Add a valid Google Maps Embed API key before relying on the on-site search preview outside this local prototype.',
				];
			}

			if ('' !== $handoff_search_query) {
				$maps_url = 'https://www.google.com/maps/search/?' . dkc_plan_build_query(
					[
						'api'   => '1',
						'query' => $handoff_search_query,
					]
				);
			}
		}

		return [
			'category_key'          => $category_key,
			'category'              => $category,
			'has_category'          => $has_category,
			'search_query'          => $search_query,
			'handoff_search_query'  => $handoff_search_query,
			'search_results'        => $search_results,
			'search_results_error'  => $search_results_error,
			'search_results_label'  => $search_results_label,
			'selected_waypoint_ids' => $selected_waypoint_ids,
			'trip_waypoints'        => $trip_waypoints,
			'has_trip'              => $has_trip,
			'start_mode'            => $start_mode,
			'custom_start'          => $custom_start,
			'preview_start_label'   => $search_context['preview_start_label'],
			'handoff_start_label'   => $search_context['handoff_start_label'],
			'start_note_text'       => $search_context['start_note_text'],
			'iframe_src'            => $iframe_src,
			'maps_url'              => $maps_url,
			'maps_link_label'       => $maps_link_label,
			'preview_mode_label'    => $preview_mode_label,
			'overview'              => $overview,
			'trip_count_label'      => $trip_count_label,
			'messages'              => $messages,
		];
	}
}

if (!function_exists('dkc_plan_get_runtime_defaults')) {
	/**
	 * Return the shared runtime defaults used by the template and JSON endpoints.
	 */
	function dkc_plan_get_runtime_defaults(): array
	{
		return [
			'category_catalog' => dkc_plan_get_category_catalog(),
			'start_points'     => dkc_plan_get_start_points(),
			'pier_address'     => 'Kailua Pier, Kailua-Kona, HI 96740',
			'embed_api_key'    => dkc_plan_get_google_embed_api_key(),
			'places_api_key'   => dkc_plan_get_google_places_api_key(),
		];
	}
}

if (!function_exists('dkc_plan_get_request_state_args')) {
	/**
	 * Normalize planner request values from either the front-page query string or
	 * a focused JSON request.
	 */
	function dkc_plan_get_request_state_args(array $request): array
	{
		$selected_waypoint_ids = isset($request['waypoints']) ? wp_unslash((array) $request['waypoints']) : [];
		$selected_waypoint_ids = dkc_plan_normalize_waypoint_ids($selected_waypoint_ids);

		/*
		 * The trip action controls are real submit buttons so their semantics are
		 * correct for browsers and assistive technology. These submitter-specific
		 * values preserve the no-JavaScript fallback by applying the same targeted
		 * state changes that the enhanced client-side planner applies locally.
		 */
		if (isset($request['clear_trip'])) {
			$selected_waypoint_ids = [];
		} elseif (isset($request['remove_waypoint'])) {
			$remove_waypoint_id = dkc_plan_sanitize_place_id(wp_unslash((string) $request['remove_waypoint']));

			if ('' !== $remove_waypoint_id) {
				$selected_waypoint_ids = array_values(
					array_filter(
						$selected_waypoint_ids,
						static function (string $waypoint_id) use ($remove_waypoint_id): bool {
							return $waypoint_id !== $remove_waypoint_id;
						}
					)
				);
			}
		} elseif (isset($request['move_waypoint'])) {
			$move_waypoint_request = sanitize_text_field(wp_unslash((string) $request['move_waypoint']));
			$move_waypoint_parts   = explode(':', $move_waypoint_request, 2);
			$move_waypoint_id      = dkc_plan_sanitize_place_id($move_waypoint_parts[0] ?? '');
			$move_direction        = sanitize_key($move_waypoint_parts[1] ?? '');

			if ('' !== $move_waypoint_id && in_array($move_direction, ['up', 'down'], true)) {
				$selected_waypoint_ids = dkc_plan_move_waypoint_ids($selected_waypoint_ids, $move_waypoint_id, $move_direction);
			}
		}

		return [
			'category_key'          => isset($request['category']) ? wp_unslash((string) $request['category']) : '',
			'selected_waypoint_ids' => $selected_waypoint_ids,
			'start_mode'            => isset($request['start_mode']) ? wp_unslash((string) $request['start_mode']) : 'pier',
			'custom_start'          => isset($request['custom_start']) ? wp_unslash((string) $request['custom_start']) : '',
		];
	}
}

if (!function_exists('dkc_plan_build_runtime_state')) {
	/**
	 * Build planner state from request-style arguments plus optional scoped
	 * overrides, such as skipping results or waypoint detail lookups.
	 */
	function dkc_plan_build_runtime_state(array $request_args, array $overrides = []): array
	{
		return dkc_plan_build_trip_state(array_merge(dkc_plan_get_runtime_defaults(), $request_args, $overrides));
	}
}

if (!function_exists('dkc_plan_build_browse_payload')) {
	/**
	 * Shape the focused browse response used for category results and the
	 * search-mode preview when no trip is active.
	 */
	function dkc_plan_build_browse_payload(array $planner_state): array
	{
		return [
			'categoryKey'       => $planner_state['category_key'],
			'categoryLabel'     => $planner_state['has_category'] ? $planner_state['category']['label'] : 'Not selected',
			'hasCategory'       => $planner_state['has_category'],
			'searchResults'     => array_values($planner_state['search_results']),
			'searchResultsLabel'=> $planner_state['search_results_label'],
			'resultsEmptyState' => dkc_plan_get_empty_results_state($planner_state),
			'messages'          => array_values($planner_state['messages']),
		];
	}
}

if (!function_exists('dkc_plan_build_route_payload')) {
	/**
	 * Shape the focused route response used for trip previews, summary updates,
	 * and the Google Maps handoff link.
	 */
	function dkc_plan_build_route_payload(array $planner_state): array
	{
		return [
			'categoryKey'          => $planner_state['category_key'],
			'categoryLabel'        => $planner_state['has_category'] ? $planner_state['category']['label'] : 'Not selected',
			'selectedWaypointIds'  => array_values($planner_state['selected_waypoint_ids']),
			'tripWaypoints'        => array_values($planner_state['trip_waypoints']),
			'hasTrip'              => $planner_state['has_trip'],
			'tripCountLabel'       => $planner_state['trip_count_label'],
			'iframeSrc'            => $planner_state['iframe_src'],
			'mapsUrl'              => $planner_state['maps_url'],
			'mapsLinkLabel'        => $planner_state['maps_link_label'],
			'previewModeLabel'     => $planner_state['preview_mode_label'],
			'overview'             => $planner_state['overview'],
			'previewStartLabel'    => $planner_state['preview_start_label'],
			'handoffStartLabel'    => $planner_state['handoff_start_label'],
			'startNoteText'        => $planner_state['start_note_text'],
			'emptyPreviewState'    => dkc_plan_get_empty_preview_state($planner_state),
			'messages'             => array_values($planner_state['messages']),
		];
	}
}

if (!function_exists('dkc_plan_validate_ajax_request')) {
	/**
	 * Require a planner nonce and apply a small per-IP request budget.
	 *
	 * The planner remains public, but these checks prevent routine cross-site
	 * requests and keep one visitor from triggering unbounded Google API work
	 * through the focused AJAX endpoints.
	 */
	function dkc_plan_validate_ajax_request(string $scope): void
	{
		$nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['nonce'])) : '';

		if (!wp_verify_nonce($nonce, 'dkc_plan_ajax')) {
			wp_send_json_error(
				[
					'message' => 'The planner request could not be verified. Refresh the page and try again.',
				],
				403
			);
		}

		$remote_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : 'unknown';
		$rate_key       = 'dkc_plan_rate_' . md5($scope . '|' . $remote_address);
		$request_count  = (int) get_transient($rate_key);

		if ($request_count >= 60) {
			wp_send_json_error(
				[
					'message' => 'Planner requests are temporarily limited. Please wait a minute and try again.',
				],
				429
			);
		}

		set_transient($rate_key, $request_count + 1, MINUTE_IN_SECONDS);
	}
}

if (!function_exists('dkc_plan_handle_browse_request')) {
	/**
	 * Return only the browse data needed for category-result updates. Waypoint
	 * details are skipped so opening categories or refreshing distances does not
	 * recalculate the full trip.
	 */
	function dkc_plan_handle_browse_request(): void
	{
		dkc_plan_validate_ajax_request('browse');

		$request_args = dkc_plan_get_request_state_args($_GET);
		$planner_state = dkc_plan_build_runtime_state(
			$request_args,
			[
				'include_results'        => true,
				'include_trip_waypoints' => false,
			]
		);

		wp_send_json_success(
			[
				'browse' => dkc_plan_build_browse_payload($planner_state),
				'route'  => dkc_plan_build_route_payload($planner_state),
			]
		);
	}
}

if (!function_exists('dkc_plan_handle_route_request')) {
	/**
	 * Return only the route and trip data needed after waypoint or start changes.
	 * Category search results are skipped so reorders and add/remove actions stay
	 * narrowly scoped.
	 */
	function dkc_plan_handle_route_request(): void
	{
		dkc_plan_validate_ajax_request('route');

		$request_args = dkc_plan_get_request_state_args($_GET);
		$planner_state = dkc_plan_build_runtime_state(
			$request_args,
			[
				'include_results'        => false,
				'include_trip_waypoints' => true,
			]
		);

		wp_send_json_success(
			[
				'route' => dkc_plan_build_route_payload($planner_state),
			]
		);
	}
}

/*
 * Standalone endpoint dispatch.
 *
 * WordPress routed these handlers through admin-ajax.php. In this copy the same
 * focused JSON requests come back to index.php with an action query parameter,
 * which keeps category browsing and route recalculation separate from the full
 * page render.
 */
$planner_endpoint_action = isset($_GET['action']) ? sanitize_key(wp_unslash((string) $_GET['action'])) : '';

if ('dkc_plan_browse' === $planner_endpoint_action) {
	dkc_plan_handle_browse_request();
}

if ('dkc_plan_route' === $planner_endpoint_action) {
	dkc_plan_handle_route_request();
}

$planner_section_id         = 'dkc-plan-your-day';
$planner_title_id           = $planner_section_id . '-title';
$planner_category_help_id   = $planner_section_id . '-category-help';
$planner_results_heading_id = $planner_section_id . '-results-heading';
$planner_trip_help_id       = $planner_section_id . '-trip-help';
$planner_trip_heading_id    = $planner_section_id . '-trip-heading';
$planner_start_help_id      = $planner_section_id . '-start-help';
$planner_start_panel_id     = $planner_section_id . '-start-panel';
$planner_custom_start_id    = $planner_section_id . '-custom-start';
$planner_preview_heading_id = $planner_section_id . '-preview-heading';
$planner_maps_label_id      = $planner_section_id . '-maps-label';
$planner_action_url         = dkc_plan_current_url();
$planner_request_args       = dkc_plan_get_request_state_args($_GET);
$runtime_defaults           = dkc_plan_get_runtime_defaults();
$pier_address               = $runtime_defaults['pier_address'];
$category_catalog           = dkc_plan_get_category_catalog();
$start_points               = dkc_plan_get_start_points();
$planner_state              = dkc_plan_build_runtime_state($planner_request_args);
$planner_browse_payload     = dkc_plan_build_browse_payload($planner_state);
$planner_route_payload      = dkc_plan_build_route_payload($planner_state);
$results_empty_state        = dkc_plan_get_empty_results_state($planner_state);
$empty_preview_state        = dkc_plan_get_empty_preview_state($planner_state);
$planner_maps_link_enabled = $planner_state['has_trip'] && '' !== $planner_state['maps_url'];
$planner_config = [
	'actionUrl'    => $planner_action_url,
	'ajaxUrl'      => $planner_action_url,
	'ajaxNonce'    => wp_create_nonce('dkc_plan_ajax'),
	'sectionId'    => $planner_section_id,
	'pierAddress'  => $pier_address,
	'startPoints'  => $start_points,
	'categoryCatalog' => $category_catalog,
	'endpoints'    => [
		'browseAction' => 'dkc_plan_browse',
		'routeAction'  => 'dkc_plan_route',
	],
	'initialState' => [
		'category'            => $planner_state['category_key'],
		'selectedWaypointIds' => $planner_state['selected_waypoint_ids'],
		'startMode'           => $planner_state['start_mode'],
		'customStart'         => $planner_state['custom_start'],
	],
	'initialData' => [
		'browse' => $planner_browse_payload,
		'route'  => $planner_route_payload,
	],
];
	?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Plan Your Day in Kona</title>
	<link rel="stylesheet" href="plan.css">
</head>
<body>
<main>
	<section
	class="dkc-plan wrap margin-top-xxlarge"
	id="<?php echo esc_attr($planner_section_id); ?>"
	aria-labelledby="<?php echo esc_attr($planner_title_id); ?>"
	data-plan-root>
	<div class="dkc-plan__surface">
		<header class="dkc-plan__hero">
			<div class="dkc-plan__hero-copy">
				<p class="dkc-plan__eyebrow">Destination Kona Coast Trip Builder</p>
				<h2 id="<?php echo esc_attr($planner_title_id); ?>">Plan Your Day in Kona</h2>
				<p class="dkc-plan__intro">
					Search by category, let Google surface real places, then turn the exact places you choose into a walking trip.
				</p>
			</div>
		</header>

		<form
			class="dkc-plan__layout"
			method="get"
			action="<?php echo esc_url($planner_action_url . '#' . $planner_section_id); ?>"
			data-plan-form>
					<div class="dkc-plan__controls">
						<section class="dkc-plan__card" aria-labelledby="<?php echo esc_attr($planner_section_id . '-start-heading'); ?>">
							<div class="dkc-plan__card-header">
								<div>
									<h3 id="<?php echo esc_attr($planner_section_id . '-start-heading'); ?>">Starting point</h3>
									<p id="<?php echo esc_attr($planner_start_help_id); ?>">
										Choose where the trip starts:
									</p>
								</div>
								<button
									class="dkc-plan__start-toggle"
									type="button"
									aria-controls="<?php echo esc_attr($planner_start_panel_id); ?>"
									aria-expanded="true"
									data-plan-start-toggle
									hidden>
									<span data-plan-start-toggle-label>Hide</span>
									<span class="dkc-plan__start-toggle-icon" aria-hidden="true"></span>
								</button>
							</div>

							<div
								class="dkc-plan__start-panel"
								id="<?php echo esc_attr($planner_start_panel_id); ?>"
								data-plan-start-panel>
								<fieldset class="dkc-plan__fieldset" aria-describedby="<?php echo esc_attr($planner_start_help_id); ?>">
									<legend class="sr-only">Starting point mode</legend>
									<div class="dkc-plan__start-options">
										<?php foreach ($start_points as $start_key => $start_point) : ?>
											<label class="dkc-plan__start-option">
												<input
													type="radio"
													name="start_mode"
													value="<?php echo esc_attr($start_key); ?>"
													<?php checked($planner_state['start_mode'], $start_key); ?>>
												<span class="dkc-plan__start-option-body">
													<span class="dkc-plan__start-title"><?php echo esc_html($start_point['label']); ?></span>
													<span class="dkc-plan__start-description"><?php echo esc_html($start_point['description']); ?></span>
												</span>
											</label>
										<?php endforeach; ?>
									</div>

									<div
										class="dkc-plan__custom-start"
										data-plan-custom-start-wrap
										<?php echo 'custom' === $planner_state['start_mode'] ? '' : 'hidden'; ?>>
										<label for="<?php echo esc_attr($planner_custom_start_id); ?>">Custom starting point</label>
										<input
											id="<?php echo esc_attr($planner_custom_start_id); ?>"
											type="text"
											name="custom_start"
											value="<?php echo esc_attr($planner_state['custom_start']); ?>"
											placeholder="Hotel name or street address"
											autocomplete="street-address"
											data-plan-custom-start>
									</div>
									<p class="dkc-plan__input-help" data-plan-start-note hidden>
										<?php echo esc_html($planner_state['start_note_text']); ?>
									</p>
								</fieldset>

								<input type="hidden" name="category" value="<?php echo esc_attr($planner_state['category_key']); ?>" data-plan-category-input>
								<div data-plan-waypoint-inputs>
									<?php foreach ($planner_state['selected_waypoint_ids'] as $waypoint_id) : ?>
										<input type="hidden" name="waypoints[]" value="<?php echo esc_attr($waypoint_id); ?>" data-plan-waypoint-input>
									<?php endforeach; ?>
								</div>

								<div class="dkc-plan__actions" hidden aria-hidden="true">
									<button class="dkc-plan__submit" type="submit">Update results</button>
								</div>

								<p class="dkc-plan__auto-note" data-plan-auto-note hidden>
									Changes update automatically. Use “Update results” when you want to jump back to the latest server-rendered state manually.
								</p>
								<noscript>
									<p class="dkc-plan__noscript-note">
										JavaScript is off, so searching, adding places, and reordering the trip refreshes the page.
									</p>
								</noscript>
							</div>
						</section>

						<section class="dkc-plan__card" aria-labelledby="<?php echo esc_attr($planner_section_id . '-categories-heading'); ?>">
							<div class="dkc-plan__card-header">
								<div>
									<h3 id="<?php echo esc_attr($planner_section_id . '-categories-heading'); ?>" data-plan-results-heading>What are you looking for?</h3>
									<p id="<?php echo esc_attr($planner_category_help_id); ?>">
										Open a category to search Google for real places. Only one category stays open at a time, and you can switch categories while keeping the same trip.
									</p>
								</div>
								<span class="dkc-plan__count-pill" data-plan-results-count><?php echo esc_html($planner_state['search_results_label']); ?></span>
							</div>

							<div class="dkc-plan__category-accordion" aria-describedby="<?php echo esc_attr($planner_category_help_id); ?>">
								<?php foreach ($category_catalog as $category_key => $category) : ?>
									<?php
									$is_active           = $planner_state['category_key'] === $category_key;
									$category_trigger_id = $planner_section_id . '-category-trigger-' . $category_key;
									$category_panel_id   = $planner_section_id . '-category-panel-' . $category_key;
									?>
									<div class="dkc-plan__category-accordion-item<?php echo $is_active ? ' is-expanded' : ''; ?>">
										<h4 class="dkc-plan__category-accordion-heading">
											<button
												class="dkc-plan__category-trigger"
												type="submit"
												name="category"
												value="<?php echo esc_attr($category_key); ?>"
												id="<?php echo esc_attr($category_trigger_id); ?>"
												aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
												aria-controls="<?php echo esc_attr($category_panel_id); ?>"
												data-plan-category-button
												data-category-key="<?php echo esc_attr($category_key); ?>">
												<span class="dkc-plan__category-trigger-copy">
													<span class="dkc-plan__category-title"><?php echo esc_html($category['label']); ?></span>
													<span class="dkc-plan__category-description"><?php echo esc_html($category['description']); ?></span>
												</span>
												<span class="dkc-plan__category-trigger-side">
													<?php if ($is_active) : ?>
														<span class="dkc-plan__count-pill"><?php echo esc_html($planner_state['search_results_label']); ?></span>
													<?php endif; ?>
													<span class="dkc-plan__category-trigger-icon" aria-hidden="true"></span>
												</span>
											</button>
										</h4>

										<div
											class="dkc-plan__category-panel"
											id="<?php echo esc_attr($category_panel_id); ?>"
											role="region"
											aria-labelledby="<?php echo esc_attr($category_trigger_id); ?>"
											<?php echo $is_active ? '' : 'hidden'; ?>>
											<div
												class="dkc-plan__category-results-scroll"
												data-plan-category-results-panel
												data-category-key="<?php echo esc_attr($category_key); ?>"
												<?php echo $is_active ? '' : 'hidden'; ?>>
												<?php if ($is_active) : ?>
													<?php if (!empty($planner_state['search_results'])) : ?>
															<ul class="dkc-plan__results-list">
																<?php foreach ($planner_state['search_results'] as $result) : ?>
																	<?php
																	$is_in_trip = in_array($result['id'], $planner_state['selected_waypoint_ids'], true);

																	if (!$is_in_trip) {
																		$add_result_announcement = sprintf('Added %s to the trip.', $result['label']);
																	}
																	?>
																<li class="dkc-plan__result-item">
																	<div class="dkc-plan__result-copy">
																		<h4><?php echo esc_html($result['label']); ?></h4>
																		<?php if (!empty($result['distance_label'])) : ?>
																			<p class="dkc-plan__result-distance"><?php echo esc_html($result['distance_label']); ?></p>
																		<?php endif; ?>
																		<p class="dkc-plan__result-meta"><?php echo esc_html($result['address']); ?></p>
																	</div>
																	<div class="dkc-plan__result-tools">
																		<?php if ('' !== $result['maps_uri']) : ?>
																			<a
																				class="dkc-plan__result-link"
																				href="<?php echo esc_url($result['maps_uri']); ?>"
																				target="_blank"
																				rel="noopener noreferrer">
																				View in Google Maps
																			</a>
																		<?php endif; ?>

																		<?php if ($is_in_trip) : ?>
																			<span class="dkc-plan__result-added" aria-label="<?php echo esc_attr($result['label'] . ' is already in the trip'); ?>">In trip</span>
																		<?php else : ?>
																			<button
																				class="dkc-plan__result-add"
																				type="submit"
																				name="waypoints[]"
																				value="<?php echo esc_attr($result['id']); ?>"
																				data-plan-action="add-waypoint"
																				data-place-id="<?php echo esc_attr($result['id']); ?>"
																				aria-label="<?php echo esc_attr(sprintf('Add %s to trip', $result['label'])); ?>"
																				data-plan-announcement="<?php echo esc_attr($add_result_announcement); ?>">
																				Add to trip
																			</button>
																		<?php endif; ?>
																	</div>
																</li>
															<?php endforeach; ?>
														</ul>
													<?php else : ?>
														<div class="dkc-plan__results-empty">
															<h4><?php echo esc_html($results_empty_state['heading']); ?></h4>
															<p><?php echo esc_html($results_empty_state['body']); ?></p>
														</div>
													<?php endif; ?>
												<?php endif; ?>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</section>

					</div>

				<div class="dkc-plan__preview-panel">
						<section class="dkc-plan__card" aria-labelledby="<?php echo esc_attr($planner_trip_heading_id); ?>">
							<div class="dkc-plan__card-header">
								<div>
									<h3 id="<?php echo esc_attr($planner_trip_heading_id); ?>" tabindex="-1" data-plan-trip-heading>Trip waypoints</h3>
									<p id="<?php echo esc_attr($planner_trip_help_id); ?>">
										Add exact places from Google, then drag to reorder or use the move controls. The visible order becomes the walking trip order.
									</p>
								</div>
									<div class="dkc-plan__trip-header-actions" data-plan-trip-header-actions>
										<span class="dkc-plan__count-pill" data-plan-trip-count><?php echo esc_html($planner_state['trip_count_label']); ?></span>
										<?php if (!empty($planner_state['selected_waypoint_ids'])) : ?>
											<button
												class="dkc-plan__clear-link"
												type="submit"
												name="clear_trip"
												value="1"
												data-plan-clear-trip
												data-plan-action="clear-trip"
												data-plan-announcement="Cleared the trip waypoints.">
												Clear trip
											</button>
										<?php endif; ?>
									</div>
								</div>

							<div data-plan-trip-region>
							<?php if (!empty($planner_state['trip_waypoints'])) : ?>
								<ol class="dkc-plan__trip-list" aria-describedby="<?php echo esc_attr($planner_trip_help_id); ?>" data-plan-trip-list>
									<?php foreach ($planner_state['trip_waypoints'] as $index => $waypoint) : ?>
										<li class="dkc-plan__trip-item" data-waypoint-id="<?php echo esc_attr($waypoint['id']); ?>">
											<div class="dkc-plan__trip-main">
											<span class="dkc-plan__trip-number" aria-hidden="true"><?php echo esc_html((string) ($index + 1)); ?></span>
											<div class="dkc-plan__trip-copy">
												<h4><?php echo esc_html($waypoint['label']); ?></h4>
												<p class="dkc-plan__trip-meta"><?php echo esc_html($waypoint['address']); ?></p>
											</div>
										</div>
										<div class="dkc-plan__trip-tools">
											<span class="dkc-plan__drag-handle" aria-hidden="true">
											<img src="icons/grip-vertical-solid.svg" width="20" alt="" loading="lazy" role="presentation">Drag</span>
												<div class="dkc-plan__reorder-links">
													<?php if ($index > 0) : ?>
														<button
															class="dkc-plan__reorder-button dkc-plan__reorder-button--up"
															type="submit"
															name="move_waypoint"
															value="<?php echo esc_attr($waypoint['id'] . ':up'); ?>"
															data-plan-action="move-waypoint"
															data-direction="up"
															data-place-id="<?php echo esc_attr($waypoint['id']); ?>"
															aria-label="<?php echo esc_attr(sprintf('Move %s up', $waypoint['label'])); ?>"
															data-plan-announcement="<?php echo esc_attr(sprintf('Moved %s up.', $waypoint['label'])); ?>">
															Move up
														</button>
													<?php else : ?>
														<button
															class="dkc-plan__reorder-disabled dkc-plan__reorder-button dkc-plan__reorder-button--up"
															type="button"
															disabled>
															Move up
														</button>
													<?php endif; ?>

													<?php if ($index < count($planner_state['trip_waypoints']) - 1) : ?>
														<button
															class="dkc-plan__reorder-button dkc-plan__reorder-button--down"
															type="submit"
															name="move_waypoint"
															value="<?php echo esc_attr($waypoint['id'] . ':down'); ?>"
															data-plan-action="move-waypoint"
															data-direction="down"
															data-place-id="<?php echo esc_attr($waypoint['id']); ?>"
															aria-label="<?php echo esc_attr(sprintf('Move %s down', $waypoint['label'])); ?>"
															data-plan-announcement="<?php echo esc_attr(sprintf('Moved %s down.', $waypoint['label'])); ?>">
															Move down
														</button>
													<?php else : ?>
														<button
															class="dkc-plan__reorder-disabled dkc-plan__reorder-button dkc-plan__reorder-button--down"
															type="button"
															disabled>
															Move down
														</button>
													<?php endif; ?>
												</div>
												<button
													type="submit"
													name="remove_waypoint"
													value="<?php echo esc_attr($waypoint['id']); ?>"
													data-plan-action="remove-waypoint"
													data-place-id="<?php echo esc_attr($waypoint['id']); ?>"
													aria-label="<?php echo esc_attr(sprintf('Remove %s from trip', $waypoint['label'])); ?>"
													data-plan-announcement="<?php echo esc_attr(sprintf('Removed %s from the trip.', $waypoint['label'])); ?>">
													Remove
												</button>
											</div>
										</li>
									<?php endforeach; ?>
							</ol>
						<?php else : ?>
							<div class="dkc-plan__trip-empty" data-plan-trip-empty>
								<h4>Start building the trip</h4>
								<p>Search Google by category, then add the exact places you want as walking-trip waypoints.</p>
							</div>
						<?php endif; ?>
						</div>
					</section>

					<section class="dkc-plan__card dkc-plan__preview-card" aria-labelledby="<?php echo esc_attr($planner_preview_heading_id); ?>" data-plan-preview-card>
					<div class="dkc-plan__card-header">
						<div>
							<h3 id="<?php echo esc_attr($planner_preview_heading_id); ?>" tabindex="-1" data-plan-preview-heading>Trip preview</h3>
							<p>The map starts as a Google place search and switches to walking directions once you add exact waypoints.</p>
						</div>
					</div>

					<div class="sr-only" aria-live="polite" data-plan-live-region></div>

					<ul class="dkc-plan__messages" data-plan-messages <?php echo empty($planner_state['messages']) ? 'hidden' : ''; ?>>
						<?php foreach ($planner_state['messages'] as $message) : ?>
							<li class="dkc-plan__message dkc-plan__message--<?php echo esc_attr($message['type']); ?>">
								<?php echo esc_html($message['text']); ?>
							</li>
						<?php endforeach; ?>
					</ul>

						<div class="dkc-plan__map-wrap" data-plan-map-wrap <?php echo '' !== $planner_state['iframe_src'] ? '' : 'hidden'; ?>>
							<iframe
								class="dkc-plan__map-frame"
							title="Google Maps trip preview"
							src="<?php echo '' !== $planner_state['iframe_src'] ? esc_url($planner_state['iframe_src']) : ''; ?>"
							loading="lazy"
							referrerpolicy="no-referrer-when-downgrade"
							allowfullscreen
							data-plan-iframe></iframe>
					</div>

						<div class="dkc-plan__preview-empty" data-plan-preview-empty <?php echo '' !== $planner_state['iframe_src'] ? 'hidden' : ''; ?>>
							<h4 data-plan-preview-empty-heading><?php echo esc_html($empty_preview_state['heading']); ?></h4>
							<p data-plan-preview-empty-body><?php echo esc_html($empty_preview_state['body']); ?></p>
						</div>

						<div class="dkc-plan__summary" data-plan-summary>
							<div class="dkc-plan__summary-top">
								<p class="dkc-plan__summary-eyebrow">Trip summary</p>
								<p class="dkc-plan__summary-count" data-plan-summary-count><?php echo esc_html($planner_state['trip_count_label']); ?></p>
							</div>
							<p class="dkc-plan__summary-overview" data-plan-summary-overview hidden><?php echo esc_html($planner_state['overview']); ?></p>
							<dl class="dkc-plan__summary-grid" hidden>
								<div>
									<dt>Active category</dt>
									<dd data-plan-summary-category><?php echo esc_html($planner_state['has_category'] ? $planner_state['category']['label'] : 'Not selected'); ?></dd>
								</div>
								<div>
									<dt>Google results</dt>
									<dd data-plan-summary-results><?php echo esc_html($planner_state['search_results_label']); ?></dd>
								</div>
								<div>
									<dt>Google Maps start</dt>
									<dd data-plan-summary-handoff-start><?php echo esc_html($planner_state['handoff_start_label']); ?></dd>
								</div>
								<div>
									<dt>On-page preview start</dt>
									<dd data-plan-summary-preview-start><?php echo esc_html($planner_state['preview_start_label']); ?></dd>
								</div>
								<div>
									<dt>Map mode</dt>
									<dd data-plan-summary-mode><?php echo esc_html($planner_state['preview_mode_label']); ?></dd>
								</div>
								<div>
									<dt>Exact waypoints</dt>
									<dd data-plan-summary-waypoints><?php echo esc_html($planner_state['trip_count_label']); ?></dd>
								</div>
							</dl>


							<div class="dkc-plan__summary-handoff">
								<p class="dkc-plan__summary-handoff-label" id="<?php echo esc_attr($planner_maps_label_id); ?>">Open in Google Maps</p>
								<a
									class="dkc-plan__maps-link dkc-plan__maps-link--summary<?php echo $planner_maps_link_enabled ? '' : ' is-disabled'; ?>"
									<?php echo $planner_maps_link_enabled ? 'href="' . esc_url($planner_state['maps_url']) . '"' : ''; ?>
									target="_blank"
									rel="noopener noreferrer"
									data-plan-open-link
									<?php echo $planner_maps_link_enabled ? '' : 'aria-disabled="true"'; ?>>
									<span data-plan-open-link-label>Go!</span>
									<span class="sr-only"> Open in Google Maps</span>
								</a>
							</div>
						</div>
					</section>
			</div>
		</form>
	</div>

	<script type="application/json" data-plan-config><?php echo wp_json_encode($planner_config); ?></script>
</section>
<script src="plan.js" defer></script>
</main>
</body>
</html>
