# Frontend Usage

Waypoints: Trip Planner can be rendered through either the block editor or the
shortcode. Both entry points use the same server-rendered planner and the same
frontend CSS and JavaScript assets.

## Before You Place The Planner

Make sure the plugin has been configured under:

```text
Settings > Waypoints: Trip Planner
```

At minimum, set:

- the default location label
- the default location address or search phrase
- the Google API keys needed for your site

If those required settings are missing, the frontend renders a setup notice
instead of the full planner.

## Block Editor

The plugin registers a dynamic block named:

```text
Waypoints: Trip Planner
```

Editor behavior:

- Insert the block from the block inserter.
- The editor shows a placeholder instead of trying to run the full planner UI
  inside Gutenberg.
- The actual planner is rendered on the frontend by the same shared renderer
  used by the shortcode.

Block setting:

- `Action URL`: optional page URL used for form submissions and stateful links.
  Leave it blank to submit back to the current page.

Use the block when the page is already built in Gutenberg and you want editors
to place the planner without writing shortcode manually.

## Shortcode

The preferred shortcode tag is:

```text
[waypoints]
```

Supported attributes:

- `action_url`: optional page URL used for form submissions and stateful links.
  Leave it blank to submit back to the current page.

Examples:

```text
[waypoints]
```

```text
[waypoints action_url="https://example.test/waypoints/"]
```

The legacy `[plan_your_day]` shortcode remains available for content created
before the public plugin name changed.

Use the shortcode in classic content, a Shortcode block, widget areas that
allow shortcodes, or any other editor flow where you want explicit markup
control.

## Placement Guidance

- Place the planner on a dedicated page or a section with enough vertical space
  for results and trip management.
- Current QA focuses on the common one-planner-per-page case. If you need
  multiple planners on one page, test that layout carefully in your theme.
- Frontend assets are registered globally but only enqueue when the shortcode
  or block actually renders.

## What Visitors Can Do

With a configured planner, visitors can:

- choose a starting mode such as default, custom, or current-location handoff
- browse built-in or saved categories
- run custom place searches
- add, remove, clear, and reorder waypoints
- request more results for category or custom searches
- view the route preview and open Google Maps handoff links when enabled
