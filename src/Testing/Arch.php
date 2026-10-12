<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Testing;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RefactorCircus\Keystone\Contracts\ActionFinishedEvent;
use RefactorCircus\Keystone\Contracts\ActionStartingEvent;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Models\Concerns\DispatchesModelEvents;
use ReflectionClass;

/**
 * Helpers for a package's architecture tests, for the suite's domain-module
 * layout: `{domains}/{Domain}/{Sub}` (usually `src/Domains`) in
 * `{Namespace}\Domains\{Domain}\{Sub}`.
 */
final class Arch
{
    /**
     * The Eloquent hooks every model has an event for.
     *
     * @var list<string>
     */
    public const array MODEL_HOOKS = [
        'retrieved', 'creating', 'created', 'updating', 'updated',
        'saving', 'saved', 'deleting', 'deleted', 'replicating',
    ];

    /**
     * The hooks only a soft-deleting model has.
     *
     * @var list<string>
     */
    public const array SOFT_DELETE_HOOKS = ['restoring', 'restored', 'trashed', 'forceDeleting', 'forceDeleted'];

    /**
     * Calls that write to the database.
     */
    private const string WRITES = '/(->|::)(create|createMany|createQuietly|forceCreate|firstOrCreate|updateOrCreate|update|updateQuietly|updateOrInsert|save|saveQuietly|saveMany|delete|deleteQuietly|forceDelete|restore|insert|insertOrIgnore|insertGetId|upsert|increment|decrement|incrementEach|attach|detach|sync|syncWithoutDetaching|syncWithPivotValues|updateExistingPivot|toggle)\(/';

    /**
     * The given sub-namespace of every domain that has one, e.g. `Actions`.
     *
     * @return array<int, string>
     */
    public static function domainNamespaces(string $namespace, string $domains, string $sub): array
    {
        $domains = rtrim($domains, '/');

        return array_map(
            fn (string $path): string => $namespace.'\\Domains\\'.str_replace('/', '\\', substr($path, strlen($domains) + 1)),
            glob($domains.'/*/'.$sub, GLOB_ONLYDIR) ?: [],
        );
    }

    /**
     * Every class in the given sub-namespace of every domain, by its file.
     *
     * @return array<int, class-string>
     */
    public static function domainClasses(string $namespace, string $domains, string $sub): array
    {
        $domains = rtrim($domains, '/');

        /** @var array<int, class-string> */
        return array_map(
            fn (string $path): string => $namespace.'\\Domains\\'.str_replace('/', '\\', substr($path, strlen($domains) + 1, -4)),
            glob($domains.'/*/'.$sub.'/*.php') ?: [],
        );
    }

    /**
     * Actions that do not dispatch both a starting and a finished event,
     * with what each lacks, judged by the event classes each file imports and
     * calls `::dispatch(` on.
     *
     * @param  string|array<int, string>  $globs
     * @return array<string, array<int, string>>
     */
    public static function actionEventGaps(string|array $globs): array
    {
        $gaps = [];

        foreach (array_merge(...array_map(fn (string $glob): array => glob($glob) ?: [], (array) $globs)) as $path) {
            $source = (string) file_get_contents($path);
            preg_match_all('/^use ([\w\\\\]+\\\\Events\\\\(\w+));$/m', $source, $imports, PREG_SET_ORDER);
            $dispatched = array_filter($imports, fn (array $import): bool => str_contains($source, $import[2].'::dispatch('));
            $missing = [];

            foreach (['starting' => ActionStartingEvent::class, 'finished' => ActionFinishedEvent::class] as $kind => $contract) {
                if (array_filter($dispatched, fn (array $import): bool => is_subclass_of($import[1], $contract)) === []) {
                    $missing[] = $kind;
                }
            }

            if ($missing !== []) {
                $gaps[basename($path, '.php')] = $missing;
            }
        }

        return $gaps;
    }

