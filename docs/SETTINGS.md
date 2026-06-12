# Settings Reference

Waypoints: Trip Planner stores plugin configuration in:

```text
plan_your_day_settings
```

The settings group is also:

```text
plan_your_day_settings
```

Only administrators with `manage_options` can manage settings.

## Required Default Location

The public planner should not be considered configured until these required
fields are set:

- `default_location_label`
- `default_location_address`

Optional default location fields:

- `default_location_latitude`
- `default_location_longitude`
- `default_location_place_id`

Destination-specific values belong in settings or migration data, not plugin
defaults.

## Planner Behavior

- `allowed_start_modes`: allowed starting controls. Supported values are
  `default`, `custom`, and `current`.
- `max_waypoints`: maximum selected places a public request may resolve.
- `result_count`: maximum Google text-search results requested per browse
  action. Fresh/default installs should use `8`, and the same per-page cap is
  used when the frontend requests additional Google Places pages through the
  More Results control.
- `distance_unit`: `miles` or `kilometers`.
- `map_preview_enabled`: controls whether the public planner may render on-page
  Google Maps Embed previews.
- `maps_handoff_enabled`: controls whether the public planner may show outbound
  Google Maps links.

## Categories

Editable categories are stored as:

```text
plan_your_day_settings[categories]
```

Each saved category row includes:

- `slug`
- `label`
- `description`
- `text_query`
- `enabled`
- `sort_order`

Built-in starter rows for fresh installs live in:

```text
plugin/waypoints/src/Planner/CategoryCatalog.php
```

Important behavior:

- `Settings::get_categories()` returns the saved category rows as-is, including
  an intentionally empty list.
- Fresh installs seed the default rows; upgrades preserve the saved list.
- `CategoryCatalog` filters those rows down to enabled frontend categories and
  keys them by slug for renderer and planner-state use.
- `Settings::sanitize_categories()` trims text fields, strips HTML/JS, creates
  safe unique slugs, drops malformed rows, and sorts by `sort_order`.

To change the starter categories for a fresh install:

1. Update `CategoryCatalog::default_rows()`.
2. Keep each row generic to the plugin.
3. Preserve `label`, `description`, `text_query`, `enabled`, and `sort_order`.
4. Update tests that assert the default/frontend category list.

## Frontend Interface Copy

Frontend interface copy is stored inside the main plugin option as:

```text
plan_your_day_settings[interface_copy]
```

Default copy definitions live in:

```text
plugin/waypoints/src/Frontend/InterfaceCopy.php
```

Important behavior:

- Required labels, buttons, accessible names, and system messages fall back to
  their default copy when saved blank.
- Optional helper text and descriptive text can be saved blank to suppress the
  visible text output.
- The search/results group includes the More Results button label, and the
  status-messages group includes the loading, loaded-count, no-more-results,
  and load-more error copy used by Google Places pagination.
- Dynamic copy uses named tokens such as `{count}`, `{search}`, `{place}`,
  `{start}`, and `{settings}` instead of raw `sprintf()` placeholders.
- Frontend runtime code receives the same saved/default values through the
  renderer config JSON, so initial HTML and JavaScript updates stay aligned.

To add a new editable frontend string in the future:

1. Add the string definition to `InterfaceCopy::definitions()` with its default,
   group, field type, and required/optional rule.
2. Read it through `Settings::get_frontend_copy_value()` or
   `Settings::format_frontend_copy()` in the renderer or planner service that
   outputs the text.
3. If JavaScript needs the string, add it to the renderer config in
   `PlannerRenderer::build_config()`.
4. Add or update tests for fallback, blank handling, and any dynamic token
   replacement.

## Google API Keys

- `google_maps_embed_api_key`: browser-facing Maps Embed API key. This key can
  appear in iframe URLs.
- `google_places_api_key`: server-side Places API key for text search and place
  details.
- `google_geocoding_api_key`: optional server-side Geocoding API key. If empty,
  the plugin uses the Places API key for geocoding.

Server-side keys must not be sent to frontend runtime config.

## Google API Behavior

- `google_api_timeout`: request timeout, clamped from 1 to 30 seconds.
- `google_text_search_cache_ttl`: successful text search cache TTL in seconds.
- `google_place_details_cache_ttl`: successful place details cache TTL in
  seconds.
- `google_geocoding_cache_ttl`: successful geocoding cache TTL in seconds.

TTL values can be set to `0` to disable that cache path.

## Rate Limit And Trusted Proxies

- `rate_limit_per_minute`: per-minute budget used by the active public planner
  REST rate limiter.
- `trusted_proxy_cidrs`: optional IP/CIDR list, one per line, used for
  trusted-proxy-aware client IP resolution when the site sits behind known
  reverse proxies.

Invalid trusted proxy entries are discarded during sanitization.

## Sanitization

Settings are sanitized in `Acodebeard\PlanYourDay\Settings\Settings`.

Important behavior:

- Text fields are trimmed and sanitized with WordPress text sanitizers.
- Coordinates must be numeric and inside valid latitude/longitude ranges.
- Google API keys and Place IDs keep only expected key characters.
- Boolean values are normalized from checkbox-style input.
- Numeric limits are clamped to conservative ranges.
- Unknown start modes and distance units fall back to safe defaults.
