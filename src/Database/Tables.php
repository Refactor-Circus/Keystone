<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Database;

use Illuminate\Contracts\Config\Repository;

/**
 * The table each of a package's records lives in, from its `{key}.tables`
 * config. Models (`getTable()`), migrations, validation rules and joins all
 * ask here, so renaming a table is one config change made before the
 * migrations run. A published config that lists only some tables keeps the
 * defaults for the rest.
 *
 *     final class Tables extends \RefactorCircus\Keystone\Database\Tables
 *     {
 *         protected const string CONFIG = 'atrium.tables';
 *
 *         public const array DEFAULTS = [
 *             'dashboards' => 'atrium_dashboards',
 *         ];
 *     }
 *
 *     Schema::create(Tables::name('dashboards'), ...);
 */
abstract class Tables
{
    /**
     * The config key holding the package's table names.
     */
    protected const string CONFIG = '';

    /**
     * Each table's key and default name.
     *
     * @var array<string, string>
     */
    public const array DEFAULTS = [];

    /**
     * The configured name of one of the package's tables, by its key.
     */
    public static function name(string $key): string
    {
        $name = app(Repository::class)->get(static::CONFIG.'.'.$key);

        return is_string($name) && $name !== '' ? $name : static::DEFAULTS[$key];
    }
}
