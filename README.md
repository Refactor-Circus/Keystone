<p align="center">
    <img src="art/icon.png" width="160" alt="Keystone icon">
</p>

# Keystone

The shared, headless runtime for the Refactor Circus package suite (Atrium, Cortex, Impex, Keen, PennantPlus, Polycart, Roster, Showroom).

Keystone has no routes, views or config of its own. It holds the base classes and contracts every package used to copy, so each package works the same way and works without the Atrium dashboard.

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
        $this->registerAtriumPlugin(ShowroomPlugin::class); // showroom.ui.enabled, when Atrium is installed
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
    'ui' => ['enabled' => true],
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
| `Auth\Authorizer` | The `authorization` switch and Gate checks |
| `Http\Requests\Request` | FormRequest with `persist()`, `actor()`, `allows()`, `allowsEach()`; guests get 401, others 403 |
| `Http\Resources\JsonResource`, `Http\Resources\ResourceCollection` | API resources that answer MCP with the HTTP payload: `->toStructuredResponse()` gives `{data, meta?, ...additional}`. Each resource describes its fields in `schema()`, and a tool declares its output with `XResource::structuredSchema()` / `collectionSchema()` |
| `Pagination\Pagination` | How a list pages - `pages()` (with a total), `simple()` (without) or `cursor()` - as one definition: its validation `rules()`, the tool input `schema()`, and the `meta()` its answer carries. `JsonResource::collectionSchema($schema, $pagination)` takes it |
| `Testing\McpTools`, `Testing\Parity`, `Testing\Arch` | Test helpers: call a tool as a client does with its output schema checked, list Actions a surface cannot reach, and the architecture checks every package runs |
| `Testing\StructuredSchema` | `violations($tool->toArray()['outputSchema'], $structuredContent)` lists where a tool's output breaks its declared schema, including fields it doesn't declare |
| `Mcp\Requests\Request`, `Mcp\Tool`, `Mcp\Server` | The same pattern for MCP, with Cortex overrides |
| `Cortex\CortexIntegration` | Registers a package's server and tools with Cortex when it is installed |
| `Exceptions\PackageException` | Base for a package's rule-broken exceptions (409 JSON, MCP error text) |
| `Policies\Policy` | `allowsOn()` defers a child model to its parent's policy, for any signed-in user or a guest |
| `Support\Surface` | Which surface a call came through: `atrium`, `http`, `mcp`, `cortex`, `cli`, `code` |

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
    protected const string CONFIG = 'atrium.tables';

    public const array DEFAULTS = [
        'dashboards' => 'atrium_dashboards',
    ];
}
```

Models return `Tables::name('dashboards')` from `getTable()`, and migrations, `Rule::unique`/`exists` and joins use it too, never a literal name. A published config that lists only some tables keeps the defaults for the rest. `Testing\Arch::literalTableNames()` finds literal names left in migrations or code, and `Arch::modelsWithoutPolicies()` finds models missing from `{key}.policies`.

## Audit seams

Keystone defines how packages talk to an audit log without depending on one. [refactor-circus/keen](https://github.com/Refactor-Circus/Keen) is the audit log; until it is installed, `AuditTrail` is bound to `NullAuditTrail`.

- `Audit\Contracts\AuditTrail`: read entries with an `AuditFilter`, get an `AuditPage` of `AuditEntry` values.
- `Audit\Contracts\Auditable`: an optional interface for action events that name their subject and context.
- `Audit\AuditHooks`: what a package teaches the log about its models (labels, snapshot extras, subject and scope pickers, context, redacted fields).
- `Package::authorizeHistory()` lets a package that does not authorize through policies decide who reads its history.
- `Package::audits()`: whether a package audits - only while Keen is installed, and unless its `{key}.audit.enabled` config key is false. A package registers its `AuditHooks` and anything else audit-related only when it does.
- `Audit\History` plus `loadHistoryRoutes()` and `Mcp\Tools\ListHistoryTool`: every package serves its own history over HTTP and MCP while it audits. Otherwise the route answers 404, and the tool is not offered on the package's MCP server or to Cortex.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
