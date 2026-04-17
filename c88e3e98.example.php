<?php
/*
|--------------------------------------------------------------------------
| GOOGLE MAPS API KEY EXAMPLE
|--------------------------------------------------------------------------
|
| Copy this file to c88e3e98.php, then replace the empty string below with the
| Google Maps API key for the standalone planner.
|
| The key needs access to:
| - Maps Embed API, for the on-page search and walking-directions iframe.
| - Places API (New), for text search and place details.
| - Geocoding API, for distance hints from the selected starting point.
|
| Keep the key restricted in Google Cloud. The Maps Embed use is visible in the
| browser by design, while Places and Geocoding requests stay server-side in
| index.php.
|
*/

/*
 * Only index.php should load this file. Direct requests fail closed before
 * defining or outputting configuration values.
 */
if (!defined('DKC_PLAN_STANDALONE_BOOTSTRAP')) {
	http_response_code(403);
	exit;
}

/*
 * SECURITY: two separate keys are strongly recommended.
 *
 * The Embed key is visible in the browser (it's the src= of the Maps
 * iframe). The Places key is only used server-side and should never
 * reach the browser. If you supply only DKC_PLAN_GOOGLE_API_KEY the
 * planner will use it for both, and you MUST restrict that single key
 * in Google Cloud to Maps Embed API + your site's referring domain.
 *
 * With two keys you can scope the Embed key to Maps Embed API only and
 * the Places key to Places API (New) + Geocoding API, so a leaked
 * browser-side key can't drive Places calls.
 */
if (!defined('DKC_PLAN_GOOGLE_EMBED_API_KEY')) {
	define('DKC_PLAN_GOOGLE_EMBED_API_KEY', '');
}

if (!defined('DKC_PLAN_GOOGLE_PLACES_API_KEY')) {
	define('DKC_PLAN_GOOGLE_PLACES_API_KEY', '');
}

// Legacy single-key fallback. If the two above are empty, this is used
// for both server-side Places and browser-side Embed.
if (!defined('DKC_PLAN_GOOGLE_API_KEY')) {
	define('DKC_PLAN_GOOGLE_API_KEY', '');
}

/*
 * Optional: pin the canonical base URL used for form actions and
 * self-referential links. This hardens the planner against
 * Host-header poisoning when the site sits behind a shared cache.
 *
 * if (!defined('DKC_PLAN_BASE_URL')) {
 *     define('DKC_PLAN_BASE_URL', 'https://example.com/plan-your-day/');
 * }
 */