    /**
     * Data classes that are not `final readonly` with their own `rules()`.
     *
     * @param  array<int, class-string>  $classes
     * @return array<int, class-string>
     */
    public static function dataWithoutRules(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            fn (string $class): bool => is_subclass_of($class, Data::class)
                && (! method_exists($class, 'rules') || ! (new ReflectionClass($class))->isFinal() || ! (new ReflectionClass($class))->isReadOnly()),
        ));
    }

    /**
     * Models that do not fire an event for every Eloquent hook, with the
     * hooks each lacks: a `{Entity}{Hook}Event` in the domain's `Events`,
     * dispatched through `DispatchesModelEvents`. The soft-delete hooks
     * count only for models that use `SoftDeletes`.
     *
     * @param  array<int, class-string>  $models
     * @return array<string, array<int, string>>
     */
    public static function modelEventGaps(array $models): array
    {
        $gaps = [];

        foreach ($models as $model) {
            $traits = class_uses_recursive($model);
            $hooks = in_array(SoftDeletes::class, $traits, true) ? [...self::MODEL_HOOKS, ...self::SOFT_DELETE_HOOKS] : self::MODEL_HOOKS;
            $namespace = Str::of($model)->beforeLast('\\')->replaceEnd('\\Models', '');
            $prefix = Str::replaceEnd('Model', '', class_basename($model));

            $missing = array_values(array_filter(
                $hooks,
                fn (string $hook): bool => ! class_exists($namespace.'\\Events\\'.$prefix.Str::ucfirst($hook).'Event'),
            ));

            if (! in_array(DispatchesModelEvents::class, $traits, true)) {
                array_unshift($missing, 'DispatchesModelEvents');
            }

            if ($missing !== []) {
                $gaps[class_basename($model)] = $missing;
            }
        }

        return $gaps;
    }

    /**
     * Actions that write to the database - create, update, delete, save,
     * pivot changes and the like - without a `transaction(` around the work,
     * by their source. A package whose writes go through other calls names
     * them too, such as Pennant's `activate` and `purge`.
     *
     * @param  string|array<int, string>  $globs
     * @param  array<int, string>  $writes
     * @return array<int, string>
     */
    public static function writesOutsideTransactions(string|array $globs, array $writes = []): array
    {
        $gaps = [];
        $extra = $writes === [] ? null : '/(->|::)('.implode('|', array_map(fn (string $write): string => preg_quote($write, '/'), $writes)).')\(/';

        foreach (array_merge(...array_map(fn (string $glob): array => glob($glob) ?: [], (array) $globs)) as $path) {
            $source = (string) file_get_contents($path);
            $writing = preg_match(self::WRITES, $source) === 1 || ($extra !== null && preg_match($extra, $source) === 1);

            if ($writing && ! str_contains($source, 'transaction(')) {
                $gaps[] = basename($path, '.php');
            }
        }

        return $gaps;
    }

    /**
     * Models without a policy in the package's `{key}.policies` config, or
     * whose configured policy class does not exist.
     *
     * @param  array<int, class-string>  $models
     * @param  array<array-key, mixed>  $policies
     * @return array<int, string>
     */
    public static function modelsWithoutPolicies(array $models, array $policies): array
    {
        return array_values(array_map(
            fn (string $model): string => class_basename($model),
            array_filter($models, fn (string $model): bool => ! is_string($policies[$model] ?? null) || ! class_exists($policies[$model])),
        ));
    }

    /**
     * Files that name one of the package's tables as a string literal - a
     * quoted name starting with the prefix, such as `'atrium_dashboards'` -
     * rather than asking the package's `Tables`, with the names each holds.
     *
     * @param  string|array<int, string>  $globs
     * @param  array<int, string>  $tables  the default table names
     * @return array<string, array<int, string>>
     */
    public static function literalTableNames(string|array $globs, array $tables): array
    {
        $gaps = [];
        $pattern = '/[\'"]('.implode('|', array_map(fn (string $table): string => preg_quote($table, '/'), $tables)).')[\'"]/';

        foreach (array_merge(...array_map(fn (string $glob): array => glob($glob) ?: [], (array) $globs)) as $path) {
            if (preg_match_all($pattern, (string) file_get_contents($path), $matches) > 0) {
                $gaps[basename($path)] = array_values(array_unique($matches[1]));
            }
        }

        return $gaps;
    }
}
