<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;

/**
 * The roles a role store package would bind: always available, so a test can
 * tell it from the null store.
 */
final class StoreRoles implements Roles
{
    public function available(): bool
    {
        return true;
    }

    public function exists(string $role, ?Model $scope = null): bool
    {
        return true;
    }

    public function assign(Authenticatable $user, string $role, ?Model $scope = null): bool
    {
        return true;
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
