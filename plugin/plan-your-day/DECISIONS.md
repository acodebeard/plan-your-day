# Scaffold Decisions

Decisions frozen at issue #20 scaffold commit. Each is a one-way door in the
PITFALLS.md D5 sense — renaming namespaces, swapping autoloaders, or retrofitting
uninstall routines is doable but churny once classes multiply.

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
  against the standalone `index.php`. If it surfaces constructs requiring
  newer-than-8.2 features, fine — we're already right. If it surfaces
  backward-compat needs against older hosts, the scaffold's headers drop back
  to the plan's 8.1 / 6.4 values. No work lost either way at this stage.

## What is deliberately deferred (not silent prereqs — noted here)

- **Multisite posture** (PITFALLS.md B7). Scaffold uses `register_activation_hook`
  (single-site). Network-activation semantics decided in the issue that
  introduces the first multisite-relevant state (options or custom tables).
- **Capabilities / nonces / REST namespace.** None of these yet; no endpoints
  in the scaffold. Decided alongside the first admin screen or REST route
  (GH issues 22+).
- **Test harness (`phpunit.xml.dist`, `tests/`).** Noted in issue tracker —
  belongs in a dedicated CI/testing issue, not hidden inside scaffold.
- **Update URI header.** Correctly deferred by the plan file until
  distribution channel is decided (REC-02 adjacent).
