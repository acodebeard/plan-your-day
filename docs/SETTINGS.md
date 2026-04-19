# Settings Reference

Plan Your Day stores plugin configuration in:

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
  action.
- `distance_unit`: `miles` or `kilometers`.
- `map_preview_enabled`: allows future on-page Maps Embed previews.
- `maps_handoff_enabled`: allows future outbound Google Maps links.

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

- `rate_limit_per_minute`: stored configuration for the future public endpoint
  rate limiter.
- `trusted_proxy_cidrs`: optional IP/CIDR list, one per line, for future
  trusted-proxy-aware client IP resolution.

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
