# Frontend QA

Plan Your Day keeps a lightweight browser smoke suite for the public planner
frontend. The suite focuses on the shipped shortcode and block entry points,
the same-site REST flow, and a small set of accessibility-sensitive
interactions.

## Automated Smoke Coverage

- Plain-page asset scoping: the planner CSS/JS do not load on pages without a
  planner render.
- Shortcode render: the frontend loads, the category browse flow works, and the
  public JS boots without browser-console errors.
- Block render: the dynamic block renders through the same frontend JS path and
  can browse categories successfully.
- Core trip flow: category browse, load-more, add waypoint, reorder, remove,
  and clear-trip actions complete through the real REST endpoints.
- Focus-sensitive behavior: route mutations restore focus to a useful control
  after add, reorder, remove, and clear-trip actions.
- Narrow-viewport interaction: the start-options toggle can be collapsed and
  reopened in a mobile-sized viewport.

The browser harness uses a tiny PHP app and deterministic fake Google data. It
does not replace WordPress integration or external Google API validation; it is
meant to catch frontend regressions in the plugin's own public flow.

## Local Run

From the repo root:

```bash
cd plugin/plan-your-day
composer install
cd ../..
npm ci
npx playwright install chromium
npm run browser-smoke
```

## CI Expectation

The GitHub `Plugin Quality` workflow runs the browser smoke suite in the
`browser-smoke` job. Public frontend changes should keep that job green and
should update the smoke harness when entry points, selectors, or planner
interaction flow change.
