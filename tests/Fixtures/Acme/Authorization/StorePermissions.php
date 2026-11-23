<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;

/**
 * The permissions a role store package would bind: here, everyone may do
 * everything, so a test can tell it from the Gate fallback.
 */
final class StorePermissions implements Permissions
{
    public function allows(?Authenticatable $user, string $ability, mixed $scope = null): bool
    {
        return true;
    }

    public function scopesWith(?Authenticatable $user, string $ability, string $scopeType): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function abilities(?Authenticatable $user, mixed $scope = null): array
    {
        return [];
    }

    public function isSuperAdmin(?Authenticatable $user): bool
    {
        return true;
    }

    public function forget(?Authenticatable $user = null): void {}
}
