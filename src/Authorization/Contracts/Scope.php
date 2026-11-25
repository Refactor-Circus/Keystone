<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Authorization\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * An Eloquent model that permissions are scoped to, such as an organization
 * or a team.
 *
 * Every `Permissions` implementation honours both methods, so the package that
 * owns the model keeps its own rules (an owner may do anything) and its
 * hierarchy (a team inside an organization) without a role store knowing it.
 * Walk a scope and its parents with `ScopeChain::of()`.
 */
interface Scope
{
    /**
     * The scope whose grants also apply here, such as a team's organization;
     * null at the top.
     */
    public function parentScope(): ?Scope;

    /**
     * The scope's own rule, independent of roles: true when the signed-in
     * user may use the ability here because of what the scope itself knows
     * (an organization's owner, say).
     */
    public function grantsByItself(Authenticatable $user, string $ability): bool;
}
