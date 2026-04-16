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

if (!defined('DKC_PLAN_GOOGLE_API_KEY')) {
	define('DKC_PLAN_GOOGLE_API_KEY', '');
}
