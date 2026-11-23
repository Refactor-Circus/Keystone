# Decisions

Spec review was skipped on the user's instruction (proceed without stopping); `meta.yml` is set to `status: approved` directly by write-spec.

## D-001: Is this v0.2.0 or v1.0.0?
- Choice: v0.2.0. The CHANGELOG entry sits under `[Unreleased]` with a `### Breaking` section that says so, and `composer.json` `branch-alias` moves `dev-main` from `0.1.x-dev` to `0.2.x-dev`.
- Rationale: the suite is on v0.1.x (tags v0.1.0, v0.1.1) and in 0.x a breaking change bumps the minor. v1.0.0 would declare the API stable, which is not this change's call. Moving the alias keeps `^0.1` dependents (Foundry, Guardian) off a `main` without the hook.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-002: Does the built-in `atrium` surface in `Surface` go too?
- Choice: yes. Remove the hard-coded `atrium.` → `atrium` prefix; `Surface::route()` stays the way a host (Atrium) names its own surface.
- Rationale: the shape says Keystone stops naming Atrium in `src/`; `route()` already lets the host keep the label, and the release is breaking anyway. Recorded as a breaking change.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-003: What happens to Atrium mentions that are only doc-comment examples (`Tables`, `Arch`, `PackageRegistry`, `AuditTrail`) and the README's suite list?
- Choice: replace them with neutral examples (`acme`, `Acme\Shop`) and reword the README intro without naming Atrium. Historic specs and `CLAUDE.md` stay as they are.
- Rationale: a test enforces no `atrium` string in `src/`, which is simpler and stricter than allowing comment exceptions; the README is the package's public doc and the shape includes docs. Historic specs are records, not documentation.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-004: Does Keystone edit the shared standard (`architecture/service-providers.md`) that lists `registerAtriumPlugin()`?
- Choice: no. Record the drift as a row in `docs/agent/standards-feedback.md` and leave the fix to a spec in the Standards package.
- Rationale: every group changes this package only; vendor files are never edited.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-005: Tag the release in this job?
- Choice: no. The job writes the CHANGELOG under `[Unreleased]`; tagging v0.2.0 is the release step after merge.
- Rationale: conservative; releases are made by the package-release workflow, not by a factory job.
- Date: 2026-11-23
- Raised by: write-spec; default chosen because the user asked to proceed without stopping

## D-006: Which version does the suite release under?
- Choice: Every suite package releases as v2.0.0 to match: Keystone, Atrium, Cortex, Impex, Keen, PennantPlus, Polycart, Roster and Showroom. Every `refactor-circus/*` constraint (require and require-dev) is `^2.0`, and every repo aliases `dev-main` to `2.0.x-dev`. Foundry and Guardian stay on Keystone `^0.1` until they migrate.
- Rationale: The user said "i'd like everything release as v2 to match". This supersedes any earlier choice to keep `^0.1` constraints or release Keystone as v0.2.0.
- Date: 2026-11-23
- Raised by: user
