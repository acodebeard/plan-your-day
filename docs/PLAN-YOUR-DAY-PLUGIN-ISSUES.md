# Plan Your Day Plugin GitHub Issue Drafts

> Historical planning snapshot: these issue drafts were prepared before the
> implementation backlog moved into GitHub. They are preserved for context, not
> maintained as the current source of truth for feature status.

## Issue 1

Title: Create generic WordPress plugin scaffold

Summary: Create the initial plugin directory structure, main bootstrap file, plugin headers, namespace, constants, activation/deactivation hooks, and release metadata. The scaffold must use generic Plan Your Day naming and avoid destination-specific references.

Acceptance Criteria:
- [x] Plugin has a valid main plugin file with WordPress headers.
- [x] Plugin uses generic namespace, text domain, option prefix, and file names.
- [x] Activation and deactivation hooks are registered.
- [x] Version constant and stored schema/version option are added.
- [x] `readme.txt` and release metadata placeholders exist.

Dependencies: None

Labels: plugin, scaffold, phase-1

## Issue 2

Title: Define plugin architecture and compatibility targets

Summary: Document the plugin architecture, supported WordPress/PHP versions, distribution approach, frontend entry points, and endpoint model before moving code.

Acceptance Criteria:
- [ ] Minimum WordPress version is documented.
- [ ] Minimum PHP version is documented.
- [ ] Multisite support decision is documented.
- [ ] Distribution/update channel decision is documented.
- [ ] Shortcode-first and block-wrapper approach is confirmed.
- [ ] REST API endpoint model is confirmed.

Dependencies: None

Labels: planning, architecture, phase-1

## Issue 3

Title: Extract planner logic into plugin services

Summary: Move the current planner implementation into maintainable plugin classes/modules for rendering, request parsing, Google API calls, security, settings, and support utilities.

Acceptance Criteria:
- [ ] Current render logic is moved into maintainable plugin renderer/services.
- [ ] Google API logic is isolated.
- [ ] Place parsing is isolated.
- [ ] Route/map URL generation is isolated.
- [ ] Security helpers are isolated.
- [ ] Temporary compatibility shims are removed from plugin code.
- [ ] WordPress-native escaping, sanitization, JSON, and URL APIs are used.

Dependencies: Issue 1

Labels: refactor, plugin, phase-3

## Issue 4

Title: Remove hardcoded location-specific defaults

Summary: Ensure plugin code, default copy, namespaces, settings keys, and seed data do not contain hardcoded references to Kona, Kailua Pier, Destination Kona Coast, or other specific locations.

Acceptance Criteria:
- [ ] No location-specific strings exist in plugin code defaults.
- [ ] Default location comes only from settings or migration data.
- [ ] Frontend copy is generic by default.
- [ ] Option names, namespaces, text domain, and files are generic.
- [ ] Missing default location renders a setup warning instead of using a hidden fallback.

Dependencies: Issue 3

Labels: settings, generic-plugin, phase-3

## Issue 5

Title: Register plugin settings and options

Summary: Build the Settings API registration layer for all configurable planner behavior, with sanitization callbacks and option defaults.

Acceptance Criteria:
- [x] Settings group is registered.
- [x] Required default location settings are registered.
- [x] Google API key settings are registered.
- [x] Planner behavior settings are registered.
- [x] Cache and rate-limit settings are registered.
- [x] Trusted proxy settings are registered under Advanced.
- [x] Every setting has a sanitization callback.
- [x] Missing required settings trigger admin warnings.

Dependencies: Issue 1

Labels: settings, admin, phase-4

## Issue 6

Title: Add configurable categories

Summary: Replace hardcoded categories with admin-configurable category data, including generic seed categories on first install.

Acceptance Criteria:
- [ ] Category label, description, search query, enabled flag, and sort order are stored in settings.
- [ ] Generic seed categories are created only when no category settings exist.
- [ ] Admins can add categories.
- [ ] Admins can edit categories.
- [ ] Admins can disable/delete categories.
- [ ] Admins can reorder categories.
- [ ] Category input is sanitized before saving.

Dependencies: Issue 5

