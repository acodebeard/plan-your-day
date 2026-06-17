# Waypoints: Trip Planner Documentation

This directory documents the WordPress plugin as it exists today. The plugin is
installable and has admin/settings, Google API, cache, planner helper, planner
state, shortcode and block rendering, REST endpoints, assets, release zip
packaging, and browser smoke automation in place.

## Current Documents

- [Installation](INSTALLATION.md): source checkout setup, local WordPress test install,
  activation checks, and release zip build/install steps.
- [Frontend Usage](USAGE.md): shortcode and block placement, supported
  entry-point settings, and frontend placement guidance.
- [Admin Workflows](ADMIN.md): settings-page sections, setup order, cache
  tools, and the Google API test workflow.
- [Release Process](RELEASES.md): version metadata alignment, changelog
  expectations, WordPress.org readme/listing assets, and the manual GitHub
  release zip workflow.
- [WordPress.org Listing](WORDPRESS-ORG-LISTING.md): readme source files,
  formatted readme preview, and plugin-directory icons/screenshots.
- [Frontend QA](QA.md): browser smoke coverage, local run steps, and ongoing
  public frontend QA expectations.
- [Architecture](ARCHITECTURE.md): plugin layers, current service boundaries,
  and current runtime structure.
- [Settings](SETTINGS.md): option name, settings groups, defaults, API keys,
  planner behavior, cache, and advanced networking settings.
- [Security](SECURITY.md): current key-handling, escaping, cache, origin, and
  rate-limit posture.
- [Troubleshooting](TROUBLESHOOTING.md): common local install and activation
  problems.

## Still Planned

These topics are intentionally incomplete until their implementation issues
land:

- REST endpoint request/response reference.
- Anonymous visitor token details.

## Historical Planning Notes

These files are preserved as planning snapshots, not as the current source of
truth for implementation status:

- [Plugin TODO Snapshot](WAYPOINTS-PLUGIN-TODO.md)
- [GitHub Issue Draft Snapshot](WAYPOINTS-PLUGIN-ISSUES.md)
- [Issue 20 Plugin Scaffold Plan](ISSUE-20-PLUGIN-SCAFFOLD-PLAN.md)
