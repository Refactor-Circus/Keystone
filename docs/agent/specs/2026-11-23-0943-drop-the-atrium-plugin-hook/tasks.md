# Tasks: Drop the Atrium plugin hook

## Group 1: Remove Atrium from the runtime
depends on: none
files: src/Support/PackageServiceProvider.php, src/Support/Surface.php, src/Database/Tables.php, src/Testing/Arch.php, src/Packages/PackageRegistry.php, src/Audit/Contracts/AuditTrail.php, tests/Unit/SurfaceTest.php, tests/ArchTest.php, tests/Feature/PackageTest.php
criteria: 1, 2, 3, 4, 5, 6, 10

- [x] 1.1 Write tests: `method_exists(PackageServiceProvider::class, 'registerAtriumPlugin')` is false in tests/Feature/PackageTest.php; in tests/Unit/SurfaceTest.php replace the `atrium.dashboard` → `atrium` expectation with an unregistered `atrium.dashboard` resolving to `cli`, and a test that `route('atrium.', 'atrium')` makes it resolve to `atrium`; in tests/ArchTest.php add `arch()->expect('RefactorCircus\Keystone')->not->toUse('RefactorCircus\Atrium')` and a test that scans every file under src/ for `atrium` (case-insensitive) and for `ui.enabled` and expects none.
- [x] 1.2 Delete `registerAtriumPlugin()` from src/Support/PackageServiceProvider.php and rewrite its class docblock: helpers read policies, MCP routes and the Cortex switch; config shape ends at `cortex{enabled,server,tools}` with no `ui{enabled}`. Leave `loadHistoryRoutes()` and every other method untouched.
- [x] 1.3 In src/Support/Surface.php drop the `$prefixes['atrium.']` line in `prefixes()` and rewrite the class docblock (surfaces `http`, `mcp`, `cortex`, `cli`, `code`, or one a package or host names with `route()`).
- [x] 1.4 Replace the Atrium examples in doc comments with neutral ones: src/Database/Tables.php (`acme.tables`, `acme_widgets`), src/Testing/Arch.php (`'acme_widgets'`), src/Packages/PackageRegistry.php (`Acme\Shop` vs `Acme\ShopPlus`), src/Audit/Contracts/AuditTrail.php ("every package and any dashboard").
- [x] 1.5 Run vendor/bin/pest (tests/Feature/HistoryTest.php must pass unmodified), composer lint:check and composer analyse; all green.

## Group 2: Docs, changelog and branch alias
depends on: 1
files: README.md, CHANGELOG.md, composer.json, docs/agent/standards-feedback.md, tests/Feature/DocumentationTest.php
criteria: 7, 8, 9, 10

- [x] 2.1 Write tests in a new tests/Feature/DocumentationTest.php: README.md contains no `atrium` (case-insensitive), no `registerAtriumPlugin` and no `'ui' =>` key; the `[Unreleased]` section of CHANGELOG.md has `### Breaking` naming `registerAtriumPlugin()`, `ui.enabled`, `Surface::route('atrium.', 'atrium')`, Foundry, Guardian and v0.2.0; composer.json `extra.branch-alias.dev-main` is `0.2.x-dev`.
- [x] 2.2 README.md: reword the intro without naming Atrium (keep "the Refactor Circus package suite"), drop the `registerAtriumPlugin()` line from the provider example, drop `'ui' => ['enabled' => true]` from the config example, and drop `atrium` from the `Support\Surface` row (list `http`, `mcp`, `cortex`, `cli`, `code`, or a named one).
- [x] 2.3 CHANGELOG.md: under `[Unreleased]` add `### Breaking` (release v0.2.0): `registerAtriumPlugin()` removed, Atrium registers package UIs itself; `{key}.ui.enabled` no longer read, drop it from package configs; `Surface` has no built-in `atrium.` prefix, a host calls `Surface::route('atrium.', 'atrium')`; Foundry and Guardian stay on `^0.1` (v0.1.1) until they migrate.
- [x] 2.4 composer.json: set `extra.branch-alias.dev-main` to `0.2.x-dev`; run `composer validate`.
- [x] 2.5 docs/agent/standards-feedback.md: add a row (2026-11-23, this spec, architecture/service-providers, the standard still lists `registerAtriumPlugin()` which Keystone removed, needs a Standards spec).
- [x] 2.6 Run vendor/bin/pest, composer lint:check and composer analyse; all green.