Labels: settings, categories, phase-4

## Issue 7

Title: Add admin settings screen and setup tools

Summary: Build the WordPress admin UI for plugin setup, settings management, status checks, and tools.

Acceptance Criteria:
- [ ] Admin settings page is registered.
- [ ] Setup/status panel shows required configuration status.
- [ ] Google API key section is available.
- [ ] Default location section is available.
- [ ] Planner behavior section is available.
- [ ] Categories editor section is available.
- [ ] Security/advanced section is available.
- [ ] Clear cache tool is available.
- [ ] Google API connection test tool is available.

Dependencies: Issue 5, Issue 6

Labels: admin, settings, phase-7

## Issue 8

Title: Register and render shortcode entry point

Summary: Add the first frontend entry point using a shortcode that renders the planner through the plugin renderer and enqueues assets only when needed.

Acceptance Criteria:
- [ ] Shortcode is registered.
- [ ] Shortcode renders planner markup.
- [ ] Assets enqueue only when shortcode renders.
- [ ] Runtime config is passed safely to JavaScript.
- [ ] Missing setup renders an appropriate admin/visitor message.
- [ ] Frontend wrapper class scopes the plugin UI.

Dependencies: Issue 3, Issue 5

Labels: frontend, shortcode, phase-6

## Issue 9

Title: Add block wrapper entry point

Summary: Add a basic block editor entry point that renders the same planner output as the shortcode without duplicating rendering logic.

Acceptance Criteria:
- [ ] Block is registered.
- [ ] Block uses the shared renderer.
- [ ] Editor display is usable.
- [ ] Frontend output matches shortcode behavior.
- [ ] Block assets do not load globally when unused.

Dependencies: Issue 8

Labels: frontend, block-editor, phase-6

## Issue 10

Title: Port frontend assets to plugin asset pipeline

Summary: Move current CSS, JS, and SVG assets into plugin asset paths and update references for WordPress enqueueing.

Acceptance Criteria:
- [ ] CSS is registered with `wp_register_style()`.
- [ ] JS is registered with `wp_register_script()`.
- [ ] SVG/icon assets are loaded from plugin URLs.
- [ ] Existing planner interactions still work.
- [ ] Asset versions use plugin version or build hash.
- [ ] Frontend CSS remains scoped and theme-safe.

Dependencies: Issue 1, Issue 8

Labels: frontend, assets, phase-6

## Issue 11

Title: Implement REST browse and route endpoints

Summary: Implement WordPress REST API routes for browse and route preview requests.

Acceptance Criteria:
- [ ] Browse route is registered.
- [ ] Route preview route is registered.
- [ ] Routes accept POST only.
- [ ] Request schema is defined.
- [ ] Request fields are sanitized.
- [ ] Responses match frontend expectations.
- [ ] Bad requests return structured errors.

Dependencies: Issue 3, Issue 8

Labels: rest-api, backend, phase-5

## Issue 12

Title: Implement anonymous visitor token protection

Summary: Replace session-based request protection with a guest-safe token cookie and HMAC validation suitable for anonymous WordPress visitors.

Acceptance Criteria:
- [ ] Visitor token cookie is issued without PHP sessions.
- [ ] Endpoint token is generated from visitor token and plugin secret.
- [ ] REST endpoints validate token before Google API work.
- [ ] Invalid token returns a structured error.
- [ ] Token strategy works for logged-out visitors.
- [ ] Token strategy works with page caching constraints documented.

Dependencies: Issue 11

Labels: security, rest-api, phase-5

## Issue 13

Title: Implement rate limiter and trusted proxy handling

Summary: Port the file-backed or object-cache-aware rate limiter into the plugin and wire it to trusted-proxy-aware client IP resolution.

Acceptance Criteria:
- [ ] Rate limiter does not rely on PHP sessions.
- [ ] Rate limiter keys by scope, client IP, and minute bucket.
- [ ] Rate limit setting is configurable.
- [ ] Trusted proxy CIDRs are configurable.
- [ ] Invalid X-Forwarded-For candidates are ignored.
- [ ] Changing cookies does not bypass rate limits.
- [ ] Same-cookie repeated requests are limited.

