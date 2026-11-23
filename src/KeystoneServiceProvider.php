<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone;

use Illuminate\Support\ServiceProvider;
use RefactorCircus\Keystone\Audit\AuditHooks;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;
use RefactorCircus\Keystone\Audit\NullAuditTrail;
use RefactorCircus\Keystone\Authorization\Abilities;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;
use RefactorCircus\Keystone\Authorization\GatePermissions;
use RefactorCircus\Keystone\Authorization\NullRoles;
use RefactorCircus\Keystone\Packages\PackageRegistry;
use RefactorCircus\Keystone\Support\Surface;

/**
 * The shared runtime every package of the suite stands on.
 *
 * It has no routes, views or config of its own: it holds the package
 * registry, the hooks packages give the audit log, the current surface, and
 * a null audit trail until refactor-circus/keen binds a real one. For
 * authorization it holds the ability registry, Gate-backed permissions and a
 * null role store until a role store binds its own with `singleton()` or
 * `scoped()`, which wins in either provider order.
 */
class KeystoneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singletonIf(PackageRegistry::class);
        $this->app->singletonIf(AuditHooks::class);
        $this->app->scopedIf(Surface::class);
        $this->app->singletonIf(AuditTrail::class, NullAuditTrail::class);
        $this->app->singletonIf(Abilities::class);
        $this->app->singletonIf(Permissions::class, GatePermissions::class);
        $this->app->singletonIf(Roles::class, NullRoles::class);
    }
}
