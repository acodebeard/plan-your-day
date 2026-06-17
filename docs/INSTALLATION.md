# Installation

## Requirements

- WordPress 6.8 or newer.
- PHP 8.2 or newer.
- Composer for source checkouts.
- Google Cloud keys for Maps Embed API, Places API (New), and optionally
  Geocoding API.

## Source Checkout Install

The plugin source lives at:

```text
plugin/waypoints/
```

For local development, copy or symlink that directory into a WordPress install:

```sh
ln -s /path/to/waypoints/plugin/waypoints /path/to/wordpress/wp-content/plugins/waypoints-trip-planner
```

Generate the Composer autoloader from the plugin directory:

```sh
cd /path/to/waypoints/plugin/waypoints
composer install
```

Then activate the plugin from the WordPress admin Plugins screen or with WP-CLI:

```sh
wp --path=/path/to/wordpress plugin activate waypoints-trip-planner
```

In the WordPress admin, the plugin appears as **Waypoints: Trip Planner**. Open
**Settings > Waypoints: Trip Planner** after activation.

## Local Test Install

One local development setup can look like this:

```text
/path/to/wordpress
/path/to/wordpress/wp-content/plugins/waypoints-trip-planner
  -> /path/to/waypoints/plugin/waypoints
```

Useful local commands:

```sh
wp \
  --path=/path/to/wordpress \
  --url=https://example.test \
  plugin status waypoints-trip-planner \
```

The admin settings screen is:

```text
https://example.test/wp-admin/options-general.php?page=waypoints
```

## Activation Effects

Activation writes the current plugin and schema versions:

- `plan_your_day_version`
- `plan_your_day_schema_version`

The plugin settings option is:

- `plan_your_day_settings`

The settings option is registered through the WordPress Settings API and is
read through `Settings::get_all()`, which merges saved values with plugin
defaults.

## Release Zip Expectations

Release zips should include generated Composer autoload files. Production sites
should not need to run Composer.

The source repository ignores `vendor/`, but the release artifact should include
the built `vendor/autoload.php`. The plugin `.distignore` controls release zip
exclusions separately from source control ignore rules.

WordPress.org listing-only files, such as the formatted `readme.html` preview
and root `assets/` icons/screenshots, are maintained in source for submission
workflows but are not required in the installable release zip.

Build the installable artifact from the source checkout with:

```sh
cd plugin/waypoints
./tools/build-release-zip.sh
```

The script writes `dist/waypoints-trip-planner-1.0.2.zip` at the repository root, which
can be uploaded through the WordPress Plugins screen.
