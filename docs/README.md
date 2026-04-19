# Plan Your Day Documentation

This directory documents the WordPress plugin migration as it exists today.
The plugin is installable and has admin/settings, Google API, cache, and
planner helper foundations. Public planner rendering, REST endpoints, assets,
migration helpers, release builds, and production QA are still in progress.

## Current Documents

- [Installation](INSTALLATION.md): source checkout setup, local DKC install,
  activation checks, and release zip expectations.
- [Architecture](ARCHITECTURE.md): plugin layers, current service boundaries,
  and what remains in the standalone runtime.
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
- Standalone migration helper documentation.
- Release zip build process and changelog policy.
- Production QA and accessibility notes.
