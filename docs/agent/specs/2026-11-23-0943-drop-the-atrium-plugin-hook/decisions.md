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

## D-007: Where does the authorization seam live, and what is in it?
- Choice: In Keystone, namespace `RefactorCircus\Keystone\Authorization`: three interfaces (`Contracts\Permissions`, `Contracts\Roles`, `Contracts\Scope`), a Gate-backed default (`GatePermissions`), a no-op role store (`NullRoles`), an ability registry (`Abilities`), `ScopeChain`, and in-memory fakes under `Testing`. Methods are only those Roster's code calls today: checks in a scope, organization-limited lists (`scopesWith`), impersonation's escalation guard (`abilities`), super-admin checks, cache forgetting, default role on join, revoking on leave/delete, approvers (`holders`) and import/export (`exists`, `of`).
- Rationale: The user decided roles and permissions move to Guardian and that packages ask a Keystone contract with a Gate fallback. Keeping the contract to observed usages keeps it small.
- Date: 2026-11-23
- Raised by: user (seam); shape of the methods defaulted by the spec writer because no user was available

## D-008: How do scope hierarchy and an organization owner's rights survive without Roster's Permissions service?
- Choice: A `Scope` interface implemented by scope models: `parentScope()` (a team's organization) and `grantsByItself()` (an owner's own rule). Every implementation, including the Gate fallback, honours both.
- Rationale: The role store must not know Roster's organizations or teams, and the owner rule is membership data that belongs to Roster. Putting it on the scope model keeps both generic and lets Guardian's SSO/SCIM checks keep the owner rule without naming Roster's authorizer.
- Date: 2026-11-23
- Raised by: spec writer; conservative default

## D-009: How does a package override the binding?
- Choice: Keystone binds with `singletonIf`; a role store binds with `singleton()` or `scoped()` in its `register()`, which wins in either provider order. Cross-cutting rules (Roster's impersonation block) wrap the binding with `$app->extend()`, which Laravel keeps across a rebind.
- Rationale: The same pattern as `AuditTrail`/`NullAuditTrail`, already used by Keen. No new registration API is needed.
- Date: 2026-11-23
- Raised by: spec writer; conservative default

## D-010: What does the Gate fallback answer for questions it cannot know?
- Choice: `scopesWith()` is null when the ability is allowed with no scope, otherwise `[]`; `abilities()` is null ("cannot list"); `isSuperAdmin()` asks the Gate for `super-admin`, so it is false unless the host defines that ability; `NullRoles` stores nothing and answers empty.
- Rationale: Each answer refuses rather than grants when the fallback has no data. Callers treat `null` from `abilities()` as "unknown" and must not read it as "no abilities".
- Date: 2026-11-23
- Raised by: spec writer; conservative default

## D-011: Can the Gate fallback loop?
- Choice: `GatePermissions` guards re-entry per user, ability and scope; a re-entrant call answers false.
- Rationale: Roster keeps a `Gate::before` for its own abilities that asks the contract; with the Gate fallback bound, that would recurse without a guard.
- Date: 2026-11-23
- Raised by: spec writer; conservative default

## D-012: How do the new helpers compose with `Authorizer::can()` and `Policy::allowsOn()`?
- Choice: Both stay unchanged. `Authorizer::allows()` (named ability in a scope, off when `authorization` is off) and `Policy::allowsAbility()` (a policy asking the contract) are added beside them. The helper is not named `permits()` because Roster's `Policy` subclass already has a `permits()` with an incompatible signature.
- Rationale: Additive only, so no package breaks.
- Date: 2026-11-23
- Raised by: spec writer; conservative default

## D-013: Spec review for the addendum, and Guardian's version
- Choice: Spec review is skipped on the user's instruction; status stays `approved`. Guardian now converts to v2 too ("all things security", user decision 2026-11-23), which supersedes D-006's "Foundry and Guardian stay on Keystone `^0.1`" for Guardian only; Foundry is unchanged. The CHANGELOG text written by group 2 is corrected in group 5 if it still says Guardian stays on `^0.1`.
- Rationale: User instruction: build and gate, no review rounds.
- Date: 2026-11-23
- Raised by: user
