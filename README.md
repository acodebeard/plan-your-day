# Plan Your Day

Plan Your Day is a configurable WordPress plugin for building day-trip planners
with Google Maps and Places data. The plugin source lives in
`plugin/plan-your-day/`.

This repository is no longer the earlier standalone PHP runtime. The legacy
root-level files remain only as migration/source material until the plugin
reaches feature parity.

## Status

The plugin is under active migration from the earlier standalone implementation.
Completed foundation work includes:

- WordPress plugin scaffold, activation/deactivation hooks, uninstall routine,
  release metadata, and Composer PSR-4 autoloading.
- Settings API registration for default location, planner behavior, Google API
  keys, cache TTLs, rate-limit settings, and trusted proxy configuration.
- Admin settings screen with required-configuration notices and a Google API
  cache clear tool.
- Google API client abstraction with server-side key handling, explicit field
  masks, safe result objects, configurable timeouts, and transient-backed
  caching.
- Extracted planner helper services for place parsing, waypoint state, distance
  labels, map URLs, start context, request state, and request-origin checks.
- Shortcode-based frontend rendering with plugin-scoped assets and a shared
  planner renderer.
- WordPress REST browse/route endpoints with POST-only requests, guest-safe
  visitor token validation, same-site request checks, trusted-proxy-aware client
  IP resolution, and file-backed rate limiting.
- Frontend JavaScript that updates browse and trip state through REST instead of
  full-page planner reloads.

Migration helpers, CI, block-editor support, configurable categories/copy, and
production documentation are still tracked in GitHub issues.

## Standalone Upgrade Note

Upgrading from the older standalone DKC runtime is currently a **manual**
migration. The plugin does not import legacy `dkc_plan_*` options or the old
standalone config file automatically.

Use [docs/MIGRATION-FROM-DKC-STANDALONE.md](docs/MIGRATION-FROM-DKC-STANDALONE.md)
before replacing an existing standalone deployment.

## Requirements

- WordPress 6.8 or newer
- PHP 8.2 or newer
- Composer for source checkouts
- Google Cloud keys for Maps Embed API, Places API (New), and optionally
  Geocoding API

## Repository Layout

- `plugin/plan-your-day/` contains the WordPress plugin source.
- `plugin/plan-your-day/plan-your-day.php` is the main plugin file.
- `plugin/plan-your-day/src/` contains namespaced plugin classes.
- `plugin/plan-your-day/src/Settings/` contains option defaults and
  sanitization.
- `plugin/plan-your-day/src/Admin/` contains the settings UI.
- `plugin/plan-your-day/src/Google/` contains Google API client and cache
  classes.
- `docs/` contains the migration plan, issue breakdown, and implementation
  notes.
- Root-level `index.php`, `plan.css`, `plan.js`, `icons/`, and private key
  examples are legacy standalone assets used as migration reference.

## Documentation

Start with [docs/README.md](docs/README.md). Current docs cover installation,
architecture, settings, security, and troubleshooting for the work implemented
so far.

## Local Source Installation

1. Copy or symlink `plugin/plan-your-day/` into a WordPress installation at
   `wp-content/plugins/plan-your-day/`.
2. From the plugin directory, install the Composer autoloader:

   ```sh
   composer install
   ```

3. Activate **Plan Your Day** from the WordPress Plugins screen.
4. Open **Settings > Plan Your Day**.
5. Configure the required default location and Google API keys.

Release zips should include generated Composer autoload files so production
sites do not need to run Composer.

## Configuration

Plugin settings are stored in the `plan_your_day_settings` option and are
managed through the WordPress Settings API. Current settings include:

- Default location label, address/search phrase, latitude, longitude, and Place
  ID.
- Allowed starting-point modes.
- Maximum waypoints and result count.
- Distance unit.
- Map preview and Google Maps handoff toggles.
- Browser-facing Maps Embed API key.
- Server-side Places and Geocoding API keys.
- Google API timeout and cache TTLs.
- Rate-limit value and trusted proxy CIDRs for public endpoint protection.

Server-side Google API keys must not be exposed to frontend runtime config.

## Development Notes

- Keep plugin code generic and location-agnostic. Destination-specific values
  belong in settings or migration data.
- Do not rely on PHP sessions in plugin code.
- Use WordPress-native APIs for escaping, sanitization, options, REST,
  transients/object cache, HTTP requests, and asset registration.
- Keep the legacy standalone deployment untouched until the plugin MVP is ready.
- Update `docs/PLAN-YOUR-DAY-PLUGIN-TODO.md` and GitHub issues as migration
  work lands.

## Useful Checks

Run PHP syntax checks from the repository root:

```sh
find plugin/plan-your-day -name '*.php' -print -exec php -l {} \;
```

Search for legacy integration-specific strings in plugin code:

```sh
rg "localhost/dkc|DKC|dkc|pier" plugin/plan-your-day
```

No production test suite is wired yet; automated checks are tracked separately.
