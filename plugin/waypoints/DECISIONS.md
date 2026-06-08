# Plugin Architecture And Compatibility Decisions

Decisions frozen across the issue #20 scaffold and issue #21 architecture pass.
Each is a one-way door in the PITFALLS.md D5 sense — renaming namespaces,
swapping autoloaders, changing distribution posture, or retrofitting uninstall
routines is doable but churny once classes multiply.

Rationale references cited are from the discovery milestone's research set
(lives in the companion `plan-ur-day-docs` planning repo, not this source
repo). Each decision can be revisited when that milestone's recommendation
(`REC-02`) lands.

## 1. Autoloader — Composer PSR-4

- `composer.json` declares `"psr-4": { "Acodebeard\\PlanYourDay\\": "src/" }`.
- Main plugin file requires `vendor/autoload.php`; if missing, activation fails
  visibly and active installs show an admin notice.
- `vendor/` is gitignored at the source-repo root. Release pipeline runs
  `composer install --no-dev --optimize-autoloader` before zipping.
- Built release zips include generated Composer autoload files and Composer
  metadata; target sites should not need to run Composer.
- Enables Strauss / PHP-Scoper dep prefix-scoping later without a rename
  migration (PITFALLS.md B2).

## 2. Namespace shape — vendor-prefixed hybrid

- Root namespace `Acodebeard\PlanYourDay\` holds `Activator`, `Deactivator`,
  `Plugin`.
- Subpackages (`\Http`, `\Google`, `\Rest`, `\Admin`, `\Core`) added as the
  first class of each domain lands — not reserved speculatively.
- The vendor prefix reduces collision risk in WordPress' shared PHP runtime.
- Matches research ARCHITECTURE.md layering without locking it in before we
  have second-class data.

## 3. `uninstall.php` — ships with scaffold

- Brackets the two options the Activator creates
  (`plan_your_day_version`, `plan_your_day_schema_version`).
- Guarded by `defined( 'WP_UNINSTALL_PLUGIN' ) || exit`.
- Closes PITFALLS.md B11 (deactivate vs uninstall bracket) while the state
  surface is still small enough to be obvious.

## 4. i18n — `load_plugin_textdomain` on `init` priority 0

- Registered by `Plugin::init()` via `add_action`.
- WordPress 6.7+ warns when translations are loaded too early. Manual textdomain
  loading should happen at `init` or later, or be omitted for WordPress.org
  plugins that rely on just-in-time translation loading.
- Using `init` priority 0 keeps the scaffold ready for custom language files
  without encouraging translation calls before WordPress knows the current user.

## 5. PHP / WordPress floors — PHP 8.2 / WP 6.8

- Diverges from the plan file's 8.1 / 6.4 targets.
- PHP 8.1 reached end-of-life in December 2025; 8.2 is the oldest actively
  supported line.
- WP 6.8 matches `@wordpress/scripts` and block-editor feature availability
  (`viewScriptModule`, interactivity API) needed by subsequent port issues.
- **Revisit gate:** DOC-03 of the discovery milestone runs PHPCompatibilityWP
  against the plugin runtime entry points. If it surfaces constructs requiring
  newer-than-8.2 features, fine — we're already right. If it surfaces
  backward-compat needs against older hosts, the scaffold's headers drop back
  to the plan's 8.1 / 6.4 values. No work lost either way at this stage.

## 6. Distribution / update channel — GitHub release zip, no updater in v1

- v1 is distributed as a built release zip generated from this source
  repository.
- The plugin is not targeting WordPress.org distribution for v1.
- No custom/private updater ships in the MVP. Manual deployment from release
  zips is the supported update path until a later release issue introduces an
  updater.
- Release zips include Composer autoload files; production sites should not
  need Composer.
- Issue #35 owns the build artifact, changelog, tag process, and any `Update
  URI` or custom updater work that follows from this decision.

## 7. Multisite — single-site v1

- v1 supports normal single-site activation and per-site settings.
- Network activation is not a v1 requirement.
- Multisite behavior should not be implied by activation hooks or admin copy
  until there is an explicit multisite issue.
- If multisite support is added later, it needs a separate decision about
  network-wide defaults versus per-site planner configuration.

## 8. Frontend entry points — shortcode first, block wrapper second

- The shortcode is the first public rendering entry point.
- The block editor entry point is a wrapper around the shared renderer, not a
  separate implementation.
- Assets should enqueue only when a planner is rendered.
- Runtime configuration passed to JavaScript must be generated from sanitized
  settings and must never include server-side Google API keys.

## 9. Endpoint model — WordPress REST API

- Public planner interactions use WordPress REST API routes, not
  `admin-ajax.php` or ad hoc query actions.
- Browse and route-preview requests are POST-only.
- REST request schemas own input validation and sanitization before Google API
  work starts.
- Anonymous requests use the guest-safe token strategy from issue #31 and the
  rate limiter / trusted proxy work from issue #32.
- Endpoint responses should remain shaped for the frontend planner, while using
  structured errors for bad requests and provider failures.

## 10. Initial plugin layers

- `Plugin` wires WordPress hooks and long-lived collaborators.
- `Settings` owns option defaults, sanitization, and read access.
- `Admin` owns settings screens, setup notices, and tools.
- `Google` owns provider HTTP calls, field masks, result objects, and caching.
- Future `Rest`, `Security`, `Renderer`, and `Assets` classes should be added
  as those issue scopes land, keeping temporary compatibility shims out of
  plugin code.

## What is deliberately deferred (not silent prereqs — noted here)

- **REST namespace details.** The endpoint model is WordPress REST API. Exact
  namespace and route names land with issue #30.
- **Test harness (`phpunit.xml.dist`, `tests/`).** Noted in issue tracker —
  belongs in a dedicated CI/testing issue, not hidden inside scaffold.
- **Updater implementation.** v1 distribution is manual release zip. Any
  `Update URI` header or custom updater implementation belongs to issue #35.
