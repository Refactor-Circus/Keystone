<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Authorization\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The roles users hold, whichever package keeps them.
 *
 * Packages give a default role on join, take roles away on leave or delete,
 * find approvers and import or export members through this contract. The
 * shared runtime binds `NullRoles`, which stores nothing; a role store
 * replaces the binding with `singleton()` or `scoped()` in its `register()`.
 *
 * A `$scope` is null for global roles, or the Eloquent model the role is held
 * in (an organization, a team). Roles are slugs (`owner`, `member`).
 */
interface Roles
{
    /**
     * Whether a role store is installed. Callers skip role work when not.
     */
    public function available(): bool;

    /**
     * Whether the role exists, globally or for the scope.
     */
    public function exists(string $role, ?Model $scope = null): bool;

    /**
     * Give the user the role in exactly that scope.
     *
     * False when the role does not exist or nothing was stored.
     */
    public function assign(Authenticatable $user, string $role, ?Model $scope = null): bool;

    /**
     * Take away every role the user holds in exactly that scope; null takes
     * away their global roles.
     */
    public function revoke(Authenticatable $user, ?Model $scope = null): void;

    /**
     * Take away every role the user holds, globally and in every scope.
     */
    public function revokeEverywhere(Authenticatable $user): void;

    /**
     * The slugs of the roles the user holds in exactly that scope, sorted.
     *
     * @return list<string>
     */
    public function of(Authenticatable $user, ?Model $scope = null): array;

    /**
     * The keys of the users who hold the ability globally, super-admins
     * included.
     *
     * @return list<int|string>
     */
    public function holders(string $ability): array;
}
