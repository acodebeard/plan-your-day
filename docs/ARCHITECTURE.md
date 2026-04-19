# Architecture

Plan Your Day is migrating from a standalone PHP planner into a generic
WordPress plugin. The standalone root files remain as migration reference until
the plugin reaches feature parity.

## Plugin Entry Point

The main plugin file is:

```text
plugin/plan-your-day/plan-your-day.php
```

It defines plugin constants, requires `vendor/autoload.php`, registers
activation/deactivation hooks, and boots `Acodebeard\PlanYourDay\Plugin`.

## Current Layers

Current plugin source lives under:

```text
plugin/plan-your-day/src/
```

Implemented layers:

- `Plugin`: wires WordPress hooks and exposes shared collaborators.
- `Activator` / `Deactivator`: manage activation/deactivation boundaries.
- `Settings`: owns defaults, sanitization, option registration, and read access.
- `Admin`: owns the settings screen, setup notices, and cache tools.
- `Google`: owns provider HTTP calls, field masks, result objects, and caching.
- `Planner`: owns extracted planner helpers from the standalone runtime.
- `Security`: owns request/security helpers extracted from standalone behavior.

## Settings And Admin

Settings are registered under:

```text
plan_your_day_settings
```

The admin page is registered under:

```text
Settings > Plan Your Day
```

The admin screen currently exposes required default location fields, planner
behavior settings, Google API keys, cache TTLs, rate-limit configuration, trusted
proxy CIDRs, and a Google API cache clear tool.

## Google API Layer

The Google API layer uses:

- `GoogleApiClientInterface`
- `GoogleApiClient`
- `CachedGoogleApiClient`
- `GoogleApiCache`
- `GoogleApiResult`
- `WordPressGoogleHttpTransport`

The client currently supports:

- Places API (New) text search.
- Places API (New) place details.
- Geocoding API lookups, falling back to the Places key when a dedicated
  Geocoding key is empty.
- Explicit field masks.
- Configurable request timeout.
- Safe user-facing errors.
- Transient-backed response caching.

Server-side Places and Geocoding keys must remain server-side. The browser-facing
Maps Embed key is separate.

## Planner Services

Planner helpers extracted so far:

- `CategoryCatalog`: provides generic seed categories until configurable
  categories land.
- `PlaceParser`: shapes Google place responses and sanitizes Place IDs / HTTPS
  map URLs.
- `WaypointList`: normalizes, deduplicates, caps, and reorders waypoint IDs.
- `DistanceFormatter`: calculates straight-line distance hints and formats
  labels in miles or kilometers.
- `MapUrlBuilder`: builds Google Maps search, directions, and embed URLs.
- `StartContextResolver`: resolves default/current/custom start behavior using
  plugin settings instead of hardcoded destination defaults.
- `RequestStateParser`: normalizes request-style category, start, and waypoint
  state.
- `PlannerStateBuilder`: builds the plugin-native planner state shape used by
  future renderers and REST endpoints.
- `PlannerPayloadBuilder`: shapes browse and route payloads from planner state.

These services are exposed from `Plugin` so future shortcode and REST work can
share one implementation.

## Security Helpers

`RequestOriginValidator` contains the same-site request heuristic extracted from
the standalone runtime. It checks Fetch Metadata headers when available and
falls back to `Origin` / `Referer` host matching.

Anonymous visitor token validation and rate limiter enforcement for WordPress
REST endpoints are still planned.

## Still In The Standalone Runtime

The root-level standalone implementation still contains:

- Full planner rendering.
- Focused JSON endpoint dispatch.
- Standalone WordPress compatibility shims.
- Session-backed nonce/cache behavior.
- Standalone request rate limiting.

Those pieces should move into plugin-native renderer, REST, security, and asset
classes in later issue slices.
