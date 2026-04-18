# Issue 20: Generic WordPress Plugin Scaffold Plan

GitHub issue: https://github.com/acodebeard/plan-your-day/issues/20

## Summary

Create the initial WordPress plugin scaffold for Plan Your Day. The scaffold should define the plugin directory structure, bootstrap file, plugin headers, namespace, constants, activation and deactivation hooks, version options, and release metadata placeholders.

This issue is limited to scaffold work. The existing standalone planner files should remain untouched until the plugin MVP is ready.

## Acceptance Criteria

- [ ] Plugin has a valid main plugin file with WordPress headers.
- [ ] Plugin uses generic namespace, text domain, option prefix, and file names.
- [ ] Activation and deactivation hooks are registered.
- [ ] Version constant and stored schema/version option are added.
- [ ] `readme.txt` and release metadata placeholders exist.

## Scaffold Location

Create the plugin under:

```text
plugin/plan-your-day/
```

This keeps the new plugin scaffold separate from the current standalone runtime in the repository root:

- `index.php`
- `plan.js`
- `plan.css`
- `icons/`

## Generic Identifiers

Use these identifiers consistently:

- Plugin name: `Plan Your Day`
- Plugin slug: `plan-your-day`
- Text domain: `plan-your-day`
- Main plugin file: `plan-your-day.php`
- PHP namespace: `PlanYourDay`
- Option prefix: `plan_your_day_`
- Constant prefix: `PLAN_YOUR_DAY_`
- Initial plugin version: `0.1.0`
- Initial schema version: `1`

Do not introduce destination-specific naming or references, including Kona, Destination Kona Coast, Kailua Pier, DKC, or similar site-specific terms.

## Files To Add

```text
plugin/plan-your-day/
├── .distignore
├── plan-your-day.php
├── readme.txt
├── release.json
└── src/
    ├── Activator.php
    ├── Deactivator.php
    └── Plugin.php
```

## Implementation Checklist

1. Create the plugin directory structure.
2. Add `plan-your-day.php` with valid WordPress plugin headers.
3. Add `defined( 'ABSPATH' ) || exit;` to prevent direct access.
4. Define initial plugin constants:
   - `PLAN_YOUR_DAY_VERSION`
   - `PLAN_YOUR_DAY_SCHEMA_VERSION`
   - `PLAN_YOUR_DAY_PLUGIN_FILE`
   - `PLAN_YOUR_DAY_PLUGIN_DIR`
   - `PLAN_YOUR_DAY_PLUGIN_URL`
   - `PLAN_YOUR_DAY_TEXT_DOMAIN`
5. Load the initial namespaced classes from `src/`.
6. Add `PlanYourDay\Plugin` for basic bootstrap wiring.
7. Add `PlanYourDay\Activator` for install/version option setup.
8. Add `PlanYourDay\Deactivator` as a non-destructive deactivation placeholder.
9. Register activation and deactivation hooks in the main plugin file.
10. On activation, store:
    - `plan_your_day_version`
    - `plan_your_day_schema_version`
11. Keep deactivation non-destructive.
12. Add `readme.txt` with WordPress-style plugin metadata and placeholders.
13. Add `.distignore` for future release artifact packaging.
14. Add `release.json` or equivalent release metadata placeholder.
15. Leave current standalone files unchanged.

## Main Plugin File Requirements

The main plugin file should include WordPress plugin headers similar to:

```php
/**
 * Plugin Name: Plan Your Day
 * Description: A configurable day planning plugin for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: acodebeard
 * Text Domain: plan-your-day
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 */
```

The exact WordPress and PHP version targets can be adjusted later if issue 2 defines different compatibility requirements. Avoid adding an `Update URI` header until the distribution/update channel is decided.

## Release Metadata Placeholders

Add placeholder release metadata only. The scaffold should not lock in a final distribution strategy.

Recommended `release.json` fields:

- `name`
- `slug`
- `version`
- `schemaVersion`
- `artifact`
- `distribution`
- `notes`

Set `distribution` to a placeholder value such as `undecided`.

## Verification

Run PHP syntax checks:

```bash
php -l plugin/plan-your-day/plan-your-day.php
php -l plugin/plan-your-day/src/Plugin.php
php -l plugin/plan-your-day/src/Activator.php
php -l plugin/plan-your-day/src/Deactivator.php
```

Confirm no destination-specific references were introduced:

```bash
rg "Kona|Destination Kona Coast|Kailua Pier|DKC|dkc" plugin/plan-your-day
```

Confirm the expected files exist:

```bash
find plugin/plan-your-day -maxdepth 3 -type f | sort
```

## Definition Of Done

- [ ] The plugin scaffold exists under `plugin/plan-your-day/`.
- [ ] The main plugin file has valid headers and generic Plan Your Day metadata.
- [ ] Constants are defined with the `PLAN_YOUR_DAY_` prefix.
- [ ] Plugin PHP classes use the `PlanYourDay` namespace.
- [ ] Activation stores version and schema version options.
- [ ] Deactivation is registered and non-destructive.
- [ ] `readme.txt` exists with metadata and changelog placeholders.
- [ ] Release metadata placeholders exist.
- [ ] The standalone app files remain unchanged.
- [ ] PHP syntax checks pass.
- [ ] Search confirms no new destination-specific strings exist in the plugin scaffold.
