# Issue 39: Documentation Reconciliation Roadmap

This draft captures the remaining documentation work after the existing install,
settings, security, and troubleshooting docs.

## Docs That Already Exist

- `README.md`
- `docs/INSTALLATION.md`
- `docs/ARCHITECTURE.md`
- `docs/SETTINGS.md`
- `docs/SECURITY.md`
- `docs/TROUBLESHOOTING.md`

## Remaining Gaps

- a concise shortcode usage guide for editors
- explicit block-editor status until issue #28 lands
- an admin workflow guide covering setup, category editing, cache tools, and
  Google API test tooling
- release/changelog documentation once issue #35 implementation lands
- final reconciliation pass so current behavior is not described as still
  theoretical

## Dependencies And Related Work

- issue `#52` covers Google Cloud key restriction guidance
- issue `#61` covers the manual migration path from the standalone runtime
- issue `#28` must land before block usage docs can describe a real supported
  editor block flow
- issue `#35` owns release-process and changelog mechanics

## Proposed Follow-Up Sequence

1. Add editor-facing shortcode and setup docs.
2. Add an admin workflow guide for category management and troubleshooting
   tools.
3. Mark block usage as deferred until the block wrapper PR lands.
4. Reconcile top-level README and canonical docs after the release-process and
   migration docs settle.

## Exit Criteria

The issue should only close once an editor or operator can answer all of the
following from repo docs alone:

- how to install and configure the plugin
- how to place the planner on a page today
- how to manage categories and cache/admin tools
- what is still deferred versus supported
