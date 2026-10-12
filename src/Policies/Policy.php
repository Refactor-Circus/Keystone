<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Shared checks for the policies each package bundles.
 *
 * Policies are registered from a package's `policies` config key, so an
 * application swaps one by pointing its model at another class there.
 */
abstract class Policy
{
    /**
     * Ask the Gate about a parent model, so a child model follows whichever
     * policy is registered for its parent: a dashboard widget follows the
     * dashboard, a cart line the cart. A guest is asked about too, so a
     * parent policy that lets guests in decides for its children as well. Any
     * signed-in user works, Eloquent or not (a GenericUser, say).
     *
     * @param  array<int, mixed>  $arguments
     */
    protected function allowsOn(?Authenticatable $user, string $ability, Model $parent, array $arguments = []): bool
    {
        return Gate::forUser($user)->allows($ability, [$parent, ...$arguments]);
    }
}
