# Waypoints

Waypoints is a configurable WordPress plugin for building day-trip planners with
Google Maps and Places data. The plugin source lives in
`plugin/plan-your-day/`.

## Status

The latest published release is **v0.5**. The project is in v1.0 release
preparation, with UI polish and final hardening work happening in focused pull
requests.

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
- Block-editor rendering through a thin server-rendered wrapper around the
  shared planner renderer.
- WordPress REST browse/route endpoints with POST-only requests, guest-safe
  visitor token validation, same-site request checks, trusted-proxy-aware client
  IP resolution, and configurable rate limiting.
- Frontend JavaScript that updates browse and trip state through REST instead of
  full-page planner reloads.
- Admin-editable categories, admin-editable interface copy, cache-clear tools,
  and a Google API test tool.
- Browser smoke coverage for shortcode and block renders, category browsing,
  load-more, waypoint add/reorder/remove, clear-trip behavior, focus recovery,
  and narrow-viewport start-options behavior.
- GitHub Actions quality checks for PHP syntax, PHPCS, PHPUnit, browser smoke,
  and WordPress Plugin Check.

Current v1.0 hardening work is split into open PRs for:

- Removing bundled icon assets in favor of CSS-only UI details.
- Making REST token bootstrap cache behavior safer.
- Moving rate-limit state to expiring transients.
- Hardening Google geocode failure handling.
- Keeping the Plugin Check workflow stable and quiet.

PHPStan is available through the repo-local Composer tooling and is included in
the stricter WordPress.org submission-readiness scan. The regular `Plugin
Quality` workflow remains focused on PHP syntax, PHPCS, PHPUnit, browser smoke,
and WordPress Plugin Check.

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
- `docs/` contains installation, usage, admin, release, architecture, settings,
  security, troubleshooting, and historical planning notes for the plugin.

## Naming Note

The public plugin name is **Waypoints**. The source still uses `plan-your-day`
for the plugin directory and text domain, `[plan_your_day]` for the shortcode,
`plan_your_day_*` for settings/options/hooks, `Acodebeard\PlanYourDay` for the
PHP namespace, and related REST, asset, and CSS identifiers because the plugin
was renamed after those compatibility surfaces already existed.

## Documentation

Start with [docs/README.md](docs/README.md). Current docs cover installation,
frontend usage, admin workflows, release steps, architecture, settings,
security, and troubleshooting.

## License

Waypoints is licensed under GPLv2 or later. See [LICENSE](LICENSE).

## Local Source Installation

1. Copy or symlink `plugin/plan-your-day/` into a WordPress installation at
   `wp-content/plugins/plan-your-day/`.
2. From the plugin directory, install the Composer autoloader:

   ```sh
   composer install
   ```

3. Activate **Waypoints** from the WordPress Plugins screen.
4. Open **Settings > Waypoints**.
5. Configure the required default location and Google API keys.

Release zips should include generated Composer autoload files so production
sites do not need to run Composer.

## Build Release Zip

From `plugin/plan-your-day/`, build an installable WordPress admin zip with:

```sh
./tools/build-release-zip.sh
```

The script creates `dist/plan-your-day-0.5.zip` at the repository root,
installs production-only Composer autoload files into a temporary staging copy,
and packages the final artifact with a top-level `plan-your-day/` directory
suitable for **Plugins > Add New > Upload Plugin**.

See [docs/RELEASES.md](docs/RELEASES.md) for the full manual GitHub release
workflow and the metadata that needs to stay aligned.

## WordPress.org Submission Readiness

The GitHub Actions workflow `WP Submission Readiness` is the go-to release
candidate scan before submitting to WordPress.org. It is reusable through
`workflow_call` and can also be run manually from the Actions tab.

The scan builds the release zip, runs the normal PHP checks, PHPStan, browser
smoke coverage, WordPress Plugin Check against the packaged artifact, and a
repo-local metadata check. The metadata check intentionally expects **Waypoints**
to use the permanent WordPress.org slug and text domain `waypoints`, so it will
fail until the deeper public rename work is complete.

## Configuration

Plugin settings are stored in the `plan_your_day_settings` option and are
managed through the WordPress Settings API. Current settings include:

- Default location label, address/search phrase, latitude, longitude, and Place
  ID.
- Allowed starting-point modes.
- Maximum waypoints and result count.
- Distance unit.
- Map preview and Google Maps handoff toggles.
- Editable category buttons with label, description, Google search query,
  enabled state, and sort order.
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
- Treat `docs/PLAN-YOUR-DAY-PLUGIN-TODO.md` and
  `docs/PLAN-YOUR-DAY-PLUGIN-ISSUES.md` as historical planning notes unless
  they are explicitly refreshed.

## Useful Checks

Run the PHP checks from the plugin directory:

```sh
cd plugin/plan-your-day
composer test
```

Run the browser smoke suite from the repository root:

```sh
npm ci
npx playwright install chromium
npm run browser-smoke
```

Run a quick PHP syntax-only sweep from the repository root:

```sh
find plugin/plan-your-day -name '*.php' -print -exec php -l {} \;
```

Search for legacy integration-specific strings in plugin code:

```sh
rg "localhost|example\\.test|Kona|pier" plugin/plan-your-day
```

The plugin PHPUnit suite covers current service, settings, and activation
behavior. The GitHub `Plugin Quality` workflow also runs WordPress Plugin Check
against an installable wp-env-backed plugin checkout.
