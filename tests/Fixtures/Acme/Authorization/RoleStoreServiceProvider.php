<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization;

use Illuminate\Support\ServiceProvider;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;

/**
 * A role store package: it replaces the shared runtime's defaults from
 * register(), whichever order the providers load in.
 */
final class RoleStoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Permissions::class, StorePermissions::class);
        $this->app->singleton(Roles::class, StoreRoles::class);
    }
}
