# Drop the Atrium plugin hook

## Goal

Keystone stops naming Atrium: Atrium hosts the suite's front ends and registers each package's UI itself, so the base package loses its plugin hook, the `{key}.ui.enabled` convention and its built-in `atrium` surface. Package authors get a base `PackageServiceProvider` that knows nothing of any dashboard; history routes stay.

## Acceptance criteria

1. `PackageServiceProvider` has no `registerAtriumPlugin()` method (a test asserts `method_exists(PackageServiceProvider::class, 'registerAtriumPlugin')` is false).
2. Keystone reads no `{key}.ui.*` config key: no file in `src/` contains `ui.enabled` or `configKey('ui`, and the `PackageServiceProvider` class docblock's shared config shape no longer lists `ui{enabled}`.
3. `loadHistoryRoutes()` is unchanged: `GET {prefix}/history` is still served inside the package's JSON API route group (the existing `tests/Feature/HistoryTest.php` passes unmodified).
4. `Surface` has no built-in `atrium` surface: with no prefix registered, a route named `atrium.dashboard` resolves like any other unknown route name (`cli` in the console, `code` otherwise), and `Surface::current()` no longer documents `atrium` among its surfaces.
5. A host can still name its own surface: after `app(Surface::class)->route('atrium.', 'atrium')`, a route named `atrium.dashboard` resolves to `atrium` (tested in `tests/Unit/SurfaceTest.php`).
6. An arch test asserts `RefactorCircus\Keystone` does not use `RefactorCircus\Atrium`, and a test asserts no file under `src/` contains the string `atrium` (case-insensitive), so string references such as `class_exists('RefactorCircus\\Atrium\\Atrium')` are caught too.
7. `README.md` does not mention Atrium, `registerAtriumPlugin()` or a `ui` config key; the provider example and the shared config example show only `registerPolicies()`, `registerMcpServer()`, `registerCortex()`, `loadHistoryRoutes()` and the `authorization`, `policies`, `routes`, `mcp`, `cortex` keys.
8. `CHANGELOG.md` `[Unreleased]` has a `### Breaking` section that names: the removal of `registerAtriumPlugin()`, the end of the `{key}.ui.enabled` convention (packages drop the key from their config), the removal of the built-in `atrium.` surface prefix (a host calls `Surface::route('atrium.', 'atrium')` itself), and that Foundry and Guardian stay on Keystone `^0.1` (v0.1.1) until they migrate. It states the release is v0.2.0.
9. `composer.json` `extra.branch-alias.dev-main` is `0.2.x-dev`.
10. The quality gate passes: `vendor/bin/pest` (including the arch tests), `composer lint:check` and `composer analyse`.

## Design

- **`src/Support/PackageServiceProvider.php`**: delete `registerAtriumPlugin()` and its docblock. Rewrite the class docblock so the helpers read "policies, MCP routes, the Cortex switch" and the config shape ends at `cortex{enabled,server,tools}`. No other method changes; `loadHistoryRoutes()`, `registerCortex()`, `registerMcpServer()`, `registerPolicies()`, `registerNotFoundResponses()` keep their signatures and behaviour.
- **`src/Support/Surface.php`**: drop the line `$prefixes['atrium.'] = $this->routes['atrium.'] ?? 'atrium';` in `prefixes()`. Rewrite the class docblock: surfaces are `http`, `mcp`, `cortex`, `cli`, `code`, or one a package or host names for its routes with `route()` (`scim`, `web`, a dashboard's own). `route()`'s signature is unchanged; it is the documented way for a host to name a surface.
- **Doc comments naming Atrium elsewhere in `src/`** (`Database/Tables.php`, `Testing/Arch.php`, `Packages/PackageRegistry.php`, `Audit/Contracts/AuditTrail.php`): replace the examples with neutral ones (`acme.tables` / `acme_widgets`; `Acme\Shop` vs `Acme\ShopPlus`; "every package and any dashboard"). Comment-only changes; no behaviour.
- **Tests**: `tests/Unit/SurfaceTest.php` replaces the `atrium.dashboard` → `atrium` expectation with criteria 4 and 5. `tests/ArchTest.php` gains the namespace rule and the `src/` string scan (criterion 6). The `method_exists` check (criterion 1) goes in `tests/Feature/PackageTest.php`, beside the provider wiring tests.
- **Docs and release**: README and CHANGELOG as in criteria 7–8; `composer.json` branch alias moves to `0.2.x-dev` so dependents constrained to `^0.1` never resolve a `main` without the hook.
- **Standards**: this follows `architecture/dependencies` more strictly than before (no optional integration with a suite package at all for Atrium). `architecture/service-providers` in refactor-circus/standards still lists `registerAtriumPlugin()` among the base helpers; that file lives in the Standards package and is fixed by its own spec there (see Risks), so this job records the drift in `docs/agent/standards-feedback.md` rather than editing vendor files.

## Out of scope

- The Cortex integration (`CortexIntegration`, `registerCortex()`, the `cortex` surface): Keystone keeps it; dropping it is a separate decision.
- Any change in Atrium, Foundry, Guardian or the seven package specs that wait on this one; each package removes its own `registerAtriumPlugin()` call and `ui` config key in its own spec.
- Editing `refactor-circus/standards` (`architecture/service-providers.md`).
- Tagging or publishing v0.2.0; the job leaves the CHANGELOG entry under `[Unreleased]` for the release step.
- Historic specs under `docs/agent/specs/` and `CLAUDE.md`, which mention Atrium as context, not as code.

## Risks and open questions

- **Breaking for every caller.** Cortex, Impex, Keen, Polycart, Showroom, Foundry and Guardian call `registerAtriumPlugin()`; on Keystone v0.2.0 they fail with an undefined method until they drop the call. Mitigation: the minor bump plus `^0.1` constraints keep them on v0.1.1; the branch alias bump keeps `dev-main` consumers on `^0.1` from resolving it.
- **Audit surface values change for Atrium routes.** Activity through `atrium.*` routes is recorded as `http`/`code` rather than `atrium` unless Atrium registers `Surface::route('atrium.', 'atrium')`. Atrium v2's spec must do so if it wants the label kept. Recorded in the CHANGELOG.
- **Standards drift.** `architecture/service-providers.md` in refactor-circus/standards names a method that no longer exists; needs a follow-up spec in Standards.
