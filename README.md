<p align="center">
    <img src="art/icon.png" width="160" alt="Keystone icon">
</p>

# Keystone

The shared, headless runtime for the Refactor Circus package suite.

Keystone has no routes, views or config of its own. It holds the base classes and contracts every package used to copy, so each package works the same way and works without any dashboard.

## Installation

Packages of the suite require it; an application never needs to install it directly.

```bash
composer require refactor-circus/keystone
```

## Describing a package

A package's main service provider extends `PackageServiceProvider`, describes the package once, and registers it before any domain provider:

```php
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Support\PackageServiceProvider;

final class ShowroomServiceProvider extends PackageServiceProvider
{
    protected function definition(): Package
    {
        return Package::make('showroom', 'RefactorCircus\Showroom')
            ->label('Showroom')
            ->server(ShowroomServer::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/showroom.php', 'showroom');
        $this->registerPackage();
        $this->app->register(DomainServiceProvider::class);
    }

    public function boot(): void
    {
        $this->registerPolicies();      // showroom.policies
        $this->registerMcpServer();     // showroom.mcp.web / showroom.mcp.local
        $this->registerCortex();        // showroom.cortex.*, when Cortex is installed
        $this->loadHistoryRoutes();     // GET {prefix}/history
    }
}
```

Every base class finds its package by namespace, through the `PackageRegistry`, so nothing else is configured per class. The helpers read a config file every package shapes the same way:

```php
return [
    'authorization' => false,
    'policies' => [/* Model::class => Policy::class */],
    'routes' => ['enabled' => true, 'prefix' => 'api/showroom', 'middleware' => ['api']],
    'mcp' => [
        'web' => ['enabled' => false, 'route' => 'mcp/showroom', 'middleware' => []],
        'local' => ['enabled' => false, 'handle' => 'showroom'],
    ],
    'cortex' => ['enabled' => true, 'server' => 'showroom', 'tools' => null, 'tags' => null],
];
```

## What it provides

| Class | Purpose |
|---|---|
| `Actions\Action` | `execute()` → `protected handle()` base for mutating logic |
| `Data\Data`, `Data\Missing` | An Action's input as a typed, immutable object that validates itself (see below) |
| `Contracts\ActionStartingEvent`, `ActionFinishedEvent`, `ModelLifecycleEvent` | The event contracts every package's events implement. Listen to one to hear every package. |
| `Contracts\ReadActionEvent` | Marks the events of an action that only reads (checking, searching); audit logs skip them whatever their names |
| `Models\Concerns\DispatchesModelEvents` | Maps each Eloquent hook to `Domains\{Domain}\Events\{Entity}{Hook}Event` |
| `Support\ServiceProvider` | Domain provider base: `loadApiRoutesFrom()`, `keepMorphAliases()` |
| `Support\PackageServiceProvider` | Main provider base, as above |
| `Auth\Authorizer` | The `authorization` switch, Gate checks on models (`can()`) and named abilities in a scope (`allows()`) |
| `Authorization\Contracts\Permissions`, `Roles`, `Scope` | The authorization contract a role store implements, with a Gate fallback (see below) |
| `Http\Requests\Request` | FormRequest with `persist()`, `actor()`, `allows()`, `allowsEach()`; guests get 401, others 403 |
| `Http\Resources\JsonResource`, `Http\Resources\ResourceCollection` | API resources that answer MCP with the HTTP payload: `->toStructuredResponse()` gives `{data, meta?, ...additional}`. Each resource describes its fields in `schema()`, and a tool declares its output with `XResource::structuredSchema()` / `collectionSchema()` |
| `Pagination\Pagination` | How a list pages - `pages()` (with a total), `simple()` (without) or `cursor()` - as one definition: its validation `rules()`, the tool input `schema()`, and the `meta()` its answer carries. `JsonResource::collectionSchema($schema, $pagination)` takes it |
| `Testing\McpTools`, `Testing\Parity`, `Testing\Arch` | Test helpers: call a tool as a client does with its output schema checked, list Actions a surface cannot reach, and the architecture checks every package runs |
| `Testing\StructuredSchema` | `violations($tool->toArray()['outputSchema'], $structuredContent)` lists where a tool's output breaks its declared schema, including fields it doesn't declare |
| `Mcp\Requests\Request`, `Mcp\Tool`, `Mcp\Server` | The same pattern for MCP, with Cortex overrides |
| `Cortex\CortexIntegration` | Registers a package's server and tools with Cortex when it is installed |
| `Exceptions\PackageException` | Base for a package's rule-broken exceptions (409 JSON, MCP error text) |
| `Policies\Policy` | `allowsOn()` defers a child model to its parent's policy, for any signed-in user or a guest; `allowsAbility()` asks the authorization contract |
| `Support\Surface` | Which surface a call came through: `http`, `mcp`, `cortex`, `cli`, `code`, or one a package or host names for its routes with `route()` |

