# Release Process

Waypoints: Trip Planner v1 ships as a manual GitHub release zip while
WordPress.org submission and listing metadata are maintained in source. The
plugin does not include a private updater or a custom `Update URI` workflow
right now.

## Release Metadata

Keep these values aligned before building a release:

- plugin header `Version` in the root PHP bootstrap file
- plugin header `Plugin Name` and `Text Domain`
- `PLAN_YOUR_DAY_VERSION`
- `PLAN_YOUR_DAY_TEXT_DOMAIN`
- `PLAN_YOUR_DAY_SCHEMA_VERSION`
- `plugin/waypoints/release.json`
- the artifact filename in `release.json`
- the `Unreleased` and versioned entries in `plugin/waypoints/readme.txt`
- `plugin/waypoints/readme.html` when the public readme copy changes

The release builder validates that:

- `release.json.version` matches `PLAN_YOUR_DAY_VERSION`
- `release.json.schemaVersion` matches `PLAN_YOUR_DAY_SCHEMA_VERSION`
- the artifact filename and top-level zip folder match the plugin slug and version

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

- a top-level `waypoints-trip-planner/` directory
- production Composer autoload files included
- development-only files excluded through `.distignore`

Production sites should install the built zip and should not need to run
Composer on the server.

The formatted `plugin/waypoints/readme.html` preview is excluded from the
installable zip. The canonical `readme.txt` remains in the plugin package.

Current v1.0.2 artifact:

- filename: `waypoints-trip-planner-1.0.2.zip`

## WordPress.org Readme And Listing Assets

WordPress.org reads the plugin listing copy from `plugin/waypoints/readme.txt`.
Keep `plugin/waypoints/readme.html` in sync as a formatted source preview when
the public readme text changes.

The repository root `assets/` directory mirrors the top-level `assets/`
directory used by the WordPress.org plugin SVN repository. It is for listing
artwork only:

- `assets/icon-128x128.png`
- `assets/icon-256x256.png`
- `assets/screenshot-1.png`

Do not put WordPress.org listing icons or screenshots in
`plugin/waypoints/assets/`; that directory is reserved for plugin runtime CSS,
JavaScript, fonts, and other files loaded by WordPress.

Readme-only listing updates may change `plugin/waypoints/readme.txt`,
`plugin/waypoints/readme.html`, and root `assets/` without bumping the plugin
version or rebuilding the GitHub release zip, as long as no runtime plugin files
change.

When publishing to WordPress.org SVN, copy the updated `readme.txt` into both
`trunk/readme.txt` and the current stable tag path, such as
`tags/1.0.2/readme.txt`. WordPress.org reads listing content from the stable tag
when `Stable tag` points at a versioned tag. Copy root `assets/` images to the
SVN checkout's top-level `assets/` directory.

Before publishing a listing-only update, run:

```sh
php plugin/waypoints/tools/check-wp-submission-readiness.php --plugin-dir=plugin/waypoints
tidy -errors -quiet plugin/waypoints/readme.html
identify -format '%f %wx%h %m %b\n' assets/icon-128x128.png assets/icon-256x256.png assets/screenshot-1.png
```

## Update Expectations

Current update flow before WordPress.org release automation exists:

- build a new release zip from this source repository
- publish it through GitHub Releases
- install or replace it manually on the target WordPress site

If the project later adds WordPress.org release automation, a private updater,
or `Update URI` support, that should land under a separate Issue rather than
expanding the current manual release process silently.
