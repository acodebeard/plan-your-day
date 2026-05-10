=== Plan Your Day ===
Contributors: acodebeard
Tags: planning, maps, wayfinding
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A configurable day planning plugin for WordPress.

== Description ==

Plan Your Day is a configurable day planning plugin for WordPress.

Current development builds include the plugin scaffold, Settings API
registration, admin settings screen, Google API client abstraction, Google API
caching, frontend planner rendering, REST endpoints, and extracted planner
helper services.

Block wrapper polish and production QA are still in progress.

== Installation ==

Release zip:

1. Build or download the packaged `plan-your-day` release zip.
2. Upload it through the Plugins screen in WordPress.
3. Activate the plugin. Release zips include generated Composer autoload files.

Source checkout:

1. Copy or symlink the `plan-your-day` directory to `/wp-content/plugins/`.
2. Run `composer install` inside the plugin directory to generate
   `vendor/autoload.php`.
3. Activate the plugin through the Plugins screen in WordPress.
4. Open Settings > Plan Your Day and configure the required default location
   and Google API keys.

== Configuration ==

Settings are stored in the `plan_your_day_settings` option and managed through
Settings > Plan Your Day.

Current settings include:

* Default location label, address/search phrase, latitude, longitude, and Place ID.
* Allowed start modes, max waypoints, result count, distance unit, map preview,
  and Google Maps handoff toggles.
* Browser-facing Maps Embed API key.
* Server-side Places and Geocoding API keys.
* Google API timeout and cache TTLs.
* Rate-limit value and trusted proxy CIDRs for future endpoint protection.

== External services ==

Plan Your Day uses Google services to load place results, place details,
geocoding data, embedded map previews, and Google Maps handoff links.

The plugin can send data to Google from the server when:

* a visitor runs a category or custom place search
* the plugin geocodes a configured or custom starting area
* the plugin resolves selected waypoint place details

The plugin can also send data to Google from the visitor's browser when:

* the frontend loads a Google Maps Embed preview from `https://www.google.com/maps/embed/v1/search` or `https://www.google.com/maps/embed/v1/directions`
* a visitor opens the generated Google Maps handoff link

Depending on the interaction, data sent to Google can include:

* search phrases
* configured or visitor-provided starting locations
* selected Google Place IDs
* visitor IP address and the browser-facing Maps Embed API key when the browser loads an embedded map preview
* origin text plus waypoint and destination Place IDs when the browser loads an embedded directions preview
* route waypoint information needed to build map previews or handoff URLs

Google provides these services. Review their terms and privacy information:

* https://policies.google.com/privacy
* https://cloud.google.com/maps-platform/terms

== Frequently Asked Questions ==

= Is this ready for production? =

Not yet. The plugin is installable and includes the planner frontend, REST
endpoints, and admin/settings flow, but production QA and release hardening are
still in progress.

= Where is the developer documentation? =

See the repository `docs/` directory for installation, architecture, settings,
security, and troubleshooting notes.

== Changelog ==

= Unreleased =
* Added Settings API registration and admin settings screen.
* Added Google API client abstraction and transient-backed caching.
* Added extracted planner helper services for shortcode and REST work.
* Added a reproducible release zip builder for WordPress admin installs.

= 0.1.0 =
* Initial plugin scaffold (GH issue #20): directory structure, main plugin file, activation / deactivation hooks, uninstall routine, PSR-4 autoloading.