Dependencies: Issue 5, Issue 11, Issue 12

Labels: security, rate-limiting, phase-5

## Issue 14

Title: Implement Google API settings and client abstraction

Summary: Build the Google API client layer using configured keys, cache TTLs, timeouts, field masks, and error handling.

Acceptance Criteria:
- [x] Browser-facing Maps Embed key is separate from server-side keys.
- [x] Places API key is used only server-side.
- [x] Geocoding API key or fallback behavior is documented.
- [x] API timeout is configurable.
- [x] Field masks are explicit.
- [x] API errors return safe user-facing messages.
- [x] Server-side keys are never exposed in frontend config.

Dependencies: Issue 5, Issue 11

Labels: google-api, backend, phase-4

## Issue 15

Title: Implement caching strategy

Summary: Add WordPress-compatible caching for Google text search, place details, and geocoding responses using transients or object cache where appropriate.

Acceptance Criteria:
- [x] Cache keys do not expose API keys.
- [x] Text search cache TTL is configurable.
- [x] Place details cache TTL is configurable.
- [x] Geocoding cache TTL is configurable.
- [x] Clear cache admin tool works.
- [x] Cache behavior is documented.

Dependencies: Issue 14

Labels: caching, backend, phase-4

## Issue 16

Title: Add versioning, migrations, and release build process

Summary: Add a maintainable plugin versioning system, option schema migrations, changelog process, and buildable release artifact.

Acceptance Criteria:
- [ ] Plugin version constant exists.
- [ ] Stored schema/version option exists.
- [ ] Migration runner is implemented.
- [ ] Changelog process is documented.
- [ ] Release zip build process exists.
- [ ] Production zip excludes local config, deprecated backups, and dev caches.
- [ ] Update channel decision is implemented or documented.

Dependencies: Issue 1, Issue 5

Labels: release, maintenance, phase-8

## Issue 18

Title: Add automated tests and quality checks

Summary: Add automated checks for PHP, WordPress standards, settings sanitization, security helpers, REST endpoints, frontend smoke tests, and Plugin Check.

Acceptance Criteria:
- [ ] PHP lint check exists.
- [ ] PHPCS WordPress Coding Standards check exists.
- [ ] Plugin Check workflow exists.
- [ ] Unit tests cover settings sanitization.
- [ ] Unit tests cover safe URL validation.
- [ ] Unit tests cover trusted proxy resolution.
- [ ] Unit tests cover guest token validation.
- [ ] REST endpoint integration tests exist.
- [ ] Browser smoke tests cover render and planner flow.
- [ ] Tests run in CI.

Dependencies: Issue 11, Issue 12, Issue 13

Labels: testing, ci, phase-10

## Issue 19

Title: Complete accessibility and frontend QA pass

Summary: Review and test the plugin frontend for keyboard access, screen reader behavior, focus management, live regions, and theme compatibility.

Acceptance Criteria:
- [ ] Keyboard-only flow works.
- [ ] Screen reader labels and live updates are reviewed.
- [ ] Focus states are visible.
- [ ] Add/remove/reorder interactions remain accessible.
- [ ] No-JavaScript fallback is reviewed or explicitly scoped out.
- [ ] Theme compatibility smoke tests pass.
- [ ] Mobile layout smoke tests pass.

Dependencies: Issue 8, Issue 10, Issue 11

Labels: accessibility, qa, frontend, phase-10

## Issue 20

Title: Write plugin documentation

Summary: Write documentation for installation, setup, settings, Google API restrictions, security model, migration, troubleshooting, and editor/admin usage.

Acceptance Criteria:
- [ ] Admin/editor documentation is written.
- [ ] Developer README is written.
- [ ] Installation guide is written.
- [ ] Settings reference is written.
- [ ] Google Cloud API key restriction guide is written.
- [ ] Security model is documented.
- [ ] Migration guide is written.
- [ ] Troubleshooting guide is written.
- [ ] Changelog is maintained.

Dependencies: Issue 5, Issue 7, Issue 16, Issue 17

Labels: documentation, phase-12
