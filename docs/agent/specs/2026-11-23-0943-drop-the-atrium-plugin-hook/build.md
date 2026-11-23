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
