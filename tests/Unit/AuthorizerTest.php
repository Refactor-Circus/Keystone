<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use RefactorCircus\Keystone\Auth\Authorizer;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;
use RefactorCircus\Keystone\Policies\Policy;
use RefactorCircus\Keystone\Testing\FakePermissions;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization\OrganizationModel;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Models\WidgetModel;

function strictAuthorizer(bool $enabled = true): Authorizer
{
    $package = app(PackageRegistry::class)->register(Package::make('strict', 'Strict')->authorization());
    config()->set('strict.authorization', $enabled);

    return Authorizer::for($package);
}

function authorizerUser(int $id): User
{
    return (new User)->forceFill(['id' => $id]);
}

final class AbilityWidgetPolicy extends Policy
{
    public function browse(?Authenticatable $user, mixed $scope = null): bool
    {
        return $this->allowsAbility($user, 'acme.widgets.browse', $scope);
    }
}

describe('allows', function (): void {
    it('allows every ability while authorization is off, without asking the contract', function (): void {
        FakePermissions::install();
        $authorizer = strictAuthorizer(false);

        expect($authorizer->allows(authorizerUser(1), 'acme.widgets.view'))->toBeTrue()
            ->and($authorizer->allows(null, 'acme.widgets.view'))->toBeTrue();
    });

    it('answers what the bound permissions answer while authorization is on', function (): void {
        $organization = (new OrganizationModel)->forceFill(['id' => 10]);
        FakePermissions::install()
            ->grant(authorizerUser(1), 'acme.widgets.view')
            ->grant(authorizerUser(2), 'acme.widgets.view', $organization);
        $authorizer = strictAuthorizer();

        expect($authorizer->allows(authorizerUser(1), 'acme.widgets.view'))->toBeTrue()
            ->and($authorizer->allows(authorizerUser(1), 'acme.widgets.delete'))->toBeFalse()
            ->and($authorizer->allows(authorizerUser(2), 'acme.widgets.view'))->toBeFalse()
            ->and($authorizer->allows(authorizerUser(2), 'acme.widgets.view', $organization))->toBeTrue()
            ->and($authorizer->allows(null, 'acme.widgets.view'))->toBeFalse();
    });

    it('asks whichever permissions are bound when it is called', function (): void {
        $authorizer = strictAuthorizer();

        expect($authorizer->allows(authorizerUser(1), 'acme.widgets.view'))->toBeFalse();

        FakePermissions::install()->grant(authorizerUser(1), 'acme.widgets.view');

        expect($authorizer->allows(authorizerUser(1), 'acme.widgets.view'))->toBeTrue();
    });

    it('passes a guest to the contract', function (): void {
        Gate::define('acme.widgets.browse', fn (?GenericUser $user): bool => true);

        expect(strictAuthorizer()->allows(null, 'acme.widgets.browse'))->toBeTrue();
    });
});

describe('can', function (): void {
    it('allows everything while authorization is off', function (): void {
        expect(strictAuthorizer(false)->can(null, 'view', WidgetModel::class))->toBeTrue();
    });

    it('asks the gate with the model subject for a signed-in model', function (): void {
        Gate::define('view', fn (User $user, WidgetModel $widget): bool => $user->getKey() === 1);
        $authorizer = strictAuthorizer();
        $widget = new WidgetModel;

        expect($authorizer->can(authorizerUser(1), 'view', $widget))->toBeTrue()
            ->and($authorizer->can(authorizerUser(2), 'view', $widget))->toBeFalse()
            ->and($authorizer->can(null, 'view', $widget))->toBeFalse()
            ->and($authorizer->can(new GenericUser(['id' => 1]), 'view', $widget))->toBeFalse();
    });

    it('never asks the permissions contract', function (): void {
        FakePermissions::install()->superAdmin(authorizerUser(1));

        expect(strictAuthorizer()->can(authorizerUser(1), 'view', new WidgetModel))->toBeFalse();
    });
});

describe('policy allowsAbility', function (): void {
    it('asks the bound permissions', function (): void {
        $organization = (new OrganizationModel)->forceFill(['id' => 10]);
        FakePermissions::install()->grant(authorizerUser(1), 'acme.widgets.browse', $organization);
        $policy = new AbilityWidgetPolicy;

        expect($policy->browse(authorizerUser(1), $organization))->toBeTrue()
            ->and($policy->browse(authorizerUser(1)))->toBeFalse()
            ->and($policy->browse(authorizerUser(2), $organization))->toBeFalse();
    });

    it('asks about a guest too', function (): void {
        $policy = new AbilityWidgetPolicy;

        expect($policy->browse(null))->toBeFalse();

        Gate::define('acme.widgets.browse', fn (?GenericUser $user): bool => $user === null);

        expect($policy->browse(null))->toBeTrue();
    });
});