## Action input

Actions take their input as a `Data` object rather than an array. A subclass declares its fields as promoted constructor properties and its validation as a static `rules()` method, which may take context:

```php
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Data\Missing;

final readonly class UpdateDashboardData extends Data
{
    public function __construct(
        public string|Missing $name = new Missing,
        public int|Missing $columns = new Missing,
    ) {}

    public static function rules(?DashboardModel $dashboard = null): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'columns' => ['sometimes', 'integer', 'between:1,4'],
        ];
    }
}

$data = UpdateDashboardData::from($request->all(), $dashboard); // validates, or throws ValidationException
app(UpdateDashboardAction::class)->execute($dashboard, $data);
```

- `from()` validates untrusted input against `rules()` and fills each field from its snake_case key (`per_page` → `$perPage`), cast to the field's type: int, float, bool, string, backed enums, dates and nested `Data`. Keys without a rule are dropped.
- A nested `Data` field is built from the parent's validated input without validating again, so the parent's rules cover it: list the nested fields' rules under its key (`address.city`), with `required_with:address` in place of `required` when the field is optional. Input is checked once, and every error comes back together.
- Optional fields of a partial update default to `Missing`, so a field left out stays apart from one set to null: `$data->has('name')`, or `$data->name instanceof Missing`.
- A request that validates first returns the Data's rules from its own `rules()` and builds with `fromValidated($request->validated())`, which fills and casts without checking again, so every error comes back in one response:

  ```php
  public function rules(): array
  {
      return UpdateDashboardData::rules($this->dashboard());
  }

  public function persist(): JsonResponse
  {
      $dashboard = app(UpdateDashboardAction::class)->execute($this->dashboard(), UpdateDashboardData::fromValidated($this->validated()));
      // ...
  }
  ```
- An array field of Data or backed enums is marked `#[ListOf(LineData::class)]` (`Data\Attributes\ListOf`) and built item by item; the parent's rules validate the items (`lines.*.quantity`).
- A field that needs a lookup - a model the input names by id, say - is filled by overriding `prepare()`, which gets the validated input before the fields are filled:

  ```php
  protected static function prepare(array $validated): array
  {
      return [...$validated, 'product' => ProductModel::query()->findOrFail($validated['product_id'])];
  }
  ```
- `toArray()` returns the given fields under their snake_case keys.
- `new` builds one from values the code itself vouches for, without validating.

## Tables

Every table a package creates is named in its `{key}.tables` config, so an application can rename one before migrating. The package extends `Database\Tables` with its config key and defaults:

```php
final class Tables extends \RefactorCircus\Keystone\Database\Tables
{
    protected const string CONFIG = 'acme.tables';

    public const array DEFAULTS = [
        'widgets' => 'acme_widgets',
    ];
}
```

Models return `Tables::name('widgets')` from `getTable()`, and migrations, `Rule::unique`/`exists` and joins use it too, never a literal name. A published config that lists only some tables keeps the defaults for the rest. `Testing\Arch::literalTableNames()` finds literal names left in migrations or code, and `Arch::modelsWithoutPolicies()` finds models missing from `{key}.policies`.

### Database connection

