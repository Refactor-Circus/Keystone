<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;
use RefactorCircus\Keystone\Authorization\Contracts\Scope;
use RefactorCircus\Keystone\Testing\FakePermissions;
use RefactorCircus\Keystone\Testing\FakeRoles;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\OrganizationModel;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\TeamModel;

function fakeUser(int $id): GenericUser
{
    return new GenericUser(['id' => $id]);
}

function fakeOrganization(int $id, ?int $ownerId = null): OrganizationModel
{
    $organization = (new OrganizationModel)->forceFill(['id' => $id]);
    $organization->ownerId = $ownerId;

    return $organization;
}

function fakeTeam(int $id, ?OrganizationModel $organization): TeamModel
{
    $team = (new TeamModel)->forceFill(['id' => $id]);
    $team->parent = $organization;

    return $team;
}

describe('fake permissions', function (): void {
    it('binds itself as the permissions', function (): void {
        $fake = FakePermissions::install();

        expect(app(Permissions::class))->toBe($fake);
    });

    it('grants an ability globally or in one scope', function (): void {
        $organization = fakeOrganization(10);
        $other = fakeOrganization(11);
        $permissions = FakePermissions::install()
            ->grant(fakeUser(1), 'acme.widgets.view')
            ->grant(fakeUser(2), 'acme.widgets.edit', $organization);

        expect($permissions->allows(fakeUser(1), 'acme.widgets.view'))->toBeTrue()
            ->and($permissions->allows(fakeUser(1), 'acme.widgets.view', $organization))->toBeTrue()
            ->and($permissions->allows(fakeUser(1), 'acme.widgets.edit'))->toBeFalse()
            ->and($permissions->allows(fakeUser(2), 'acme.widgets.edit', $organization))->toBeTrue()
            ->and($permissions->allows(fakeUser(2), 'acme.widgets.edit', fakeOrganization(10)))->toBeTrue()
            ->and($permissions->allows(fakeUser(2), 'acme.widgets.edit', $other))->toBeFalse()
            ->and($permissions->allows(fakeUser(2), 'acme.widgets.edit'))->toBeFalse()
            ->and($permissions->allows(null, 'acme.widgets.view'))->toBeFalse();
    });

    it('ignores the gate', function (): void {
        Gate::define('acme.widgets.view', fn (?GenericUser $user): bool => true);

        expect(FakePermissions::install()->allows(fakeUser(1), 'acme.widgets.view'))->toBeFalse();
    });

    it('lets a super-admin do anything', function (): void {
        $permissions = FakePermissions::install()->superAdmin(fakeUser(1));

        expect($permissions->isSuperAdmin(fakeUser(1)))->toBeTrue()
            ->and($permissions->isSuperAdmin(fakeUser(2)))->toBeFalse()
            ->and($permissions->isSuperAdmin(null))->toBeFalse()
            ->and($permissions->allows(fakeUser(1), 'acme.widgets.delete'))->toBeTrue()
            ->and($permissions->allows(fakeUser(1), 'acme.widgets.delete', fakeOrganization(10)))->toBeTrue()
            ->and($permissions->scopesWith(fakeUser(1), 'acme.widgets.delete', OrganizationModel::class))->toBeNull()
            ->and($permissions->abilities(fakeUser(1)))->toBe([Permissions::SUPER_ADMIN]);
    });

    it('honours what a scope or its parent grants by itself', function (): void {
        $organization = fakeOrganization(10, ownerId: 1);
        $team = fakeTeam(20, $organization);
        $permissions = FakePermissions::install();

        expect($permissions->allows(fakeUser(1), 'acme.teams.manage', $team))->toBeTrue()
            ->and($permissions->allows(fakeUser(1), 'acme.teams.manage', $organization))->toBeTrue()
            ->and($permissions->allows(fakeUser(1), 'acme.teams.manage'))->toBeFalse()
            ->and($permissions->allows(fakeUser(2), 'acme.teams.manage', $team))->toBeFalse()
            ->and($permissions->allows(null, 'acme.teams.manage', $team))->toBeFalse()
            ->and($organization->asked)->toBe(['acme.teams.manage', 'acme.teams.manage', 'acme.teams.manage']);
    });

    it('applies a grant in a parent scope to its children', function (): void {
        $organization = fakeOrganization(10);
        $team = fakeTeam(20, $organization);
        $permissions = FakePermissions::install()
            ->grant(fakeUser(1), 'acme.teams.view', $organization)
            ->grant(fakeUser(2), 'acme.teams.view', $team);

        expect($permissions->allows(fakeUser(1), 'acme.teams.view', $team))->toBeTrue()
            ->and($permissions->allows(fakeUser(2), 'acme.teams.view', $team))->toBeTrue()
            ->and($permissions->allows(fakeUser(2), 'acme.teams.view', $organization))->toBeFalse();
    });

    it('lists the scopes of a type the user holds an ability in', function (): void {
        $permissions = FakePermissions::install()
            ->grant(fakeUser(1), 'acme.widgets.view')
            ->grant(fakeUser(2), 'acme.widgets.view', fakeOrganization(12))
            ->grant(fakeUser(2), 'acme.widgets.view', fakeOrganization(10))
            ->grant(fakeUser(2), 'acme.widgets.view', fakeTeam(20, null))
            ->grant(fakeUser(2), 'acme.widgets.edit', fakeOrganization(11));

        expect($permissions->scopesWith(fakeUser(1), 'acme.widgets.view', OrganizationModel::class))->toBeNull()
            ->and($permissions->scopesWith(fakeUser(2), 'acme.widgets.view', OrganizationModel::class))->toBe([10, 12])
            ->and($permissions->scopesWith(fakeUser(2), 'acme.widgets.view', TeamModel::class))->toBe([20])
            ->and($permissions->scopesWith(fakeUser(3), 'acme.widgets.view', OrganizationModel::class))->toBe([])
            ->and($permissions->scopesWith(null, 'acme.widgets.view', OrganizationModel::class))->toBe([]);
    });

    it('lists the abilities held globally and up the scope chain, sorted', function (): void {
        $organization = fakeOrganization(10);
        $team = fakeTeam(20, $organization);
        $permissions = FakePermissions::install()
            ->grant(fakeUser(1), 'acme.widgets.view')
            ->grant(fakeUser(1), 'acme.teams.view', $organization)
            ->grant(fakeUser(1), 'acme.teams.edit', $team)
            ->grant(fakeUser(1), 'acme.teams.edit', $team);

        expect($permissions->abilities(fakeUser(1)))->toBe(['acme.widgets.view'])
            ->and($permissions->abilities(fakeUser(1), $organization))->toBe(['acme.teams.view', 'acme.widgets.view'])
            ->and($permissions->abilities(fakeUser(1), $team))->toBe(['acme.teams.edit', 'acme.teams.view', 'acme.widgets.view'])
            ->and($permissions->abilities(null))->toBe([]);
    });

    it('answers false to a re-entrant check', function (): void {
        $permissions = FakePermissions::install();
        $scope = new class extends Model implements Scope
        {
            public function parentScope(): ?Scope
            {
                return null;
            }

            public function grantsByItself(Authenticatable $user, string $ability): bool
            {
                return app(Permissions::class)->allows($user, $ability, $this);
            }
        };

        expect($permissions->allows(fakeUser(1), 'acme.teams.manage', $scope))->toBeFalse();
    });

    it('remembers nothing to forget', function (): void {
        $permissions = FakePermissions::install()->grant(fakeUser(1), 'acme.widgets.view');

        $permissions->forget(fakeUser(1));
        $permissions->forget();

        expect($permissions->allows(fakeUser(1), 'acme.widgets.view'))->toBeTrue();
    });
});

