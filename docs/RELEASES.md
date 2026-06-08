# Release Process

Waypoints v1 ships as a manual GitHub release zip. The plugin does not include a
private updater or a custom `Update URI` workflow right now.

## Release Metadata

Keep these values aligned before building a release:

- plugin header `Version` in the root PHP bootstrap file
- `PLAN_YOUR_DAY_VERSION`
- `PLAN_YOUR_DAY_SCHEMA_VERSION`
- `plugin/waypoints/release.json`
- the artifact filename in `release.json`
- the `Unreleased` and versioned entries in `plugin/waypoints/readme.txt`

The release builder validates that:

- `release.json.version` matches `PLAN_YOUR_DAY_VERSION`
- `release.json.schemaVersion` matches `PLAN_YOUR_DAY_SCHEMA_VERSION`
- the artifact filename matches the plugin slug and version

## Standard Release Steps

1. Update the plugin version metadata and changelog entries.
2. Run the plugin test suite:

   ```sh
   cd plugin/waypoints
   composer test
   ```

3. Build the installable release zip:

   ```sh
   cd plugin/waypoints
   ./tools/build-release-zip.sh
   ```

4. Confirm the artifact was created at the path defined in `release.json`.
5. Smoke-test the built zip in a WordPress install when the release includes
   user-facing or upgrade-sensitive changes.
6. Create the Git tag and GitHub release, then attach or publish the built zip.

## Installable Artifact

The current release artifact is an installable WordPress admin zip with:

- a top-level `waypoints/` directory
- production Composer autoload files included
- development-only files excluded through `.distignore`

Production sites should install the built zip and should not need to run
Composer on the server.

## Update Expectations

Current update flow:

- build a new release zip from this source repository
- publish it through GitHub Releases
- install or replace it manually on the target WordPress site

If the project later adds a private updater or `Update URI` support, that
should land under a separate Issue rather than expanding the current manual
release process silently.
