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

## Addendum 2026-11-23: authorization contract

Roles and permissions move from Roster to Guardian (user decision, 2026-11-23). Packages must not name each other, so the seam lives here: Keystone defines a small authorization contract, binds a Laravel Gate-backed default, and lets a role store (Guardian) override the binding. Roster, Guardian's SSO/SCIM and any other package ask the contract; without a role store, the host's Gate abilities and policies decide. The contract's methods come from Roster's real usages (access checks, organization-limited lists, impersonation's escalation guard, super-admin checks, default member role on join, revoking on leave/delete, approvers, member import/export).

### Acceptance criteria

11. `RefactorCircus\Keystone\Authorization\Contracts\Permissions` is an interface with exactly: `const string SUPER_ADMIN = 'super-admin'`; `allows(?Authenticatable $user, string $ability, mixed $scope = null): bool`; `scopesWith(?Authenticatable $user, string $ability, string $scopeType): ?array` (`@return list<int|string>|null`, null meaning "not limited to any scope"); `abilities(?Authenticatable $user, mixed $scope = null): ?array` (`@return list<string>|null`, null meaning "this implementation cannot list them"); `isSuperAdmin(?Authenticatable $user): bool`; `forget(?Authenticatable $user = null): void`.
12. `RefactorCircus\Keystone\Authorization\Contracts\Roles` is an interface with exactly: `available(): bool`; `exists(string $role, ?Model $scope = null): bool`; `assign(Authenticatable $user, string $role, ?Model $scope = null): bool`; `revoke(Authenticatable $user, ?Model $scope = null): void` (every role held in exactly that scope; null means global roles); `revokeEverywhere(Authenticatable $user): void`; `of(Authenticatable $user, ?Model $scope = null): array` (`list<string>` role slugs held in exactly that scope, sorted); `holders(string $ability): array` (`list<int|string>` keys of users holding the ability globally, super-admins included).
13. `RefactorCircus\Keystone\Authorization\Contracts\Scope` is an interface for Eloquent models that permissions are scoped to, with exactly `parentScope(): ?Scope` (the scope whose grants also apply here, such as a team's organization) and `grantsByItself(Authenticatable $user, string $ability): bool` (the scope's own rule, such as an organization owner's, independent of roles).
14. After boot with no other package, the container resolves `Permissions` to `RefactorCircus\Keystone\Authorization\GatePermissions`, `Roles` to `RefactorCircus\Keystone\Authorization\NullRoles` and `RefactorCircus\Keystone\Authorization\Abilities` to one shared instance. A fixture package that binds its own `Permissions` and `Roles` in `register()` wins whether its provider registers before or after Keystone's. A decorator added with `$app->extend(Permissions::class, ...)` before the fixture rebinds still wraps the fixture's implementation.
15. `GatePermissions::allows($user, $ability, $scope)` answers `Gate::forUser($user)->allows($ability, $scope === null ? [] : [$scope])`: an ability the host defines with `Gate::define()` is honoured, an undefined ability is refused, and a guest is refused unless the host's gate accepts guests.
16. Before asking the Gate, `GatePermissions::allows()` walks the scope chain: when `$scope` is a `Scope` and it or any `parentScope()` up the chain answers `grantsByItself($user, $ability)` true for a signed-in user, the answer is true. `RefactorCircus\Keystone\Authorization\ScopeChain::of(?Scope $scope): list<Scope>` returns the scope and its parents, nearest first, and stops at a repeated scope.
17. `GatePermissions` does not recurse: when a `Gate::before()` callback calls `allows()` for the same user, ability and scope while that check is running, the inner call answers false and the outer call completes.
18. `GatePermissions::scopesWith()` is null when `allows($user, $ability)` (no scope) is true, otherwise `[]`. `abilities()` is null. `isSuperAdmin()` is `Gate::forUser($user)->allows(Permissions::SUPER_ADMIN)` for a signed-in user and false for a guest. `forget()` does nothing.
19. `NullRoles`: `available()` and `exists()` are false, `assign()` is false and stores nothing, `of()` and `holders()` are `[]`, and `revoke()` and `revokeEverywhere()` do nothing. None of them queries the database.
20. `Abilities` is a registry packages fill from their providers' `boot()`: `define(string $ability, string $description, array $grantTo = []): self` (`$grantTo` lists default role slugs a role store may grant it to when it creates them), `has(string $ability): bool`, `all(): array` (`array<string, array{description: string, grantTo: list<string>}>`, sorted by ability). Defining a name twice, an empty name, or a name with whitespace throws `InvalidArgumentException`.
21. `Authorizer::allows(?Authenticatable $user, string $ability, mixed $scope = null): bool` is true when the package's `authorization` is off, and otherwise the bound `Permissions::allows()`. `Authorizer::can()` is unchanged.
22. `Policy` gains `protected function allowsAbility(?Authenticatable $user, string $ability, mixed $scope = null): bool`, which asks the bound `Permissions` (guests included). `allowsOn()` is unchanged.
23. `RefactorCircus\Keystone\Testing\FakePermissions` and `FakeRoles` implement the contracts in memory for package test suites: `FakePermissions::install(): self` binds itself, `grant($user, $ability, $scope = null)`, `superAdmin($user)`, and it honours `Scope::grantsByItself()` and `parentScope()` like a real store; `FakeRoles::install(): self`, `define(string $role, array $abilities, ?string $scopeType = null)`, and `assign()`/`of()`/`holders()` that work together with an installed `FakePermissions`.
24. `tests/ArchTest.php`: everything in `RefactorCircus\Keystone\Authorization\Contracts` is an interface; `GatePermissions`, `NullRoles`, `Abilities` and `ScopeChain` are final; `RefactorCircus\Keystone\Authorization` uses no `RefactorCircus\Roster` or `RefactorCircus\Guardian` class, and the existing `src/` scan also finds no `roster` or `guardian` string in `src/Authorization`.
25. `README.md` has an "Authorization" section: the three contracts, the defaults (Gate fallback, no roles), how a role store overrides the binding (`singleton()`/`scoped()` in `register()`), decorating with `extend()`, `Abilities::define()`, how `Authorizer::allows()` and `Policy::allowsAbility()` compose with `Authorizer::can()`, and the testing fakes. `CHANGELOG.md` `[Unreleased]` has an `### Added` entry for all of it.
26. The quality gate passes: `vendor/bin/pest`, `composer lint:check` and `composer analyse`.

### Design

All new code is under `src/Authorization/`:

- `Contracts/Permissions.php`, `Contracts/Roles.php`, `Contracts/Scope.php`: interfaces as in criteria 11-13, with docblocks stating the meaning of `null` returns and that `$scope` is null (global), a `Scope`, or any Eloquent model (no hierarchy).
- `GatePermissions.php` (final): constructor takes `Illuminate\Contracts\Auth\Access\Gate`. `allows()`: null user and no guest gate falls through to the Gate as is; a `Scope` chain is walked first (criterion 16); a private `array<string, true> $running` keyed by user, ability and scope guards re-entry (criterion 17), cleared in a `finally`. `scopesWith()`, `abilities()`, `isSuperAdmin()`, `forget()` as criterion 18.
- `NullRoles.php` (final): criterion 19.
- `Abilities.php` (final): criterion 20. Code-defined state only, so one instance serves the process (`singletonIf`).
- `ScopeChain.php` (final, static `of()`): shared by `GatePermissions`, the fakes and any role store.
- `KeystoneServiceProvider::register()`: `singletonIf(Abilities::class)`, `singletonIf(Permissions::class, GatePermissions::class)`, `singletonIf(Roles::class, NullRoles::class)`, the same `*If` pattern as `AuditTrail`/`NullAuditTrail`. A role store binds with `singleton()` or `scoped()` (per request when it memoizes) and so replaces the default in either provider order.
- `src/Auth/Authorizer.php`: add `allows()` (criterion 21). `can()` still asks the Gate with a model subject, so model policies keep working; `allows()` is for named abilities in a scope, which a policy method may in turn call through `Policy::allowsAbility()`. Neither calls the other, so they compose without loops.
- `src/Policies/Policy.php`: add `allowsAbility()` (criterion 22). The name avoids `permits()`, which Roster's own `Policy` subclass already defines with a narrower signature.
- `src/Testing/FakePermissions.php`, `src/Testing/FakeRoles.php` (final): criterion 23.

### Out of scope

- Any role store, table or permission catalogue persistence (Guardian's spec).
- Changing `Authorizer::can()`, `Authorizer::enabled()` or how `policies` config is registered.
- A "current scope" (context) concept: packages that have one (Roster's current organization) keep it.

### Risks

- **Every package's tests run against Gate fallback by default.** Packages that relied on Roster's roles must grant abilities in tests through `FakePermissions` or `Gate::define()`.
- **Gate abilities named like permissions.** Laravel only camel-cases abilities containing `-` when mapping to policy methods, so a dotted ability such as `roster.users.view` never reaches a model policy method by accident; the fallback asks only `Gate::define()`d abilities and `before` callbacks for it. Criterion 17 covers the one loop a `before` callback could cause.
