# AGENTS.md

## Project

This repository contains the standalone **Plan Your Day** WordPress plugin.

Plan Your Day is a reusable plugin. It must not be branded to, named after, or architecturally tied to any specific client/site.

## Hard rules

- Do not reference DKC, Destination Kona Coast, or any other site-specific branding in code, comments, docs, settings, namespaces, text domains, admin labels, or frontend copy.
- Preserve the plugin as a generic planning, search, directions, and waypoint tool.
- Keep patches focused and reviewable.
- Prefer small, safe changes over broad rewrites.
- Inspect the existing code before editing.
- Do not introduce a new build system unless absolutely necessary.
- Do not remove accessibility features.
- Escape and sanitize all WordPress output/input appropriately.
- Do not allow raw HTML/JS injection through admin-editable copy/category fields unless a deliberate sanitized rich-text pattern already exists.

## Current feature direction

The plugin currently supports:
- frontend place/category/custom searches,
- waypoint selection,
- route/directions behavior,
- admin-editable interface copy,
- admin-editable/custom categories.

Current planned features include:
- “Get more results” for category and custom searches,
- a mobile fixed waypoint-count tab,
- distance preference controls,
- transportation preference controls for walking, driving, or both,
- a later compacting pass after copy and category settings are cleaned up.

## UI/accessibility expectations

- Use real buttons for interactive controls.
- Preserve keyboard access.
- Preserve accessible names for controls.
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

Before finishing a task, check:
- frontend still renders with default settings,
- saved settings still load,
- category/custom searches still work,
- selected waypoints are preserved when unrelated UI changes occur,
- no obvious JS console errors,
- no obvious PHP warnings/notices,
- no client/site-specific references were introduced.