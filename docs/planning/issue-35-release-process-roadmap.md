# Issue 35: Versioning, Migrations, And Release Process Roadmap

This draft narrows the remaining work for the release/process issue into
mergeable slices.

## What Already Exists

- `PLAN_YOUR_DAY_VERSION` and `PLAN_YOUR_DAY_SCHEMA_VERSION` constants exist in
  the main plugin bootstrap.
- Activation writes the plugin version and schema version options.
- Composer-based source installs and `.distignore` already exist.
- The architecture decisions document already records a GitHub-release-zip
  distribution posture.

## Remaining Gaps

- No migration runner updates stored schema versions after activation.
- No release checklist or changelog source-of-truth is checked in.
- No automated build artifact workflow produces a production zip.
- No documented tagging/release sequence exists for maintainers.
- The update-channel decision is documented, but not surfaced as an operator
  release procedure.

## Proposed Delivery Order

### Slice 1

- add a dedicated migration runner class
- call it on activation and on plugin bootstrap when schema drift is detected
- add unit coverage around no-op and version-bump cases

### Slice 2

- add a maintainer-facing release checklist
- define changelog ownership and release-note source files
- document exactly what gets built into the production zip

### Slice 3

- add an automated build script or GitHub Actions workflow that assembles the
  release zip from `plugin/plan-your-day/`
- verify local config, caches, and deprecated reference material stay out of
  the artifact

## Recommended Files

- `plugin/plan-your-day/src/Support/SchemaMigrator.php`
- `plugin/plan-your-day/tests/`
- `plugin/plan-your-day/release/` or `.github/workflows/`
- `docs/` for release process and changelog policy

## Definition Of Done

The issue should not be considered complete until the repo has:

- executable migration code
- a repeatable release build path
- a maintained changelog/update process
- explicit artifact exclusion rules