describe('fake roles', function (): void {
    it('binds itself as the roles and is available', function (): void {
        $fake = FakeRoles::install();

        expect(app(Roles::class))->toBe($fake)
            ->and($fake->available())->toBeTrue();
    });

    it('knows the roles defined globally or for a scope type', function (): void {
        $roles = FakeRoles::install()
            ->define('admin', ['acme.widgets.view'])
            ->define('owner', ['acme.teams.manage'], OrganizationModel::class);

        expect($roles->exists('admin'))->toBeTrue()
            ->and($roles->exists('admin', fakeOrganization(10)))->toBeFalse()
            ->and($roles->exists('owner', fakeOrganization(10)))->toBeTrue()
            ->and($roles->exists('owner'))->toBeFalse()
            ->and($roles->exists('owner', fakeTeam(20, null)))->toBeFalse()
            ->and($roles->exists('missing'))->toBeFalse();
    });

    it('assigns, lists and revokes roles in exactly one scope', function (): void {
        $organization = fakeOrganization(10);
        $other = fakeOrganization(11);
        $roles = FakeRoles::install()
            ->define('admin', ['acme.widgets.view'])
            ->define('owner', ['acme.teams.manage'], OrganizationModel::class)
            ->define('member', ['acme.teams.view'], OrganizationModel::class);
        $user = fakeUser(1);

        expect($roles->assign($user, 'admin'))->toBeTrue()
            ->and($roles->assign($user, 'owner', $organization))->toBeTrue()
            ->and($roles->assign($user, 'member', $organization))->toBeTrue()
            ->and($roles->assign($user, 'member', $other))->toBeTrue()
            ->and($roles->assign($user, 'owner'))->toBeFalse()
            ->and($roles->assign($user, 'missing', $organization))->toBeFalse()
            ->and($roles->of($user))->toBe(['admin'])
            ->and($roles->of($user, fakeOrganization(10)))->toBe(['member', 'owner'])
            ->and($roles->of($user, $other))->toBe(['member'])
            ->and($roles->of(fakeUser(2), $organization))->toBe([]);

        $roles->revoke($user, $organization);

        expect($roles->of($user, $organization))->toBe([])
            ->and($roles->of($user, $other))->toBe(['member'])
            ->and($roles->of($user))->toBe(['admin']);

        $roles->revoke($user);

        expect($roles->of($user))->toBe([])
            ->and($roles->of($user, $other))->toBe(['member']);

        $roles->assign($user, 'admin');
        $roles->revokeEverywhere($user);

        expect($roles->of($user))->toBe([])
            ->and($roles->of($user, $other))->toBe([]);
    });

    it('lists the users holding an ability globally, super-admins included', function (): void {
        $roles = FakeRoles::install()
            ->define('admin', ['acme.widgets.approve'])
            ->define('root', [Permissions::SUPER_ADMIN])
            ->define('owner', ['acme.widgets.approve'], OrganizationModel::class);
        $roles->assign(fakeUser(3), 'admin');
        $roles->assign(fakeUser(1), 'root');
        $roles->assign(fakeUser(2), 'owner', fakeOrganization(10));
        FakePermissions::install()
            ->grant(fakeUser(5), 'acme.widgets.approve')
            ->superAdmin(fakeUser(4))
            ->grant(fakeUser(6), 'acme.widgets.approve', fakeOrganization(10));

        expect($roles->holders('acme.widgets.approve'))->toBe([1, 3, 4, 5])
            ->and($roles->holders('acme.widgets.reject'))->toBe([1, 4]);
    });

    it('feeds the abilities its roles grant to the installed fake permissions', function (): void {
        $organization = fakeOrganization(10);
        $team = fakeTeam(20, $organization);
        $permissions = FakePermissions::install();
        $roles = FakeRoles::install()
            ->define('admin', ['acme.widgets.view'])
            ->define('root', [Permissions::SUPER_ADMIN])
            ->define('member', ['acme.teams.view'], OrganizationModel::class);
        $roles->assign(fakeUser(1), 'admin');
        $roles->assign(fakeUser(2), 'member', $organization);
        $roles->assign(fakeUser(3), 'root');

        expect($permissions->allows(fakeUser(1), 'acme.widgets.view'))->toBeTrue()
            ->and($permissions->allows(fakeUser(2), 'acme.teams.view', $organization))->toBeTrue()
            ->and($permissions->allows(fakeUser(2), 'acme.teams.view', $team))->toBeTrue()
            ->and($permissions->allows(fakeUser(2), 'acme.teams.view'))->toBeFalse()
            ->and($permissions->scopesWith(fakeUser(2), 'acme.teams.view', OrganizationModel::class))->toBe([10])
            ->and($permissions->abilities(fakeUser(2), $team))->toBe(['acme.teams.view'])
            ->and($permissions->isSuperAdmin(fakeUser(3)))->toBeTrue()
            ->and($permissions->allows(fakeUser(3), 'acme.anything'))->toBeTrue();

        $roles->revoke(fakeUser(2), $organization);

        expect($permissions->allows(fakeUser(2), 'acme.teams.view', $organization))->toBeFalse();
    });

    it('works with fake permissions installed after it', function (): void {
        $roles = FakeRoles::install()->define('admin', ['acme.widgets.view']);
        $roles->assign(fakeUser(1), 'admin');

        expect(FakePermissions::install()->allows(fakeUser(1), 'acme.widgets.view'))->toBeTrue();
    });
});
