VERDICT: PASS

## Gate

- `composer test` (phpstan, pint, pest): passed, 158 tests, 445 assertions, 100% coverage.
- `composer lint:check`: passed.
- `composer exec ringside check`: passed.

## Criteria

Criteria 8 and 9 (v0.2.0, `0.2.x-dev`) are superseded by decisions D-006 (all suite packages release as v2.0.0, alias `2.0.x-dev`) and D-013 (Guardian converts too); the code follows the decisions.

1. tests/Feature/PackageTest.php "leaves registering a dashboard plugin to the dashboard".
2. tests/ArchTest.php "names no dashboard and reads no ui config in the package source"; docblock rewritten in src/Support/PackageServiceProvider.php.
3. tests/Feature/HistoryTest.php unmodified (not in the diff), passing.
4. tests/Unit/SurfaceTest.php "has no built-in surface for a dashboard route".
5. tests/Unit/SurfaceTest.php "lets a host name its own surface".
6. tests/ArchTest.php arch "the package source never uses Atrium" and the src/ string scan.
7. tests/Feature/DocumentationTest.php "keeps the readme free of the dashboard hook and the ui config key", "shows only the remaining helpers and config keys in the readme examples".
8. tests/Feature/DocumentationTest.php "records the breaking changes of v2.0.0 under unreleased" and "keeps only Foundry on the 0.1 line" (v2.0.0 per D-006).
9. tests/Feature/DocumentationTest.php "aliases the main branch to 2.0.x-dev" (per D-006).
10. Gate above passes.
11. src/Authorization/Contracts/Permissions.php; tests/Feature/AuthorizationContractTest.php (gate fallback tests) and the fakes tests.
12. src/Authorization/Contracts/Roles.php; tests/Feature/AuthorizationFakesTest.php role tests.
13. src/Authorization/Contracts/Scope.php; "allows what a parent scope grants by itself".
14. AuthorizationContractTest "binds the gate fallback, the null role store and one ability registry", "lets a role store registered before/after the shared runtime win", "keeps a decorator added before the role store rebinds".
15. "honours an ability the host defines and refuses an undefined one", "passes the scope to the gate as its only argument, and none without one", "refuses a guest unless the host gate accepts guests".
16. "allows what a parent scope grants by itself", "never asks a scope about a guest", ScopeChain tests ("lists the scope and its parents, nearest first", "stops at a repeated scope").
17. "answers false to a re-entrant check and completes the outer one", "clears the re-entry guard when the gate throws".
18. "answers scopesWith from the unscoped check", "cannot list abilities", "asks the gate whether a signed-in user is a super-admin", "forgets nothing because it remembers nothing".
19. "stores nothing, answers empty and never queries the database".
20. tests/Unit/AbilitiesTest.php (sorted list, has, twice, empty or whitespace).
21. tests/Unit/AuthorizerTest.php Authorizer::allows tests.
22. tests/Unit/AuthorizerTest.php Policy::allowsAbility tests ("asks the bound permissions", "asks about a guest too").
23. tests/Feature/AuthorizationFakesTest.php (FakePermissions and FakeRoles groups).
24. tests/ArchTest.php: contracts are interfaces, defaults are final, no Roster/Guardian use, src/Authorization string scan.
25. tests/Feature/DocumentationTest.php "documents the authorization contract in the readme", "records the authorization contract as added under unreleased".
26. Gate above passes.

## Findings

None.
