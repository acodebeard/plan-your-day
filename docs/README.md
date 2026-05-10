# Plan Your Day Documentation

This directory documents the WordPress plugin as it exists today. The plugin is
installable and has admin/settings, Google API, cache, planner helper, planner
state, shortcode and block rendering, REST endpoints, assets, and release zip
packaging in place. Broader production QA automation is still in progress.

## Current Documents

- [Installation](INSTALLATION.md): source checkout setup, local WordPress test install,
  activation checks, and release zip build/install steps.
- [Frontend Usage](USAGE.md): shortcode and block placement, supported
  entry-point settings, and frontend placement guidance.
- [Admin Workflows](ADMIN.md): settings-page sections, setup order, cache
  tools, and the Google API test workflow.
- [Release Process](RELEASES.md): version metadata alignment, changelog
  expectations, and the manual GitHub release zip workflow.
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
- Production QA and accessibility notes.

## Historical Planning Notes

These files are preserved as planning snapshots, not as the current source of
truth for implementation status:

- [Plugin TODO Snapshot](PLAN-YOUR-DAY-PLUGIN-TODO.md)
- [GitHub Issue Draft Snapshot](PLAN-YOUR-DAY-PLUGIN-ISSUES.md)
