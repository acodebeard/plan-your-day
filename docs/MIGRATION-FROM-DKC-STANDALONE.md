# Migration From Standalone DKC Runtime

The current WordPress plugin does **not** perform automatic migration from the
older standalone DKC runtime.

If you are upgrading an existing deployment, plan to copy the required values
into `Settings > Plan Your Day` manually.

## What Does Not Migrate Automatically

The plugin does not currently:

- read legacy `dkc_plan_*` WordPress options
- import the old standalone `c88e3e98.php` config file
- detect standalone categories and seed them into plugin settings

A fresh plugin activation writes only the plugin version, schema version, and
default settings payload.

## Settings You Need To Re-Enter

Before switching public traffic, collect these values from the standalone
runtime:

- default location label
- default location address or search phrase
- default latitude and longitude if you use them
- default Google Place ID if you already track it
- Maps Embed API key
- Places API key
- Geocoding API key, if separate
- custom categories and their search phrases
- cache TTLs, rate limits, and trusted proxy CIDRs if you changed defaults

## Manual Migration Workflow

1. Keep the standalone implementation available until the plugin is configured.
2. Activate the plugin in WordPress.
3. Open `Settings > Plan Your Day`.
4. Re-enter the required location settings first.
5. Re-enter Google API keys.
6. Rebuild any custom categories from the standalone configuration.
7. Re-apply any non-default cache, rate-limit, and proxy settings.
8. Test shortcode rendering and REST planner actions on a non-public page.
9. Only after the plugin is verified, replace the old public entry point with
   the plugin shortcode.

## Verification Checklist

Confirm all of the following before you remove the standalone page:

- the setup notice is gone from the plugin settings screen
- the planner renders on a shortcode page
- browse requests return the expected category results
- route preview requests work with the configured starting location
- category labels and ordering match the intended public experience

## Known Limitation

This guide is the current supported path because the plugin no longer ships the
old `LegacyConfigMigrator`. A future issue may restore automatic import, but
until then upgrades from the standalone runtime are manual.
