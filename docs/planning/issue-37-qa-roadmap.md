# Issue 37: QA And Coverage Roadmap

This draft captures the remaining quality-check work after the baseline test and
CI scaffold landed.

## Current Baseline

- PHPUnit, PHPCS, and lint scripts exist.
- A GitHub quality workflow already runs the baseline checks.
- REST route coverage exists for the first public planner behavior.
- Browser smoke coverage exists in repository test scripts, but it is not yet a
  complete end-to-end policy for public planner flows.

## Remaining Gaps

- Broader REST integration coverage for browse and route-state permutations.
- A stable browser smoke path for initial render and core planner flow.
- Clear boundaries for which behavior is unit-tested versus browser-verified.
- A maintained CI policy defining which checks are required for new plugin work.

## Proposed Follow-Up Slices

### Slice 1

- document the required CI gates for plugin changes
- classify current checks as required or informational
- add missing workflow notes where local tooling and CI diverge

### Slice 2

- expand REST integration coverage around route mutations and degraded provider
  responses
- capture fixtures for representative browse and route payloads

### Slice 3

- stabilize browser smoke coverage for one public happy path
- document the fallback manual QA steps when browser automation cannot run

## Validation Expectations

Each follow-up PR should state:

- which layer it is covering
- which user flow or regression it protects
- whether the new check is required in CI or advisory only

## Exit Criteria

This issue should only close once the repo has a documented, enforceable answer
for:

- required CI checks
- minimum REST route coverage
- minimum browser/manual smoke coverage
