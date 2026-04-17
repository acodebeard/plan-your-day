# Plan Your Day Plugin TODO

## Overview

Convert the current standalone Plan Your Day implementation into a generic, maintainable WordPress plugin. The plugin must not contain inherent references to Kona or any other specific location. Site-specific destination behavior should come from admin settings, including a required default location.

The current standalone implementation in `index.php`, `plan.js`, `plan.css`, and `icons/` is the source for the first plugin version, but the plugin should use WordPress-native APIs for rendering, settings, assets, REST endpoints, escaping, sanitization, caching, updates, and security.

## Goals

- [ ] Package Plan Your Day as an installable WordPress plugin.
- [ ] Keep the plugin generic and location-agnostic.
- [ ] Allow admins to configure the default location.
- [ ] Allow admins to configure as much planner behavior and copy as practical.
- [ ] Preserve the current security improvements.
- [ ] Support maintainable versioned updates.
- [ ] Keep frontend behavior accessible and theme-safe.
- [ ] Add tests and QA checks before release.

## Assumptions / Constraints

- [ ] No hardcoded references to Kona, Destination Kona Coast, Kailua Pier, or any other site-specific location in plugin code, default copy, text domain, option names, namespaces, or filenames.
- [ ] The plugin can seed generic defaults, but destination-specific values must be admin settings or migration data.
- [ ] The default location setting is required before the public planner should be considered fully configured.
- [ ] The browser-visible Google Maps Embed API key and server-side Google API keys must remain separate.
- [ ] Public planner endpoints must remain POST-only and protected by a guest-safe token strategy.
- [ ] PHP sessions should not be required in the plugin.
- [ ] Existing standalone deployment should remain untouched until the plugin MVP works.
- [ ] WordPress-native escaping, sanitization, options, REST, transients/object cache, and asset APIs should replace standalone compatibility shims.

## Phases

### Phase 1: Product And Architecture Decisions

- [ ] Choose generic plugin name, slug, namespace, text domain, and option prefix.
- [ ] Decide whether the plugin is private/internal, GitHub-distributed, or WordPress.org-distributed.
- [ ] Define minimum supported WordPress version.
- [ ] Define minimum supported PHP version.
- [ ] Decide whether multisite support is required for v1.
- [ ] Choose initial frontend entry points: shortcode first, block wrapper second.
- [ ] Confirm REST API routes as the endpoint model.

### Phase 2: Plugin Skeleton

- [ ] Create standalone plugin scaffold.
- [ ] Add main plugin bootstrap file with WordPress plugin headers.
- [ ] Add `readme.txt` with plugin metadata and changelog placeholder.
- [ ] Add `uninstall.php` or uninstall handler.
- [ ] Add namespaced PHP directory structure.
- [ ] Add activation and deactivation hooks.
- [ ] Add version constant and stored schema/version option.
- [ ] Add build/release ignore rules.

### Phase 3: Extract Current Logic

- [ ] Move current Plan component rendering into plugin structure.
- [ ] Split current `index.php` responsibilities into renderer, request parsing, Google client, security, settings, and support classes.
- [ ] Replace standalone WordPress shim functions with real WordPress APIs.
- [ ] Move frontend assets into plugin `assets/` paths.
- [ ] Move SVG icons into plugin assets.
- [ ] Convert template markup into a reusable plugin template.
- [ ] Remove hardcoded location-specific copy and defaults.

### Phase 4: Settings System

