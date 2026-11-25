<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;

/**
 * A cross-cutting rule wrapped around whichever permissions are bound, as a
 * package adds it with `$app->extend()`.
 */
final readonly class DecoratedPermissions implements Permissions
{
    public function __construct(public Permissions $inner) {}

    public function allows(?Authenticatable $user, string $ability, mixed $scope = null): bool
    {
        return $this->inner->allows($user, $ability, $scope);
    }

    public function scopesWith(?Authenticatable $user, string $ability, string $scopeType): ?array
    {
        return $this->inner->scopesWith($user, $ability, $scopeType);
    }

    public function abilities(?Authenticatable $user, mixed $scope = null): ?array
    {
        return $this->inner->abilities($user, $scope);
    }

    public function isSuperAdmin(?Authenticatable $user): bool
    {
        return $this->inner->isSuperAdmin($user);
    }

    public function forget(?Authenticatable $user = null): void
    {
        $this->inner->forget($user);
    }
}
