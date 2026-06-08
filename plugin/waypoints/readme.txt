=== Waypoints ===
Contributors: acodebeard
Tags: planning, maps, wayfinding
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A configurable day planning plugin for WordPress.

== Description ==

Waypoints is a configurable day planning plugin for WordPress.

Use Waypoints to add a searchable trip-planning interface to a page or post.
Visitors can search Google place results, add stops to a waypoint list, reorder
their trip, preview the route when map embeds are configured, and open the route
in Google Maps.

== Credits ==

Special thanks to Hagan Franks (https://github.com/hagan) and Christopher
Reaume (https://github.com/datapoke) for development help.

Thanks also to Destination Kona Coast (https://destinationkonacoast.com) for
the idea that started the project.

== Installation ==

Release zip:

1. Build or download the packaged `waypoints` release zip.
2. Upload it through the Plugins screen in WordPress.
3. Activate the plugin. Release zips include generated Composer autoload files.

Source checkout:

1. Copy or symlink this plugin directory as `/wp-content/plugins/waypoints/`.
2. Run `composer install` inside the plugin directory to generate
   `vendor/autoload.php`.
3. Activate the plugin through the Plugins screen in WordPress.
4. Open Settings > Waypoints and configure the required default location
   and Google API keys.

== Configuration ==

Settings are managed through Settings > Waypoints.

Current settings include:

* Default location label, address/search phrase, latitude, longitude, and Place ID.
* Allowed start modes, max waypoints, result count, distance unit, map preview,
  and Google Maps handoff toggles.
* Editable categories and interface copy.
* Browser-facing Maps Embed API key.
* Server-side Places and Geocoding API keys.
* Google API timeout and cache TTLs.
* Rate-limit value and trusted proxy CIDRs for public endpoint protection.
* Admin-only API call counter for troubleshooting request behavior.

== External services ==

Waypoints uses Google services to load place results, place details,
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

= What do I need before using it on a live site? =

Configure the required default location and Google API keys in Settings >
Waypoints. For best results, restrict Google API keys in Google Cloud Console
and test the planner flow on a staging site before adding it to a public page.

= How do I display the planner? =

Use the Waypoints block in the block editor or add `[waypoints]` to a page,
post, or Shortcode block. The optional `action_url` shortcode attribute and
`Action URL` block setting can submit planner updates to a specific page URL.

= Where is the developer documentation? =

See the repository `docs/` directory for installation, usage, admin, release,
architecture, settings, security, and troubleshooting notes.

== Changelog ==

= 0.5 =
* Added block-editor and shortcode entry points that share the same planner renderer.
* Added admin-editable categories and interface copy settings.
* Added REST-powered planner interactions, rate limiting, and Google API admin tools.
* Added a reproducible release zip builder and manual release-process documentation.
* Added color mode controls, Noto Sans assets, and a cleaned-up settings interface.
* Added an upgrade cleanup that prunes removed interface-copy settings from existing installs.

= 0.1.0 =
* Initial plugin scaffold (GH issue #20): directory structure, main plugin file, activation / deactivation hooks, uninstall routine, PSR-4 autoloading.
