# Admin Workflows

Plan Your Day settings live under:

```text
Settings > Plan Your Day
```

Only administrators with `manage_options` can change plugin settings or run the
admin-only tools.

## Recommended Setup Order

1. Fill in the required default location fields.
2. Add the Google API keys your site needs.
3. Save planner behavior settings.
4. Review categories and interface copy.
5. Save changes.
6. Run the Google API test.

## Settings Sections

### Default Location

Use this section to define the stable starting area for the planner.

Required:

- default location label
- default location address or search phrase

Optional:

- latitude
- longitude
- Google Place ID

The saved location is used for frontend defaults, search biasing, and other
planner state derived from the configured start area.

### Planner Behavior

This section controls the main visitor-facing planner behavior:

- allowed start modes
- max waypoints
- result count
- distance unit
- map preview toggle
- Google Maps handoff toggle

### Interface Copy

This section manages the editable frontend labels, helper text, buttons,
messages, and accessible text used by the planner.

Important behavior:

- required labels fall back to plugin defaults when saved blank
- optional helper text can be blanked intentionally
- the same saved/default copy is used in the initial HTML and the frontend
  runtime config

### Categories

This section manages the saved category buttons shown to visitors.

Each category row includes:

- label
- description
- Google search query
- enabled state
- sort order

The starter category fallback setting controls whether fresh or empty installs
show the built-in generic starter rows instead of no category buttons.

## Google API And Cache

### Google API

Use this section to save:

- Maps Embed API key
- Places API key
- optional Geocoding API key
- API timeout

Server-side keys stay on the server and are not exposed in frontend runtime
config.

### Google API Cache

Use this section to tune the cache TTL values for:

- text search responses
- place details responses
- geocoding responses

Set a TTL to `0` to disable that cache path.

## Security And Network Settings

### Rate Limiting

Use `Requests per minute` to control the public REST rate limiter budget used by
the planner browse and route endpoints.

### Advanced

Use `Trusted proxy CIDRs` when the site sits behind a known reverse proxy or
load balancer and forwarded client IP handling needs to trust specific proxy
ranges.

## Admin Tools

### Cache Tools

The settings page includes admin-only cache controls for Google API responses:

- clear the full Google API cache
- clear one cache scope
- clear one Google Place ID

Use these after changing cache TTL settings, troubleshooting unexpected results,
or forcing a fresh provider read.

### Google API Test

The `Run Google API test` button performs a lightweight admin-only probe using
the configured default location, categories, and server-side keys.

After the test runs, the settings page shows the latest probe summary and
individual results so you can confirm whether the current configuration is
usable before testing the public planner.
