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
- On the retry the group was already complete, so no code changed. `composer lint:check`, `composer analyse` and `composer validate` passed again.

### Deviations

- None. The README Tables example change (from `atrium.tables` to `acme.tables`) goes beyond the four README edits task 2.2 lists, but criterion 7 requires it.

## Group 3

Authorization contracts, Gate fallback and bindings (criteria 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 24).

### Files

- `src/Authorization/Contracts/Permissions.php`, `Roles.php`, `Scope.php` (new): the interfaces with exactly the methods of criteria 11-13. Docblocks state what a `null` return means (`scopesWith()`: not limited to any scope; `abilities()`: cannot list), that `$scope` is null, a `Scope` or any Eloquent model, and that a role store overrides the binding with `singleton()`/`scoped()` and is decorated with `extend()`.
- `src/Authorization/ScopeChain.php` (new, final): `of()` returns the scope and its parents, nearest first. It stops at a repeated scope, meaning the same instance or the same saved model loaded again (`Model::is()` with a non-null key), so a reloaded parent loop also stops.
- `src/Authorization/GatePermissions.php` (new, final): walks the `ScopeChain` for a signed-in user, then asks `Gate::forUser($user)->allows($ability, $scope === null ? [] : [$scope])`. A private `$running` map keyed by user (class and auth identifier), ability and scope (class and key, or the instance) answers a re-entrant check false, and a `finally` clears it. `scopesWith()` is null or `[]` from the unscoped check, `abilities()` is null, `isSuperAdmin()` is false for a guest and otherwise `allows($user, SUPER_ADMIN)` (the same Gate question, now behind the re-entry guard), and `forget()` does nothing.
- `src/Authorization/NullRoles.php` (new, final): answers false or empty, stores nothing, and never touches the database.
- `src/Authorization/Abilities.php` (new, final): `define()` (chainable), `has()`, `all()` sorted by name. An empty name, a name holding whitespace or a duplicate throws `InvalidArgumentException` naming it.
- `src/KeystoneServiceProvider.php`: `singletonIf()` for `Abilities`, `Permissions` → `GatePermissions` and `Roles` → `NullRoles`. The class docblock says a role store wins in either provider order.
- `tests/Fixtures/Acme/Authorization/` (new): `OrganizationModel` (owner rule, records the abilities it is asked about), `TeamModel` (parent only), `StorePermissions`/`StoreRoles` and `RoleStoreServiceProvider` (a role store binding with `singleton()`), and `DecoratedPermissions` (an `extend()` wrapper).
- `tests/Feature/AuthorizationContractTest.php` (new): default bindings and one shared `Abilities`. A role store registered before or after Keystone wins (on a bare `Application`), and an `extend()` decorator added before the rebind wraps the store. Tests cover `Gate::define()` honoured, undefined refused, the scope passed as the only Gate argument, a guest refused unless the gate takes `?User`, an organization owner allowed on its team and a guest never asked, a re-entrant `Gate::before` answering false with the outer check completing, the guard cleared after the Gate throws, the `scopesWith`/`abilities`/`isSuperAdmin`/`forget` answers, `ScopeChain` order and its stops (same instance, same key reloaded, unsaved models kept apart), and `NullRoles` with a `DB::listen` counter at zero.
- `tests/Unit/AbilitiesTest.php` (new): define/has/all sorted, `grantTo` default, duplicate, empty and whitespace names throw.
- `tests/ArchTest.php`: `Authorization\Contracts` are interfaces; `GatePermissions`, `NullRoles`, `Abilities` and `ScopeChain` are final; `Authorization` uses no `RefactorCircus\Roster` or `RefactorCircus\Guardian`; a scan of `src/Authorization` finds no `roster` or `guardian` string.
- `docs/agent/standards-feedback.md` (outside the group's list): a row for the `errors/exceptions` deviation below.

### Tests

- `composer test:unit`: 130 passed (36 in the new authorization tests). `composer lint:check`, `composer analyse` (level 7, includes `tests/Fixtures`) and `composer test:types` (100%) all pass.

### Deviations

- `errors/exceptions`: `Abilities::define()` throws a bare `InvalidArgumentException`, as criterion 20 requires, rather than a package exception subclass. It is a programmer error raised during boot, like the `LogicException`s Keystone already throws. The row is in `docs/agent/standards-feedback.md`.