A package can keep its tables on their own connection. `Tables::connection()` reads `{key}.database.connection` (null means the app's default); models, migrations, queries and rules all go through it:

```php
public function getConnectionName(): ?string // on each model
{
    return $this->connection ?? Tables::connection();
}

public function getConnection(): ?string // on each migration
{
    return Tables::connection();
}

Tables::db()->transaction(fn () => ...);           // not DB::transaction()
Rule::exists(Tables::qualified('widgets'), 'id'); // "{connection}.{table}"
```

`Arch::defaultConnectionUses()` finds code still reaching the default connection through `DB::` or an injected connection. Joins and `whereHas` against host models (users, teams) only work while both sit on the same database.

## Audit seams

Keystone defines how packages talk to an audit log without depending on one. [refactor-circus/keen](https://github.com/Refactor-Circus/Keen) is the audit log; until it is installed, `AuditTrail` is bound to `NullAuditTrail`.

- `Audit\Contracts\AuditTrail`: read entries with an `AuditFilter`, get an `AuditPage` of `AuditEntry` values.
- `Audit\Contracts\Auditable`: an optional interface for action events that name their subject and context.
- `Audit\AuditHooks`: what a package teaches the log about its models (labels, snapshot extras, subject and scope pickers, context, redacted fields).
- `Package::authorizeHistory()` lets a package that does not authorize through policies decide who reads its history.
- `Package::audits()`: whether a package audits - only while Keen is installed, and unless its `{key}.audit.enabled` config key is false. A package registers its `AuditHooks` and anything else audit-related only when it does.
- `Audit\History` plus `loadHistoryRoutes()` and `Mcp\Tools\ListHistoryTool`: every package serves its own history over HTTP and MCP while it audits. Otherwise the route answers 404, and the tool is not offered on the package's MCP server or to Cortex.

## Authorization

Packages never ask each other who may do what. They ask a contract Keystone defines, and a role store (or nothing) answers it:

- `Authorization\Contracts\Permissions`: `allows($user, $ability, $scope = null)`, `scopesWith($user, $ability, $scopeType)` (the scope keys a list is limited to; null means not limited), `abilities($user, $scope = null)` (null means the implementation cannot list them), `isSuperAdmin($user)` and `forget($user = null)`. `Permissions::SUPER_ADMIN` is `super-admin`.
- `Authorization\Contracts\Roles`: `available()`, `exists()`, `assign()`, `revoke()` (roles held in exactly that scope), `revokeEverywhere()`, `of()` and `holders($ability)`.
- `Authorization\Contracts\Scope`: implemented by the Eloquent models permissions are scoped to. `parentScope()` names the scope whose grants also apply (a team's organization) and `grantsByItself($user, $ability)` is the scope's own rule (an organization's owner). Every implementation honours both; `ScopeChain::of($scope)` walks a scope and its parents.

A `$scope` is null (global), a `Scope`, or any Eloquent model, which then has no parents.

### Defaults

With no role store installed, Keystone binds `Permissions` to `GatePermissions` and `Roles` to `NullRoles`:

- `GatePermissions` asks the host's Gate: an ability defined with `Gate::define()` (or a `Gate::before()` callback) decides, an undefined one is refused, and so is a guest unless the host's gate lets guests in. A `Scope` that grants the ability by itself, or any of its parents, allows it first. `isSuperAdmin()` asks the Gate for `super-admin`, `scopesWith()` is null when the ability is allowed with no scope and `[]` otherwise, and `abilities()` is null. A `Gate::before()` callback that asks `allows()` again for the same check gets false rather than a loop.
- `NullRoles` stores nothing: `available()`, `exists()` and `assign()` are false, `of()` and `holders()` are empty.

### A role store

A role store replaces the defaults by binding the contracts in its provider's `register()`. Keystone binds with `singletonIf()`, so `singleton()` wins whichever provider registers first; use `scoped()` when the implementation memoizes per request:

```php
public function register(): void
{
    $this->app->scoped(Permissions::class, RolePermissions::class);
    $this->app->singleton(Roles::class, RoleStore::class);
}
```

A package that adds a rule to every check, without being the store, decorates the binding with `extend()`. Laravel keeps the decorator when a role store rebinds later:

```php
$this->app->extend(Permissions::class, fn (Permissions $permissions) => new BlockWhileImpersonating($permissions));
```

### Abilities

Packages describe the abilities they check from their provider's `boot()`, so a role store can list them and seed its default roles:

```php
app(Abilities::class)
    ->define('acme.widgets.view', 'View widgets', grantTo: ['admin', 'member'])
    ->define('acme.widgets.edit', 'Edit widgets', grantTo: ['admin']);
```

`Abilities::define()` refuses a name defined twice, an empty one or one with whitespace. `has()` and `all()` (sorted by ability) read the registry.

### Checking

`Authorizer::can($user, $ability, $model)` asks the Gate about a model, so the package's policy decides; it is unchanged. `Authorizer::allows($user, $ability, $scope)` asks the bound `Permissions` about a named ability, and like `can()` is true while the package's `authorization` key is off. A policy method asks the contract through `Policy::allowsAbility($user, $ability, $scope)`, for guests too:

```php
final class WidgetPolicy extends Policy
{
    public function update(?Authenticatable $user, Widget $widget): bool
    {
        return $this->allowsAbility($user, 'acme.widgets.edit', $widget->organization);
    }
}
```

So `can()` reaches the policy and the policy reaches the contract; neither helper calls the other, and `allowsOn()` keeps deferring a child model to its parent's policy.

### Testing

`Testing\FakePermissions` and `Testing\FakeRoles` keep grants and roles in memory. `install()` binds each in place of the defaults, and they work together in either order:

```php
FakePermissions::install()
    ->grant($user, 'acme.widgets.view')
    ->grant($user, 'acme.widgets.edit', $organization)
    ->superAdmin($admin);

FakeRoles::install()
    ->define('owner', ['acme.teams.manage'], Organization::class)
    ->assign($user, 'owner', $organization);
```

A global grant or role applies in every scope, one in a scope applies in its child scopes, `grantsByItself()` is honoured, and a guest may do nothing. `FakePermissions` never asks the Gate.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
