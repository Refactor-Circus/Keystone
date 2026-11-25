<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RefactorCircus\Keystone\Authorization\Abilities;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;
use RefactorCircus\Keystone\Authorization\GatePermissions;
use RefactorCircus\Keystone\Authorization\NullRoles;
use RefactorCircus\Keystone\Authorization\ScopeChain;
use RefactorCircus\Keystone\KeystoneServiceProvider;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\DecoratedPermissions;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\OrganizationModel;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\RoleStoreServiceProvider;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\StorePermissions;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\StoreRoles;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\TeamModel;

/**
 * A bare application with only the given providers registered, in order.
 *
 * @param  list<class-string<ServiceProvider>>  $providers
 */
function authorizationApp(array $providers): Application
{
    $app = new Application(dirname(__DIR__, 2));

    foreach ($providers as $provider) {
        $app->register($provider);
    }

    return $app;
}

function authorizationUser(int $id): GenericUser
{
    return new GenericUser(['id' => $id, 'name' => 'User '.$id]);
}

function organizationOwnedBy(int $ownerId): OrganizationModel
{
    $organization = new OrganizationModel;
    $organization->ownerId = $ownerId;

    return $organization;
}

function teamIn(?OrganizationModel $organization): TeamModel
{
    $team = new TeamModel;
    $team->parent = $organization;

    return $team;
}

describe('bindings', function (): void {
    it('binds the gate fallback, the null role store and one ability registry', function (): void {
        expect(app(Permissions::class))->toBeInstanceOf(GatePermissions::class)
            ->and(app(Roles::class))->toBeInstanceOf(NullRoles::class)
            ->and(app(Abilities::class))->toBeInstanceOf(Abilities::class)
            ->and(app(Abilities::class))->toBe(app(Abilities::class))
            ->and(app(Permissions::class))->toBe(app(Permissions::class));
    });

    it('lets a role store registered before the shared runtime win', function (): void {
        $app = authorizationApp([RoleStoreServiceProvider::class, KeystoneServiceProvider::class]);

        expect($app->make(Permissions::class))->toBeInstanceOf(StorePermissions::class)
            ->and($app->make(Roles::class))->toBeInstanceOf(StoreRoles::class);
    });

    it('lets a role store registered after the shared runtime win', function (): void {
        $app = authorizationApp([KeystoneServiceProvider::class, RoleStoreServiceProvider::class]);

        expect($app->make(Permissions::class))->toBeInstanceOf(StorePermissions::class)
            ->and($app->make(Roles::class))->toBeInstanceOf(StoreRoles::class);
    });

    it('keeps a decorator added before the role store rebinds', function (): void {
        $app = authorizationApp([KeystoneServiceProvider::class]);
        $app->extend(Permissions::class, fn (Permissions $permissions, ApplicationContract $app): Permissions => new DecoratedPermissions($permissions));
        $app->register(RoleStoreServiceProvider::class);

        $permissions = $app->make(Permissions::class);

        expect($permissions)->toBeInstanceOf(DecoratedPermissions::class)
            ->and($permissions instanceof DecoratedPermissions ? $permissions->inner : null)->toBeInstanceOf(StorePermissions::class);
    });
});