- [ ] Register plugin settings/options with the Settings API.
- [ ] Add admin settings page.
- [ ] Add required default location setting.
- [ ] Add default location label setting.
- [ ] Add default location address/search phrase setting.
- [ ] Add optional default location latitude/longitude settings.
- [ ] Add optional default location Google Place ID setting.
- [ ] Add allowed start modes setting.
- [ ] Add max waypoints setting.
- [ ] Add result count/page size setting.
- [ ] Add distance unit setting.
- [ ] Add map preview enable/disable setting.
- [ ] Add Google Maps handoff enable/disable setting.
- [ ] Add browser-facing Maps Embed API key setting.
- [ ] Add server-side Places API key setting.
- [ ] Add server-side Geocoding API key setting or documented fallback behavior.
- [ ] Add cache TTL settings.
- [ ] Add rate-limit settings.
- [ ] Add trusted proxy CIDR settings under Advanced.
- [ ] Add configurable frontend copy settings.
- [ ] Add categories settings with add/edit/delete/reorder/enable controls.
- [ ] Add sanitization callbacks for every setting.
- [ ] Add admin warnings for missing required settings.

### Phase 5: Security And REST Endpoints

- [ ] Add guest-safe visitor token cookie strategy.
- [ ] Add HMAC-based endpoint token generation.
- [ ] Add endpoint token validation.
- [ ] Register REST route for browse requests.
- [ ] Register REST route for route preview requests.
- [ ] Keep REST routes POST-only.
- [ ] Sanitize all REST request fields.
- [ ] Validate max waypoint limits before Google API work.
- [ ] Port file-backed or object-cache-aware rate limiter.
- [ ] Integrate trusted proxy client IP resolution.
- [ ] Preserve `https://` allowlist for externally sourced URLs.
- [ ] Preserve safe JSON encoding for config payloads.
- [ ] Preserve cache-control behavior where needed.
- [ ] Ensure server-side API keys never reach the browser.

### Phase 6: Frontend Integration

- [ ] Register frontend CSS with `wp_register_style()`.
- [ ] Register frontend JS with `wp_register_script()`.
- [ ] Enqueue assets only when the planner is rendered.
- [ ] Pass runtime config safely to JavaScript.
- [ ] Update `plan.js` to call WordPress REST endpoints.
- [ ] Update `plan.js` to send POST bodies matching REST schema.
- [ ] Keep frontend markup scoped under a plugin wrapper class.
- [ ] Confirm multiple planners on one site do not conflict, or explicitly document one instance per page for v1.
- [ ] Add shortcode entry point.
- [ ] Add block wrapper entry point.

### Phase 7: Admin UI And Tools

- [ ] Build admin settings screen.
- [ ] Add setup/status panel.
- [ ] Add API key configuration section.
- [ ] Add default location section.
- [ ] Add planner behavior section.
- [ ] Add categories editor section.
- [ ] Add copy/text section.
- [ ] Add security/advanced section.
- [ ] Add clear cache tool.
- [ ] Add Google API connection test tool.
- [ ] Add settings export/import if needed.

### Phase 8: Versioning And Updates

- [ ] Add semantic versioning policy.
- [ ] Add option schema migration runner.
- [ ] Add migration path for settings changes.
- [ ] Add Git tag release process.
- [ ] Add plugin zip build process.
- [ ] Decide update channel: WordPress.org, GitHub Releases, private updater, or manual deployment.
- [ ] Add `Update URI` header if using custom/private updates.
- [ ] Maintain changelog entries for each release.

### Phase 9: Migration From Standalone

- [ ] Keep standalone deployment unchanged during plugin MVP.
- [ ] Add migration helper for current standalone configuration.
- [ ] Map existing Google key config to plugin settings.
- [ ] Map existing categories to plugin category settings.
- [ ] Map current site-specific location values into settings only.
- [ ] Add admin notice if old standalone config exists and plugin settings are empty.
- [ ] Document replacement of standalone route/page with shortcode or block.
- [ ] Document cleanup/deprecation of standalone files after successful migration.

### Phase 10: Testing And QA

- [ ] Add PHP lint check.
- [ ] Add WordPress Coding Standards check.
- [ ] Add Plugin Check workflow.
- [ ] Add unit tests for settings sanitization.
- [ ] Add unit tests for safe URL validation.
- [ ] Add unit tests for trusted proxy resolution.
- [ ] Add unit tests for rate limiter keys.
- [ ] Add unit tests for guest token validation.
- [ ] Add REST endpoint integration tests.
- [ ] Add browser smoke test for initial render.
- [ ] Add browser smoke test for browse/add/remove/reorder flow.
- [ ] Add accessibility review.
- [ ] Add keyboard-only QA pass.
- [ ] Add theme compatibility QA pass.
- [ ] Add no-API-key and bad-API-response QA cases.

