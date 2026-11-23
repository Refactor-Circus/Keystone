# Build: Drop the Atrium plugin hook

## Group 1

Remove Atrium from the runtime (criteria 1, 2, 3, 4, 5, 6, 10).

### Files

- `src/Support/PackageServiceProvider.php`: `registerAtriumPlugin()` deleted; the class docblock now names policies, MCP routes and the Cortex switch, and the config shape ends at `cortex{enabled,server,tools}`. `loadHistoryRoutes()` and the other methods are unchanged.
- `src/Support/Surface.php`: the built-in `atrium.` prefix is gone from `prefixes()`; the class docblock lists `http`, `mcp`, `cortex`, `cli`, `code`, or one a package or host names with `route()`.
- `src/Database/Tables.php`, `src/Testing/Arch.php`, `src/Packages/PackageRegistry.php`, `src/Audit/Contracts/AuditTrail.php`: doc-comment examples changed to neutral ones (`acme.tables`, `acme_widgets`, `Acme\Shop` vs `Acme\ShopPlus`, "every package and any dashboard"). These are comment-only changes.
- `tests/Feature/PackageTest.php`: asserts `PackageServiceProvider` has no `registerAtriumPlugin()`.
- `tests/Unit/SurfaceTest.php`: an unregistered `atrium.dashboard` resolves to `cli`; after `route('atrium.', 'atrium')` it resolves to `atrium`.
- `tests/ArchTest.php`: `RefactorCircus\Keystone` does not use `RefactorCircus\Atrium`; a scan of every file under `src/` finds no `atrium` (case-insensitive), no `ui.enabled` and no `configKey('ui`.

### Tests

- `composer lint:check` and `composer analyse` passed (re-run on the retry, which found the group complete and changed no code). The quality gate runs `vendor/bin/pest` after the build. `tests/Feature/HistoryTest.php` is unmodified.

### Deviations

- None.

## Group 2

Docs, changelog and branch alias (criteria 7, 8, 9, 10).

### Files

- `tests/Feature/DocumentationTest.php` (new): README has no `atrium` (case-insensitive), no `registerAtriumPlugin` and no `'ui' =>`; its examples still show `registerPolicies()`, `registerMcpServer()`, `registerCortex()`, `loadHistoryRoutes()` and the `authorization`, `policies`, `routes`, `mcp`, `cortex` keys. The CHANGELOG `[Unreleased]` section has `### Breaking` naming `registerAtriumPlugin()`, `ui.enabled`, `Surface::route('atrium.', 'atrium')`, Foundry, Guardian and v0.2.0. `composer.json` `extra.branch-alias.dev-main` is `0.2.x-dev`.
- `README.md`: the intro names only "the Refactor Circus package suite" and says packages work "without any dashboard". The `registerAtriumPlugin()` line is gone from the provider example and `'ui' => ['enabled' => true]` from the config example. The `Support\Surface` row lists `http`, `mcp`, `cortex`, `cli`, `code`, or one named with `route()`. The Tables example also named Atrium, so it now uses `acme.tables` / `acme_widgets` / `Tables::name('widgets')` to match the `src/` doc comments.
- `CHANGELOG.md`: `### Breaking` under `[Unreleased]` (released as v0.2.0) covers the removed `registerAtriumPlugin()`, the end of `{key}.ui.enabled`, the removed `atrium.` surface prefix (the host calls `Surface::route('atrium.', 'atrium')`), and that Foundry and Guardian stay on `^0.1` (v0.1.1).
- `composer.json`: `extra.branch-alias.dev-main` is `0.2.x-dev`; `composer validate` reports it valid.
- `docs/agent/standards-feedback.md`: a row for `architecture/service-providers`, which still lists `registerAtriumPlugin()` and needs a Standards spec (D-004).

### Tests

- `tests/Feature/DocumentationTest.php` passes (4 tests) and `pint --test` passes on it. PHPStan only analyses `src` and `tests/Fixtures`, so this group changes nothing it checks. The quality gate runs the full `vendor/bin/pest`, `composer lint:check` and `composer analyse` after the build.

### Deviations

- None. The README Tables example change (from `atrium.tables` to `acme.tables`) goes beyond the four README edits task 2.2 lists, but criterion 7 requires it.
