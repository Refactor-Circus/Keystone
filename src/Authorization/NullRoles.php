<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;

/**
 * The roles while no role store is installed: never available, stores
 * nothing, always empty, and never touches the database. A role store
 * replaces the binding.
 */
final class NullRoles implements Roles
{
    public function available(): bool
    {
        return false;
    }

    public function exists(string $role, ?Model $scope = null): bool
    {
        return false;
    }

    public function assign(Authenticatable $user, string $role, ?Model $scope = null): bool
    {
        return false;
    }

    public function revoke(Authenticatable $user, ?Model $scope = null): void {}

    public function revokeEverywhere(Authenticatable $user): void {}

    public function of(Authenticatable $user, ?Model $scope = null): array
    {
        return [];
    }

    public function holders(string $ability): array
    {
        return [];
    }
}
