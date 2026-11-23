<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Authorization\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What a user may do, whichever package keeps roles.
 *
 * Every package asks this contract for named abilities (`acme.widgets.view`)
 * instead of naming a role store, so none of them depends on one. The shared
 * runtime binds `GatePermissions`, which asks the host's Gate; a role store
 * replaces the binding with `singleton()` or `scoped()` in its `register()`,
 * and a cross-cutting rule wraps it with `$app->extend()`.
 *
 * A `$scope` is null (a global question), a `Scope` (its own rule and its
 * parents' grants count too), or any other Eloquent model (asked about as it
 * is, with no hierarchy). A null user is a guest.
 */
interface Permissions
{
    /**
     * The ability that lets a user do anything.
     */
    public const string SUPER_ADMIN = 'super-admin';

    /**
     * Whether the user may use the ability, globally or in the scope.
     *
     * A `Scope` that, or whose parent, grants the ability by itself allows it.
     * Implementations refuse a question asked again while it is being answered
     * (a Gate `before` callback calling back in) instead of recursing.
     */
    public function allows(?Authenticatable $user, string $ability, mixed $scope = null): bool;

    /**
     * The keys of the scopes of the given type in which the user may use the
     * ability, for limiting a list to them. `$scopeType` is the scope model's
     * class or morph alias.
     *
     * Null means the user is not limited to any scope (they hold the ability
     * globally); an empty list means no scope at all.
     *
     * @return list<int|string>|null
     */
    public function scopesWith(?Authenticatable $user, string $ability, string $scopeType): ?array;

    /**
     * Every ability the user holds, globally or in the scope, sorted.
     *
     * Null means this implementation cannot list them: callers treat it as
     * unknown, never as "no abilities".
     *
     * @return list<string>|null
     */
    public function abilities(?Authenticatable $user, mixed $scope = null): ?array;

    /**
     * Whether the user holds `SUPER_ADMIN`. A guest never does.
     */
    public function isSuperAdmin(?Authenticatable $user): bool;

    /**
     * Drop whatever the implementation remembers about the user, or about
     * everyone when null, after their roles change.
     */
    public function forget(?Authenticatable $user = null): void;
}
