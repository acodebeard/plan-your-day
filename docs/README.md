# Plan Your Day Documentation

This directory documents the WordPress plugin as it exists today. The plugin is
installable and has admin/settings, Google API, cache, planner helper, planner
state, frontend rendering, REST endpoints, assets, and release zip packaging
in place. Production QA is still in progress.

## Current Documents

- [Installation](INSTALLATION.md): source checkout setup, local WordPress test install,
  activation checks, and release zip build/install steps.
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

- Editor and frontend usage documentation.
- Shortcode and block instructions.
- REST endpoint request/response reference.
- Anonymous visitor token details.
- Changelog policy.
- Production QA and accessibility notes.
