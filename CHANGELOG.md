# Changelog

## [Unreleased](https://github.com/Refactor-Circus/Keystone/commits/main)

### Breaking

- Renamed Foundation to Keystone and moved to the Refactor Circus organisation: the package is now `refactor-circus/keystone` with the PHP namespace `RefactorCircus\Keystone` (it was `jayi/foundation` and `JayI\Foundation`), and its provider is `KeystoneServiceProvider`. Update `composer.json` requirements and `use` statements. Old class names are not kept as aliases, so stored values written under them - polymorphic `*_type` columns, audit subjects, Pennant feature names - need updating to the new names.

### Added

- The shared runtime for the Refactor Circus suite: the `Action` base, the action and model event contracts, `DispatchesModelEvents`, the package registry, base service providers, HTTP and MCP request bases, the MCP tool and server bases, the Cortex integration, `PackageException`, the `Policy` base, and `Surface`.
- `Http\Resources\JsonResource` and `ResourceCollection`: `toStructuredResponse()` turns a resource or a list of them into an MCP tool's structured response with the same payload as HTTP - the resource under `data`, `with()` and `additional()` beside it, and `meta` (current page, last page, per page, total) for a paginated list. An empty list still ships as `{data: []}`. Resources describe their fields in `schema()`, and `structuredSchema()` / `collectionSchema()` give a tool's `outputSchema()`; `Testing\StructuredSchema::violations()` checks a tool's output against it.
- `Package::audits()`: a package audits only while refactor-circus/keen is installed and its `{key}.audit.enabled` config key allows it. The history route answers 404 and the history tool is left off the package's MCP server and Cortex while it does not.
- Testing helpers for every package: `Testing\McpTools::call($server, $tool, $arguments)` calls a tool the way a client does (through tool search when the server has it) and fails when a structured answer breaks the tool's output schema; `Testing\Parity::gaps()` lists Actions a surface cannot reach; `Testing\Arch` gives the domain namespaces and classes, Actions missing their starting or finished event, and Data classes without rules, `Arch::modelEventGaps()` (models missing an event for an Eloquent hook) and `Arch::writesOutsideTransactions()` (Actions that write without a `transaction(`, optionally counting a package's own write calls), `Arch::modelsWithoutPolicies()` (models without a policy in `{key}.policies`) and `Arch::literalTableNames()` (files that name a table as a string rather than through `Tables`).
- `Database\Tables`: a package's table names from its `{key}.tables` config, defaults kept for tables a published config leaves out. A package extends it with its `CONFIG` key and `DEFAULTS`, and its models, migrations, rules and joins ask `Tables::name('dashboards')`.
- `PackageServiceProvider::registerNotFoundResponses()`: a missing record on the package's own routes, asked for as JSON, answers a plain `{"message": "Not found."}` 404 rather than Laravel's message naming the model class. Pages (HTML requests) keep Laravel's 404.
- `Pagination\Pagination`: `pages()`, `simple()` and `cursor()` give a list's validation rules, its MCP tool input schema and its answer's `meta` from one definition, so they cannot disagree. `ResourceCollection` answers simple and cursor paginators with matching `meta` (`has_more`; `next_cursor`/`prev_cursor`), and `collectionSchema()` takes the list's pagination. The history endpoint and tool use `Pagination::cursor(default: 25)`.
- `DispatchesModelEvents` builds and dispatches a model's `retrieved` event only while something listens for it (the event or `ModelLifecycleEvent`), or under `Event::fake()`; it fired for every model loaded.
- `Data` reads each class's constructor once per process rather than on every `from()`.
- `Data\Data` and `Data\Missing`: Action input as typed, immutable objects that validate themselves (`from()` checks the subclass's `rules()`, hydrates snake_case keys into cast constructor fields, and keeps fields left out as `Missing`; `fromValidated()` builds from input a request has already validated). An array field marked `#[Data\Attributes\ListOf(ItemData::class)]` is built item by item (Data or backed enums), and a subclass fills fields that need a lookup, such as a model its input names by id, by overriding `prepare()`.
- `Contracts\UnauditedActionEvent`: marks the events of a step that runs once per row or page of a larger job (an import row, an export page), so audit logs skip them; the Actions the step runs and the job's own start and finish are still recorded.
- `Contracts\ReadActionEvent`: marks the events of an action that only reads, so audit logs skip them whatever their names.
- Audit seams for refactor-circus/keen: `AuditTrail` (bound to `NullAuditTrail` until Keen is installed), `Auditable`, `AuditHooks`, and a history endpoint and MCP tool for every package.

### Changed

- `Http\Requests\Request` refuses a guest with 401 (`AuthenticationException`) rather than 403; a signed-in user without the ability still gets 403.
- `Policy::allowsOn()` takes any `?Authenticatable`, not only Eloquent users, and a null user, so a parent policy that lets guests in decides for its children too.
- A nested `Data` field is built with `fromValidated()` from the parent's validated input rather than validated again with its own rules, so a request validates once and lookup rules (`exists`) run once. List the nested fields' rules under its key in the parent's `rules()` (`address.city`; `required_with:address` when the field is optional).
- The Cortex integration resolves its server's tool names once, on the first agent tool call, rather than building every tool of every package on each call to check whether it owns it.
- `PackageRegistry::for()` remembers the package each class belongs to (cleared when a package registers), and `Surface` builds its sorted route prefixes once (rebuilt when `route()` names one or a package registers), so the lookups base classes make on every request, tool and audit entry no longer scan.
- Who counts as a guest is `Request::isGuest()` (no Laravel user by default); a request whose callers identify another way overrides it to get 403 instead of 401.

### Fixed

- A Data field typed `BackedEnum|string` (or the abstract `Data`) no longer fails: the interface is not cast to, so the value falls through to the next type.

- Requires PHP 8.5 or later.
