<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Database;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Where a package's records live: the table each one is in, from its
 * `{key}.tables` config, and the database connection they all share, from
 * its `{key}.database.connection` config. Models (`getTable()`,
 * `getConnectionName()`), migrations (`getConnection()`), validation rules,
 * joins, raw queries and transactions all ask here, so renaming a table or
 * moving the package to its own database is one config change made before
 * the migrations run. A published config that lists only some tables keeps
 * the defaults for the rest; no connection configured means the app's
 * default connection.
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
 *     Tables::db()->transaction(fn () => ...);
 *     Rule::exists(Tables::qualified('dashboards'), 'id');
 */
abstract class Tables
{
    /**
     * The config key holding the package's table names.
     */
    protected const string CONFIG = '';

    /**
     * The config key holding the package's database connection name. Empty
     * means `{key}.database.connection`, `{key}` being the first segment of
     * CONFIG.
     */
    protected const string CONNECTION = '';

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

    /**
     * The database connection the package's tables live on, or null for the
     * app's default connection.
     */
    public static function connection(): ?string
    {
        $name = app(Repository::class)->get(static::connectionKey());

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The package's database connection, for raw queries, schema checks and
     * transactions around the package's writes.
     */
    public static function db(): ConnectionInterface
    {
        return app(ConnectionResolverInterface::class)->connection(static::connection());
    }

    /**
     * One of the package's tables as validation rules (`exists`, `unique`)
     * expect it: `{connection}.{table}` when the package has its own
     * connection, else the bare table name.
     */
    public static function qualified(string $key): string
    {
        $connection = static::connection();

        return ($connection === null ? '' : $connection.'.').static::name($key);
    }

    /**
     * The config key holding the package's database connection name.
     */
    protected static function connectionKey(): string
    {
        return static::CONNECTION !== ''
            ? static::CONNECTION
            : explode('.', static::CONFIG, 2)[0].'.database.connection';
    }
}