### Phase 11: CI/CD

- [ ] Add GitHub Actions workflow for PHP lint.
- [ ] Add GitHub Actions workflow for PHPCS.
- [ ] Add GitHub Actions workflow for tests.
- [ ] Add GitHub Actions workflow for JS syntax/lint checks.
- [ ] Add GitHub Actions workflow for Plugin Check.
- [ ] Add release artifact build workflow.
- [ ] Exclude local config, dev caches, deprecated backups, and unnecessary test fixtures from production zip.

### Phase 12: Documentation

- [ ] Write admin/editor documentation.
- [ ] Write developer README.
- [ ] Write installation guide.
- [ ] Write settings reference.
- [ ] Write Google Cloud API key restriction guide.
- [ ] Write security model documentation.
- [ ] Write migration guide from standalone implementation.
- [ ] Write troubleshooting guide.
- [ ] Maintain changelog.

## Task Checklist

- [ ] Create standalone plugin scaffold.
- [ ] Choose generic namespace, prefix, and text domain.
- [ ] Move current Plan component logic into plugin structure.
- [ ] Register plugin settings/options.
- [ ] Add required default location setting.
- [ ] Add configurable category settings.
- [ ] Add configurable frontend copy settings.
- [ ] Separate frontend assets.
- [ ] Add shortcode entry point.
- [ ] Add block entry point.
- [ ] Add API key/settings handling.
- [ ] Add Google API client abstraction.
- [ ] Add REST endpoints.
- [ ] Add guest-safe token validation.
- [ ] Add caching/transient strategy.
- [ ] Add rate limiter strategy.
- [ ] Add trusted proxy strategy.
- [ ] Add admin settings UI.
- [ ] Add setup/status checks.
- [ ] Add migration helper.
- [ ] Add version/migration system.
- [ ] Add CI checks.
- [ ] Add accessibility review.
- [ ] Add QA/testing pass.
- [ ] Write admin/editor documentation.
- [ ] Prepare release artifact.

## Risks / Dependencies

- [ ] Guest token design must not rely on logged-in WordPress nonces for anonymous visitors.
- [ ] Google API key separation must be clear so server-side keys are not exposed.
- [ ] Default location must be required without hardcoding a site-specific fallback.
- [ ] Admin-configurable category queries can increase Google API cost if not validated and rate-limited.
- [ ] Rate limiting needs a reliable storage strategy across common hosting environments.
- [ ] Object cache availability varies by site.
- [ ] REST endpoints must remain compatible with page caches and security plugins.
- [ ] CSP/security headers may conflict with themes or other plugins if emitted globally.
- [ ] Block support can expand scope; shortcode should work first.
- [ ] Migration must not break the existing standalone page before plugin launch.

## Definition Of Done

- [ ] Plugin installs and activates cleanly on a supported WordPress site.
- [ ] Plugin has no hardcoded location-specific references.
- [ ] Admin can configure default location and planner behavior.
- [ ] Admin can configure API keys without editing files.
- [ ] Planner renders through shortcode.
- [ ] Planner renders through block or documented block wrapper.
- [ ] Browse and route flows work through REST POST endpoints.
- [ ] Anonymous visitor token protection works.
- [ ] Rate limiting works for anonymous visitors and changing cookies.
- [ ] Trusted proxy configuration works when enabled.
- [ ] Server-side API keys are never exposed to the browser.
- [ ] Frontend is keyboard accessible and screen-reader usable.
- [ ] Settings are sanitized and escaped correctly.
- [ ] Tests and QA checks pass.
- [ ] Version migration system is in place.
- [ ] Release artifact can be built reproducibly.
- [ ] Admin/editor documentation is complete.
