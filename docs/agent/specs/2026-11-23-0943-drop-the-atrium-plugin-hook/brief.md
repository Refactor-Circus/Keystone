Risk: high

## What changed
- Atrium removal: `PackageServiceProvider::registerAtriumPlugin()`, the `{key}.ui.enabled` convention and the built-in `atrium.` surface prefix in `Surface` are gone. Doc comments in `Tables`, `Arch`, `PackageRegistry` and `AuditTrail` use neutral examples. README and CHANGELOG (`### Breaking`) are updated.
- Authorization seam (addendum): new `src/Authorization/` with the `Permissions`, `Roles` and `Scope` contracts, a Gate-backed `GatePermissions`, a no-op `NullRoles`, the `Abilities` registry and `ScopeChain`. `Authorizer::allows()` and `Policy::allowsAbility()` are added. In-memory `Testing\FakePermissions` and `FakeRoles` are added.
- Release: `composer.json` branch alias moves from `0.1.x-dev` to `2.0.x-dev`. One row is added to `docs/agent/standards-feedback.md` for the standards drift.
- Tests: arch rules, `src/` string scan, surface, authorization, fakes and documentation tests.

## Why
Keystone must not depend on or name any suite package, and Atrium now registers each package's UI itself (spec.md goal). The addendum moves roles and permissions from Roster to Guardian, so packages need a seam that does not name either one. Decisions that shaped it:
- D-006 and D-013: the whole suite releases as v2.0.0, which supersedes the spec's v0.2.0 and `0.2.x-dev`.
- D-002: the `atrium` surface is removed, and a host can still name it through `Surface::route()`.
- D-007 to D-012 and D-014: the contract is limited to methods Roster uses today. Defaults are bound with `singletonIf`. The Gate fallback refuses when it cannot know the answer and guards against re-entry. The helpers are additive.
- D-004 and D-005: vendor standards are not edited and nothing is tagged.

## Look at
- `src/KeystoneServiceProvider.php:37`: the default bindings. A role store must be able to override them in either provider order, and decorators must survive a rebind.
- `src/Authorization/GatePermissions.php:32`: `allows()` covers the scope-chain walk, the re-entry guard cleared in `finally`, and guest handling.
- `src/Support/Surface.php:144`: `prefixes()` no longer adds `atrium.`. Check that audit surface values for Atrium routes fall back as described in the CHANGELOG.
- `composer.json:108`: the alias is now `2.0.x-dev`. Confirm this is the intended version line.
- `src/Testing/FakePermissions.php` and `FakeRoles.php`: they find each other through the container, so check the install-order behaviour.

## Tested
- Criteria 1 and 2: `tests/Feature/PackageTest.php` and `tests/ArchTest.php`.
- Criterion 3: the unmodified `tests/Feature/HistoryTest.php`.
- Criteria 4 and 5: `tests/Unit/SurfaceTest.php`.
- Criterion 6: `tests/ArchTest.php`, the namespace rule and the `src/` scan.
- Criteria 7 to 9 and 25: `tests/Feature/DocumentationTest.php`.
- Criteria 11 to 22: `tests/Feature/AuthorizationContractTest.php`, `tests/Unit/AbilitiesTest.php` and `tests/Unit/AuthorizerTest.php`.
- Criterion 23: `tests/Feature/AuthorizationFakesTest.php`.
- Criterion 24: `tests/ArchTest.php`.
- Gate (round 1): VERDICT PASS. `composer test` ran 158 tests with 100% coverage, and `composer lint:check` and `ringside check` passed.

## Risks
- Breaking for every caller of `registerAtriumPlugin()` (Cortex, Impex, Keen, Polycart, Showroom, Foundry), which fail with an undefined method until they migrate. The alias bump keeps `^0.1` constraints off `main`.
- The audit surface label for `atrium.*` routes changes unless Atrium calls `Surface::route('atrium.', 'atrium')`.
- Packages whose tests relied on Roster's roles now run against the Gate fallback and must use `FakePermissions` or `Gate::define()`.
- The CHANGELOG text still says "Foundry, and any package that still calls `registerAtriumPlugin()`, stays on `^0.1`". This matches D-013, since only Guardian converts.
- `architecture/service-providers.md` in the standards still lists `registerAtriumPlugin()`. It is recorded as feedback and needs a follow-up spec in Standards.
- Open findings: none (nits: 0).
