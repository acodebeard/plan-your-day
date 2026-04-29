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
plugin/plan-your-day/
```

For local development, copy or symlink that directory into a WordPress install:

```sh
ln -s /path/to/plan-your-day/plugin/plan-your-day /path/to/wordpress/wp-content/plugins/plan-your-day
```

Generate the Composer autoloader from the plugin directory:

```sh
cd /path/to/plan-your-day/plugin/plan-your-day
composer install
```

Then activate the plugin from the WordPress admin Plugins screen or with WP-CLI:

```sh
wp --path=/path/to/wordpress plugin activate plan-your-day
```

## Local DKC Test Install

The current local target test install is:

```text
/opt/lampp/htdocs/dkc
```

The local plugin install is a symlink:

```text
/opt/lampp/htdocs/dkc/wp-content/plugins/plan-your-day
  -> /opt/lampp/htdocs/plan-your-day/plugin/plan-your-day
```

Useful local commands:

```sh
sudo /opt/lampp/lampp start

/opt/lampp/bin/php /usr/local/bin/wp \
  --path=/opt/lampp/htdocs/dkc \
  --url=http://localhost/dkc \
  plugin status plan-your-day \
  --allow-root
```

The admin settings screen is:

```text
http://localhost/dkc/wp-admin/options-general.php?page=plan-your-day
```

If the target WordPress runtime has legacy planner settings to preserve, define
`PLAN_YOUR_DAY_LEGACY_CONFIG_FILE` to a PHP file that returns a legacy settings
array, or provide the same array through the `plan_your_day_legacy_settings`
filter before activating the plugin. Activation imports those values into the
plugin settings only when the plugin settings are still effectively empty.

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
