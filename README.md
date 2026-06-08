# Waypoints

Waypoints is a configurable WordPress plugin for building day-trip planners with
Google Maps and Places data. The plugin source lives in
`plugin/waypoints/`.

## Status

The latest published release is **v1.0**. The v1.0 milestone completes the MVP
release-readiness pass for a reusable WordPress.org submission candidate.

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

The v1.0 hardening pass included:

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

- `plugin/waypoints/` contains the WordPress plugin source.
- The root PHP bootstrap file contains the WordPress plugin headers.
- `plugin/waypoints/src/` contains namespaced plugin classes.
- `plugin/waypoints/src/Settings/` contains option defaults and
  sanitization.
- `plugin/waypoints/src/Admin/` contains the settings UI.
- `plugin/waypoints/src/Google/` contains Google API client and cache
  classes.
- `docs/` contains installation, usage, admin, release, architecture, settings,
  security, troubleshooting, and historical planning notes for the plugin.

## Naming Note

The public plugin name is **Waypoints**. The source directory, text domain,
REST namespace, block name, and preferred shortcode now use `waypoints`. Some
legacy compatibility surfaces still use identifiers from the original name:
`[plan_your_day]` remains as a shortcode alias, `plan_your_day_*` remains for
settings/options/hooks, `Acodebeard\PlanYourDay` remains the PHP namespace, and
some asset/CSS identifiers remain unchanged to avoid unnecessary migration
risk.

## Credits

Special thanks to [Hagan Franks](https://github.com/hagan) and
[Christopher Reaume](https://github.com/datapoke) for development help.

Thanks also to [Destination Kona Coast](https://destinationkonacoast.com) for
the idea that started the project.

## Documentation

Start with [docs/README.md](docs/README.md). Current docs cover installation,
frontend usage, admin workflows, release steps, architecture, settings,
security, and troubleshooting.

## License

Waypoints is licensed under GPLv2 or later. See [LICENSE](LICENSE).

## Local Source Installation

1. Copy or symlink `plugin/waypoints/` into a WordPress installation at
   `wp-content/plugins/waypoints/`.
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

From `plugin/waypoints/`, build an installable WordPress admin zip with:

```sh
./tools/build-release-zip.sh
```

The script creates `dist/waypoints-1.0.zip` at the repository root,
installs production-only Composer autoload files into a temporary staging copy,
and packages the final artifact with a top-level `waypoints/` directory
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
to use the permanent WordPress.org slug and text domain `waypoints`.

## Configuration

Plugin settings are stored in a single WordPress option and are managed through
the WordPress Settings API. Current settings include:

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
- Treat `docs/WAYPOINTS-PLUGIN-TODO.md` and
  `docs/WAYPOINTS-PLUGIN-ISSUES.md` as historical planning notes unless
  they are explicitly refreshed.

## Useful Checks

Run the PHP checks from the plugin directory:

```sh
cd plugin/waypoints
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
find plugin/waypoints -name '*.php' -print -exec php -l {} \;
```

Search for legacy integration-specific strings in plugin code:

```sh
rg "localhost|example\\.test|Kona|pier" plugin/waypoints
```

The plugin PHPUnit suite covers current service, settings, and activation
behavior. The GitHub `Plugin Quality` workflow also runs WordPress Plugin Check
against an installable wp-env-backed plugin checkout.
