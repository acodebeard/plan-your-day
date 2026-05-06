# Architecture

Plan Your Day is a generic WordPress plugin for building configurable day-trip
planners with Google Maps and Places data.

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
- `Planner`: owns planner helper services shared by frontend rendering and REST
  endpoints.
- `Security`: owns request/security helpers, visitor-token protection,
  trusted-proxy-aware IP resolution, and rate limiting.
- `Rest`: owns the public browse and route endpoints.

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
behavior settings, editable planner categories, Google API keys, cache TTLs,
rate-limit configuration, trusted proxy CIDRs, frontend interface copy
controls, and a Google API cache clear tool.

Editable frontend copy defaults and field metadata live in:

```text
plugin/plan-your-day/src/Frontend/InterfaceCopy.php
```

`Settings` stores those values inside the main option under `interface_copy`,
and renderer/state classes read them through
`Settings::get_frontend_copy_value()` and `Settings::format_frontend_copy()`.
That keeps first-paint HTML, REST responses, and JavaScript runtime strings on
one source of truth.

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

- `CategoryCatalog`: owns the built-in starter category list for fresh installs
  and empty saved-category fallbacks, then filters the saved category rows down
  to the enabled frontend catalog.
- `PlaceParser`: shapes Google place responses and sanitizes Place IDs / HTTPS
  map URLs.
- `WaypointList`: normalizes, deduplicates, caps, and reorders waypoint IDs.
- `DistanceFormatter`: calculates straight-line distance hints and formats
  labels in miles or kilometers.
- `InterfaceCopy`: centralizes editable frontend copy defaults, field metadata,
  blank/fallback rules, and named-token formatting.
- `MapUrlBuilder`: builds Google Maps search, directions, and embed URLs.
- `StartContextResolver`: resolves default/current/custom start behavior using
  plugin settings instead of hardcoded destination defaults.
- `RequestStateParser`: normalizes request-style category, start, and waypoint
  state, including the browse-pagination fields passed through the shared
  `/browse` endpoint.
- `PlannerStateBuilder`: builds the plugin-native planner state shape used by
  shared renderers and REST endpoints.
- `PlannerPayloadBuilder`: shapes browse and route payloads from planner state.

These services are exposed from `Plugin` so the shortcode renderer and REST
routes share one implementation.

## Category Defaults

Editable categories are stored inside `plan_your_day_settings[categories]`.
Each row contains:

- `slug`
- `label`
- `description`
- `text_query`
- `enabled`
- `sort_order`

Built-in starter rows for fresh installs live in:

```text
plugin/plan-your-day/src/Planner/CategoryCatalog.php
```

Use `CategoryCatalog::default_rows()` when changing the starter list for new or
empty installs. `Settings::get_categories()` resolves the saved rows first and
falls back to that starter list only when the saved category list is empty and
the fallback toggle remains enabled.

## Security Helpers

`RequestOriginValidator` contains the same-site request heuristic used by the
plugin runtime. It checks Fetch Metadata headers when available and falls back
to `Origin` / `Referer` host matching.

`VisitorTokenManager` issues the guest-safe planner cookie and derives the HMAC
endpoint token embedded in frontend runtime config.

`ClientIpResolver` resolves the client IP, consulting `X-Forwarded-For` only
when the direct peer is in the configured trusted-proxy CIDR list.

`RateLimiter` applies a file-backed fixed-window request budget keyed by route
scope, client IP, and minute bucket.

`PlannerRoutes` registers the public WordPress REST browse and route endpoints.
Those callbacks reuse `RequestStateParser`, `PlannerStateBuilder`, and
`PlannerPayloadBuilder` before returning structured route/browse payloads.

The browse endpoint remains the single search-results entrypoint for both
initial category/custom searches and the frontend More Results flow. Pagination
state stays request- and client-state based: the frontend sends `page_token`,
`append_results`, and `loaded_result_ids`, then keeps the accumulated result
list in `plan.js` while the browse payload returns `nextPageToken`,
`hasMoreResults`, `searchContextKey`, and `searchResultsError`. That keeps
selected waypoints and route state aligned without introducing stored sessions
or server-side persistence for pagination.
