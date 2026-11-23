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

- [ ] 2.1 Write tests in a new tests/Feature/DocumentationTest.php: README.md contains no `atrium` (case-insensitive), no `registerAtriumPlugin` and no `'ui' =>` key; the `[Unreleased]` section of CHANGELOG.md has `### Breaking` naming `registerAtriumPlugin()`, `ui.enabled`, `Surface::route('atrium.', 'atrium')`, Foundry, Guardian and v0.2.0; composer.json `extra.branch-alias.dev-main` is `0.2.x-dev`.
- [ ] 2.2 README.md: reword the intro without naming Atrium (keep "the Refactor Circus package suite"), drop the `registerAtriumPlugin()` line from the provider example, drop `'ui' => ['enabled' => true]` from the config example, and drop `atrium` from the `Support\Surface` row (list `http`, `mcp`, `cortex`, `cli`, `code`, or a named one).
- [ ] 2.3 CHANGELOG.md: under `[Unreleased]` add `### Breaking` (release v0.2.0): `registerAtriumPlugin()` removed, Atrium registers package UIs itself; `{key}.ui.enabled` no longer read, drop it from package configs; `Surface` has no built-in `atrium.` prefix, a host calls `Surface::route('atrium.', 'atrium')`; Foundry and Guardian stay on `^0.1` (v0.1.1) until they migrate.
- [ ] 2.4 composer.json: set `extra.branch-alias.dev-main` to `0.2.x-dev`; run `composer validate`.
- [ ] 2.5 docs/agent/standards-feedback.md: add a row (2026-11-23, this spec, architecture/service-providers, the standard still lists `registerAtriumPlugin()` which Keystone removed, needs a Standards spec).
- [ ] 2.6 Run vendor/bin/pest, composer lint:check and composer analyse; all green.