## Addendum 2026-11-23: authorization contract

Builders: if a design question comes up, choose the most conservative option consistent with spec.md and decisions.md, record it as the next D-entry in decisions.md, and continue. You may change files outside a group's list when the group needs it; commit them with the group.

## Group 3: Authorization contracts, Gate fallback and bindings
depends on: 2
files: src/Authorization/Contracts/Permissions.php, src/Authorization/Contracts/Roles.php, src/Authorization/Contracts/Scope.php, src/Authorization/GatePermissions.php, src/Authorization/NullRoles.php, src/Authorization/Abilities.php, src/Authorization/ScopeChain.php, src/KeystoneServiceProvider.php, tests/Feature/AuthorizationContractTest.php, tests/Unit/AbilitiesTest.php, tests/Fixtures/Acme/Authorization/*, tests/ArchTest.php
criteria: 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 24

- [ ] 3.1 Write tests: `AuthorizationContractTest` (default bindings; a fixture provider binding its own Permissions/Roles before and after Keystone wins; an `extend()` decorator survives the rebind; `Gate::define()` honoured, undefined refused, guest refused; a fixture team Scope whose parent organization `grantsByItself` allows; `ScopeChain::of()` order and loop stop; a `Gate::before` calling `allows()` re-entrantly does not recurse; `scopesWith`/`abilities`/`isSuperAdmin`/`forget` fallback answers; NullRoles answers with a query counter at zero), `AbilitiesTest` (define/has/all sorted; duplicate, empty and whitespace names throw), and the arch rules (contracts are interfaces, classes final, no Roster/Guardian use or string in `src/Authorization`)
- [ ] 3.2 Add the three interfaces with full docblocks
- [ ] 3.3 Implement `ScopeChain`, `GatePermissions` (scope walk, re-entry guard in `finally`), `NullRoles` and `Abilities`
- [ ] 3.4 Bind `Abilities`, `Permissions` and `Roles` with `singletonIf` in `KeystoneServiceProvider::register()`
- [ ] 3.5 Run vendor/bin/pest, composer lint:check and composer analyse; all green

## Group 4: Authorizer and Policy helpers, testing fakes
depends on: 3
files: src/Auth/Authorizer.php, src/Policies/Policy.php, src/Testing/FakePermissions.php, src/Testing/FakeRoles.php, tests/Unit/AuthorizerTest.php, tests/Feature/AuthorizationFakesTest.php
criteria: 21, 22, 23

- [ ] 4.1 Write tests: `Authorizer::allows()` true with authorization off and the contract's answer with it on (using a bound fake); `can()` unchanged; a fixture policy's `allowsAbility()` asks the bound contract, for a guest too; `FakePermissions::install()` binds, `grant()` per scope, `superAdmin()`, honours `grantsByItself()` and parents; `FakeRoles::install()`, `define()`, `assign()`/`of()`/`revoke()`/`revokeEverywhere()`/`holders()`/`exists()` and the abilities a role grants show up in an installed `FakePermissions`
- [ ] 4.2 Add `Authorizer::allows()` and `Policy::allowsAbility()`
- [ ] 4.3 Implement `FakePermissions` and `FakeRoles` (final, in-memory, using `ScopeChain`)
- [ ] 4.4 Run vendor/bin/pest, composer lint:check and composer analyse; all green

## Group 5: Authorization docs and changelog
depends on: 4
files: README.md, CHANGELOG.md, tests/Feature/DocumentationTest.php
criteria: 25, 26

- [ ] 5.1 Write tests in `DocumentationTest`: README has an "Authorization" section naming `Contracts\Permissions`, `Contracts\Roles`, `Contracts\Scope`, `GatePermissions`, `NullRoles`, `Abilities::define`, `Authorizer::allows`, `allowsAbility`, `extend(` and `FakePermissions`; CHANGELOG `[Unreleased]` has `### Added` naming the authorization contract
- [ ] 5.2 Write the README "Authorization" section and the CHANGELOG `### Added` entry; if the `### Breaking` text says Guardian stays on `^0.1`, change it to Foundry only (D-013)
- [ ] 5.3 Run vendor/bin/pest, composer lint:check and composer analyse; all green
