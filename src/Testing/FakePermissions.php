<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;
use RefactorCircus\Keystone\Authorization\Contracts\Scope;
use RefactorCircus\Keystone\Authorization\ScopeChain;

/**
 * In-memory permissions for a package's test suite, bound in place of the
 * Gate fallback. The Gate is never asked: a user may use what was granted,
 * globally or in a scope, plus what the roles of an installed `FakeRoles`
 * grant.
 *
 * Like a real role store, a global grant applies in every scope, a grant in a
 * scope applies in its child scopes, a `Scope` that grants the ability by
 * itself allows it, a super-admin may do anything, and a guest may do
 * nothing. A saved model loaded twice is the same scope.
 *
 *     FakePermissions::install()
 *         ->grant($user, 'acme.widgets.view')
 *         ->grant($user, 'acme.widgets.edit', $organization)
 *         ->superAdmin($admin);
 */
final class FakePermissions implements Permissions
{
    /**
     * The user's identifier, by user key.
     *
     * @var array<string, int|string>
     */
    private array $users = [];

    /**
     * The scopes granted in, by scope key.
     *
     * @var array<string, mixed>
     */
    private array $scopes = [];

    /**
     * The abilities granted, by user key and scope key.
     *
     * @var array<string, array<string, array<string, true>>>
     */
    private array $granted = [];

    /**
     * The checks being answered right now, so a scope that asks again while
     * it is asked gets false instead of a loop.
     *
     * @var array<string, true>
     */
    private array $running = [];

    /**
     * Bind a new fake as the application's `Permissions`.
     */
    public static function install(): self
    {
        $fake = new self;

        app()->instance(Permissions::class, $fake);

        return $fake;
    }

    /**
     * Let the user use the ability globally (and so in every scope), or in
     * the scope and its children.
     */
    public function grant(Authenticatable $user, string $ability, mixed $scope = null): self
    {
        $who = self::userKey($user);
        $where = self::scopeKey($scope);
        $identifier = $user->getAuthIdentifier();

        if (is_int($identifier) || is_string($identifier)) {
            $this->users[$who] = $identifier;
        }

        $this->scopes[$where] = $scope;
        $this->granted[$who][$where][$ability] = true;

        return $this;
    }

    /**
     * Make the user a super-admin, who may do anything.
     */
    public function superAdmin(Authenticatable $user): self
    {
        return $this->grant($user, self::SUPER_ADMIN);
    }

    public function allows(?Authenticatable $user, string $ability, mixed $scope = null): bool
    {
        if ($user === null) {
            return false;
        }

        $key = self::userKey($user)."\0".$ability."\0".self::scopeKey($scope);

        if (isset($this->running[$key])) {
            return false;
        }

        $this->running[$key] = true;

        try {
            $global = $this->held($user, null);

            if (isset($global[self::SUPER_ADMIN]) || isset($global[$ability])) {
                return true;
            }

            foreach (self::chain($scope) as $link) {
                if (isset($this->held($user, $link)[$ability])) {
                    return true;
                }

                if ($link instanceof Scope && $link->grantsByItself($user, $ability)) {
                    return true;
                }
            }

            return false;
        } finally {
            unset($this->running[$key]);
        }
    }

    /**
     * Null when the user may use the ability globally, otherwise the sorted
     * keys of the scopes of that type granted in, directly or through a role.
     */
    public function scopesWith(?Authenticatable $user, string $ability, string $scopeType): ?array
    {
        if ($user === null) {
            return [];
        }

        if ($this->allows($user, $ability)) {
            return null;
        }

        $keys = $this->roles()?->scopesGranting($user, $ability, $scopeType) ?? [];

        foreach ($this->granted[self::userKey($user)] ?? [] as $where => $abilities) {
            $scope = $this->scopes[$where] ?? null;

            if (isset($abilities[$ability]) && $scope instanceof Model && self::isOfType($scope, $scopeType)) {
                $key = $scope->getKey();

                if (is_int($key) || is_string($key)) {
                    $keys[] = $key;
                }
            }
        }

        $keys = array_values(array_unique($keys, SORT_REGULAR));
        sort($keys);

        return $keys;
    }

    /**
     * The abilities granted globally and up the scope chain, directly or
     * through a role, sorted. What a scope grants by itself is not listed.
     */
    public function abilities(?Authenticatable $user, mixed $scope = null): array
    {
        if ($user === null) {
            return [];
        }

        $abilities = $this->held($user, null);

        foreach (self::chain($scope) as $link) {
            $abilities += $this->held($user, $link);
        }

        $abilities = array_map(strval(...), array_keys($abilities));
        sort($abilities);

        return $abilities;
    }

    public function isSuperAdmin(?Authenticatable $user): bool
    {
        return $user !== null && isset($this->held($user, null)[self::SUPER_ADMIN]);
    }

    /**
     * The fake remembers no answers, so there is nothing to forget.
     */
    public function forget(?Authenticatable $user = null): void {}

    /**
     * The identifiers of the users granted the ability globally, or made
     * super-admins, through this fake. `FakeRoles::holders()` adds them.
     *
     * @return list<int|string>
     */
    public function holders(string $ability): array
    {
        $holders = [];

        foreach ($this->granted as $who => $scopes) {
            $global = $scopes[self::scopeKey(null)] ?? [];

            if (isset($this->users[$who]) && (isset($global[$ability]) || isset($global[self::SUPER_ADMIN]))) {
                $holders[] = $this->users[$who];
            }
        }

        return $holders;
    }

    /**
     * The abilities the user holds in exactly that scope, granted or through
     * a role.
     *
     * @return array<string, true>
     */
    private function held(Authenticatable $user, mixed $scope): array
    {
        $abilities = $this->granted[self::userKey($user)][self::scopeKey($scope)] ?? [];

        foreach ($this->roles()?->abilitiesIn($user, $scope) ?? [] as $ability) {
            $abilities[$ability] = true;
        }

        return $abilities;
    }

    private function roles(): ?FakeRoles
    {
        // Typed as the contract: Larastan may narrow app() to the bound class, which is never the fake.
        /** @var Roles $roles */
        $roles = app(Roles::class);

        return $roles instanceof FakeRoles ? $roles : null;
    }

    /**
     * The scope and the parents whose grants also apply in it.
     *
     * @return list<mixed>
     */
    private static function chain(mixed $scope): array
    {
        if ($scope === null) {
            return [];
        }

        return $scope instanceof Scope ? ScopeChain::of($scope) : [$scope];
    }

    private static function isOfType(Model $scope, string $type): bool
    {
        return $scope::class === $type || $scope->getMorphClass() === $type;
    }

    private static function userKey(Authenticatable $user): string
    {
        $identifier = $user->getAuthIdentifier();

        return is_int($identifier) || is_string($identifier) ? 'user:'.$identifier : 'object:'.spl_object_id($user);
    }

    /**
     * A stable name for a scope: its morph class and key when saved, so the
     * same record loaded twice matches, otherwise the instance.
     */
    private static function scopeKey(mixed $scope): string
    {
        if ($scope === null) {
            return 'global';
        }

        if ($scope instanceof Model) {
            $key = $scope->getKey();

            if (is_int($key) || is_string($key)) {
                return $scope->getMorphClass().'#'.$key;
            }
        }

        if (is_object($scope)) {
            return $scope::class.'#object:'.spl_object_id($scope);
        }

        return is_scalar($scope) ? get_debug_type($scope).':'.$scope : get_debug_type($scope);
    }
}