describe('gate fallback', function (): void {
    it('honours an ability the host defines and refuses an undefined one', function (): void {
        Gate::define('acme.widgets.view', fn (GenericUser $user): bool => $user->getAuthIdentifier() === 1);
        $permissions = app(Permissions::class);

        expect($permissions->allows(authorizationUser(1), 'acme.widgets.view'))->toBeTrue()
            ->and($permissions->allows(authorizationUser(2), 'acme.widgets.view'))->toBeFalse()
            ->and($permissions->allows(authorizationUser(1), 'acme.widgets.delete'))->toBeFalse();
    });

    it('passes the scope to the gate as its only argument, and none without one', function (): void {
        $organization = organizationOwnedBy(99);
        Gate::define('acme.widgets.view', fn (GenericUser $user, mixed ...$arguments): bool => $arguments === [$organization]);
        Gate::define('acme.widgets.list', fn (GenericUser $user, mixed ...$arguments): bool => $arguments === []);
        $permissions = app(Permissions::class);

        expect($permissions->allows(authorizationUser(1), 'acme.widgets.view', $organization))->toBeTrue()
            ->and($permissions->allows(authorizationUser(1), 'acme.widgets.view'))->toBeFalse()
            ->and($permissions->allows(authorizationUser(1), 'acme.widgets.list'))->toBeTrue();
    });

    it('refuses a guest unless the host gate accepts guests', function (): void {
        Gate::define('acme.widgets.view', fn (GenericUser $user): bool => true);
        Gate::define('acme.widgets.browse', fn (?GenericUser $user): bool => true);
        $permissions = app(Permissions::class);

        expect($permissions->allows(null, 'acme.widgets.view'))->toBeFalse()
            ->and($permissions->allows(null, 'acme.widgets.browse'))->toBeTrue();
    });

    it('allows what a parent scope grants by itself', function (): void {
        $organization = organizationOwnedBy(1);
        $team = teamIn($organization);
        $permissions = app(Permissions::class);

        expect($permissions->allows(authorizationUser(1), 'acme.teams.manage', $team))->toBeTrue()
            ->and($permissions->allows(authorizationUser(1), 'acme.teams.manage', $organization))->toBeTrue()
            ->and($permissions->allows(authorizationUser(2), 'acme.teams.manage', $team))->toBeFalse()
            ->and($permissions->allows(authorizationUser(1), 'acme.teams.manage', teamIn(null)))->toBeFalse();
    });

    it('never asks a scope about a guest', function (): void {
        $organization = organizationOwnedBy(1);

        expect(app(Permissions::class)->allows(null, 'acme.teams.manage', teamIn($organization)))->toBeFalse()
            ->and($organization->asked)->toBe([]);
    });

    it('answers false to a re-entrant check and completes the outer one', function (): void {
        $calls = 0;
        Gate::before(function (?GenericUser $user, string $ability, array $arguments) use (&$calls): ?bool {
            $calls++;

            return app(Permissions::class)->allows($user, $ability, $arguments[0] ?? null) ? true : null;
        });
        $permissions = app(Permissions::class);

        expect($permissions->allows(authorizationUser(1), 'acme.widgets.view'))->toBeFalse()
            ->and($calls)->toBe(1);

        Gate::define('acme.widgets.view', fn (GenericUser $user): bool => true);

        expect($permissions->allows(authorizationUser(1), 'acme.widgets.view'))->toBeTrue()
            ->and($permissions->allows(authorizationUser(1), 'acme.widgets.view'))->toBeTrue()
            ->and($calls)->toBe(3);
    });

    it('clears the re-entry guard when the gate throws', function (): void {
        Gate::define('acme.widgets.view', fn (GenericUser $user): bool => throw new RuntimeException('Gate failed.'));
        $permissions = app(Permissions::class);

        expect(fn (): bool => $permissions->allows(authorizationUser(1), 'acme.widgets.view'))
            ->toThrow(RuntimeException::class, 'Gate failed.');

        Gate::define('acme.widgets.view', fn (GenericUser $user): bool => true);

        expect($permissions->allows(authorizationUser(1), 'acme.widgets.view'))->toBeTrue();
    });

    it('answers scopesWith from the unscoped check', function (): void {
        Gate::define('acme.widgets.view', fn (GenericUser $user): bool => $user->getAuthIdentifier() === 1);
        $permissions = app(Permissions::class);

        expect($permissions->scopesWith(authorizationUser(1), 'acme.widgets.view', OrganizationModel::class))->toBeNull()
            ->and($permissions->scopesWith(authorizationUser(2), 'acme.widgets.view', OrganizationModel::class))->toBe([])
            ->and($permissions->scopesWith(null, 'acme.widgets.view', OrganizationModel::class))->toBe([]);
    });

    it('cannot list abilities', function (): void {
        expect(app(Permissions::class)->abilities(authorizationUser(1)))->toBeNull()
            ->and(app(Permissions::class)->abilities(authorizationUser(1), organizationOwnedBy(1)))->toBeNull();
    });

    it('asks the gate whether a signed-in user is a super-admin', function (): void {
        $permissions = app(Permissions::class);

        expect($permissions->isSuperAdmin(authorizationUser(1)))->toBeFalse();

        Gate::define(Permissions::SUPER_ADMIN, fn (?GenericUser $user): bool => $user === null || $user->getAuthIdentifier() === 1);

        expect($permissions->isSuperAdmin(authorizationUser(1)))->toBeTrue()
            ->and($permissions->isSuperAdmin(authorizationUser(2)))->toBeFalse()
            ->and($permissions->isSuperAdmin(null))->toBeFalse();
    });

    it('forgets nothing because it remembers nothing', function (): void {
        Gate::define('acme.widgets.view', fn (GenericUser $user): bool => true);
        $permissions = app(Permissions::class);

        $permissions->forget(authorizationUser(1));
        $permissions->forget();

        expect($permissions->allows(authorizationUser(1), 'acme.widgets.view'))->toBeTrue();
    });
});

describe('scope chain', function (): void {
    it('lists the scope and its parents, nearest first', function (): void {
        $organization = organizationOwnedBy(1);
        $team = teamIn($organization);
        $squad = new TeamModel;
        $squad->parent = $team;

        expect(ScopeChain::of($squad))->toBe([$squad, $team, $organization])
            ->and(ScopeChain::of($organization))->toBe([$organization])
            ->and(ScopeChain::of(null))->toBe([]);
    });

    it('stops at a repeated scope', function (): void {
        $first = new TeamModel;
        $second = teamIn(null);
        $first->parent = $second;
        $second->parent = $first;

        expect(ScopeChain::of($first))->toBe([$first, $second]);
    });

    it('stops at a scope loaded again with the same key', function (): void {
        $first = (new TeamModel)->forceFill(['id' => 1]);
        $second = (new TeamModel)->forceFill(['id' => 2]);
        $again = (new TeamModel)->forceFill(['id' => 1]);
        $first->parent = $second;
        $second->parent = $again;
        $again->parent = $second;

        expect(ScopeChain::of($first))->toBe([$first, $second]);
    });

    it('keeps unsaved scopes apart', function (): void {
        $first = new TeamModel;
        $second = new TeamModel;
        $first->parent = $second;

        expect(ScopeChain::of($first))->toBe([$first, $second]);
    });
});

describe('null roles', function (): void {
    it('stores nothing, answers empty and never queries the database', function (): void {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $roles = app(Roles::class);
        $user = authorizationUser(1);
        $organization = organizationOwnedBy(1);

        expect($roles->available())->toBeFalse()
            ->and($roles->exists('owner'))->toBeFalse()
            ->and($roles->exists('owner', $organization))->toBeFalse()
            ->and($roles->assign($user, 'owner'))->toBeFalse()
            ->and($roles->assign($user, 'owner', $organization))->toBeFalse()
            ->and($roles->of($user))->toBe([])
            ->and($roles->of($user, $organization))->toBe([])
            ->and($roles->holders('acme.widgets.view'))->toBe([]);

        $roles->revoke($user);
        $roles->revoke($user, $organization);
        $roles->revokeEverywhere($user);

        expect($queries)->toBe(0);
    });
});
