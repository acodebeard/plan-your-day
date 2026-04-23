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
caching, and extracted planner helper services.

The public planner frontend, shortcode, block wrapper, REST endpoints, visitor
token protection, release build process, migration helper, and production QA
are still in progress.

== Installation ==

Release zip:

1. Upload the built `plan-your-day` release zip through the Plugins screen in WordPress.
2. Activate the plugin. Release zips include generated Composer autoload files.

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

== Frequently Asked Questions ==

= Is this ready for production? =

No. The plugin is installable and the admin/settings/API foundations are in
place, but the public planner frontend and REST endpoints are not complete.

= Where is the developer documentation? =

See the repository `docs/` directory for installation, architecture, settings,
security, and troubleshooting notes.

== Changelog ==

= Unreleased =
* Added Settings API registration and admin settings screen.
* Added Google API client abstraction and transient-backed caching.
* Added extracted planner helper services for future shortcode and REST work.

= 0.1.0 =
* Initial plugin scaffold (GH issue #20): directory structure, main plugin file, activation / deactivation hooks, uninstall routine, PSR-4 autoloading.
