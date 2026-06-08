# AGENTS.md

## Project

This repository contains the standalone **Waypoints** WordPress plugin. The
plugin source lives in `plugin/waypoints/`.

Waypoints is a reusable plugin. It must not be branded to, named after, or architecturally tied to any specific client/site.

The latest published release is v0.5. The project is preparing for v1.0 with
focused public-release hardening and cleanup.

## Hard rules

- Do not reference DKC, Destination Kona Coast, or any other site-specific branding in code, comments, docs, settings, namespaces, text domains, admin labels, or frontend copy.
- Preserve the plugin as a generic planning, search, directions, and waypoint tool.
- Keep patches focused and reviewable.
- Prefer small, safe changes over broad rewrites.
- Inspect the existing code before editing.
- Do not introduce a new build system unless absolutely necessary.
- Do not remove accessibility features.
- Do not introduce Font Awesome, icon fonts, bundled icon images, or SVG icon
  assets for UI chrome. Use CSS-only details for carets, grips, toggles, and
  similar controls unless the asset is real content.
- Keep the bundled Noto Sans font files and their license together. Do not add
  or replace fonts without confirming license coverage.
- Escape and sanitize all WordPress output/input appropriately.
- Do not allow raw HTML/JS injection through admin-editable copy/category fields unless a deliberate sanitized rich-text pattern already exists.

## Current product surface

The plugin currently supports:
- frontend place/category/custom searches,
- "Get more results" pagination for category and custom searches,
- waypoint selection,
- waypoint reorder/remove and clear-trip behavior,
- route/directions behavior,
- map preview and Google Maps handoff behavior,
- distance labels and distance-unit settings,
- frontend color mode switching with an admin default,
- Noto Sans frontend/admin typography,
- admin-editable interface copy where the setting is still useful,
- admin-editable/custom categories with draggable ordering.

## Current v1.0 direction

Prioritize release readiness over new feature breadth. Current hardening lanes
include:

- CSS-only UI details and removal of bundled icon assets.
- REST token bootstrap cache safety.
- Rate-limit state moving to expiring transients.
- Google geocode failure handling.
- Stable, quiet WordPress Plugin Check workflow coverage.

PHPStan is not part of the current v1.0 gate. A one-off scan without WordPress
stubs reports missing WordPress symbols, so do not add PHPStan to CI unless the
project also adds reproducible WordPress-aware tooling.

## UI/accessibility expectations

- Use real buttons for interactive controls.
- Preserve keyboard access.
- Preserve accessible names for controls.
- Preserve color mode behavior: admin default first, system preference only
  when configured, and explicit frontend user choice as the strongest signal.
- Loading/error/status messages should be understandable to screen reader users.
- Do not steal focus unexpectedly.
- Use polite live/status announcements where helpful, but do not announce every result one-by-one.
- Keep the frontend compact and avoid adding unnecessary instructional text.

## WordPress expectations

- Follow existing plugin architecture.
- Use existing settings/admin patterns where possible.
- Centralize defaults for editable copy/settings.
- Use nonces for admin saves.
- Sanitize saved settings.
- Escape output based on context.
- Avoid PHP notices/warnings with partial or missing settings.
- Keep frontend strings generic and admin-editable where practical.

## Testing expectations

Before finishing a code task, choose checks that match the change. Common
checks include:

- `cd plugin/waypoints && composer test`
- `npm ci && npx playwright install chromium && npm run browser-smoke`
- `find plugin/waypoints -name '*.php' -print -exec php -l {} \;`
- GitHub `Plugin Quality` workflow for PHP syntax, PHPCS, PHPUnit, browser
  smoke, and WordPress Plugin Check.

Also check, as relevant:

- frontend still renders with default settings,
- saved settings still load,
- category/custom searches still work,
- selected waypoints are preserved when unrelated UI changes occur,
- no obvious JS console errors,
- no obvious PHP warnings/notices,
- no client/site-specific references were introduced.

## Additional Rules
- When referring to an Issue on a GitHub repo, capitalize the I.
