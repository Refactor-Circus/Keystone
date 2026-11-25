<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Authorization\Contracts\Roles;

/**
 * An in-memory role store for a package's test suite, bound in place of
 * `NullRoles`. Define the roles a test needs, then assign them; an installed
 * `FakePermissions` lets each user use what their roles grant, and
 * `holders()` includes the users it grants to globally.
 *
 * A role is global, or defined for one scope type (a model class or morph
 * alias) and held in a scope of that type. A saved model loaded twice is the
 * same scope.
 *
 *     FakeRoles::install()
 *         ->define('admin', ['acme.widgets.view', 'acme.widgets.edit'])
 *         ->define('owner', ['acme.teams.manage'], Organization::class)
 *         ->assign($user, 'owner', $organization);
 */
final class FakeRoles implements Roles
{
    /**
     * The abilities each role grants, by role and scope type ('' for global).
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $definitions = [];

    /**
     * The roles held, by user key and scope key.
     *
     * @var array<string, array<string, array<string, true>>>
     */
    private array $assignments = [];

    /**
     * The user's identifier, by user key.
     *
     * @var array<string, int|string>
     */
    private array $users = [];

    /**
     * The scopes roles are held in, by scope key.
     *
     * @var array<string, Model>
     */
    private array $scopes = [];

    /**
     * Bind a new fake as the application's `Roles`.
     */
    public static function install(): self
    {
        $fake = new self;

        app()->instance(Roles::class, $fake);

        return $fake;
    }

    /**
     * Define a role and the abilities it grants: global when `$scopeType` is
     * null, otherwise held in scopes of that model class or morph alias.
     * Defining it again for the same type replaces its abilities.
     *
     * @param  list<string>  $abilities
     */
    public function define(string $role, array $abilities, ?string $scopeType = null): self
    {
        $this->definitions[$role][$scopeType ?? ''] = $abilities;

        return $this;
    }

    public function available(): bool
    {
        return true;
    }

    public function exists(string $role, ?Model $scope = null): bool
    {
        return $this->grantsOf($role, $scope) !== null;
    }

    /**
     * True once the user holds the role in that scope (already holding it
     * counts); false when the role is not defined for it.
     */
    public function assign(Authenticatable $user, string $role, ?Model $scope = null): bool
    {
        if (! $this->exists($role, $scope)) {
            return false;
        }

        $who = self::userKey($user);
        $where = self::scopeKey($scope);
        $identifier = $user->getAuthIdentifier();

        if (is_int($identifier) || is_string($identifier)) {
            $this->users[$who] = $identifier;
        }

        if ($scope !== null) {
            $this->scopes[$where] = $scope;
        }

        $this->assignments[$who][$where][$role] = true;

        return true;
    }

    public function revoke(Authenticatable $user, ?Model $scope = null): void
    {
        unset($this->assignments[self::userKey($user)][self::scopeKey($scope)]);
    }

    public function revokeEverywhere(Authenticatable $user): void
    {
        unset($this->assignments[self::userKey($user)]);
    }

    public function of(Authenticatable $user, ?Model $scope = null): array
    {
        $roles = array_map(strval(...), array_keys($this->assignments[self::userKey($user)][self::scopeKey($scope)] ?? []));
        sort($roles);

        return $roles;
    }

    public function holders(string $ability): array
    {
        $holders = [];

        foreach ($this->assignments as $who => $scopes) {
            foreach (array_keys($scopes[self::scopeKey(null)] ?? []) as $role) {
                $grants = $this->grantsOf((string) $role, null) ?? [];

                if (isset($this->users[$who]) && (in_array($ability, $grants, true) || in_array(Permissions::SUPER_ADMIN, $grants, true))) {
                    $holders[] = $this->users[$who];
                }
            }
        }

        // Typed as the contract: Larastan may narrow app() to the bound class, which is never the fake.
        /** @var Permissions $permissions */
        $permissions = app(Permissions::class);

        if ($permissions instanceof FakePermissions) {
            $holders = [...$holders, ...$permissions->holders($ability)];
        }

        $holders = array_values(array_unique($holders, SORT_REGULAR));
        sort($holders);

        return $holders;
    }

    /**
     * The abilities the user's roles grant in exactly that scope, for
     * `FakePermissions`.
     *
     * @return list<string>
     */
    public function abilitiesIn(Authenticatable $user, mixed $scope): array
    {
        if ($scope !== null && ! $scope instanceof Model) {
            return [];
        }

        $abilities = [];

        foreach (array_keys($this->assignments[self::userKey($user)][self::scopeKey($scope)] ?? []) as $role) {
            $abilities = [...$abilities, ...($this->grantsOf((string) $role, $scope) ?? [])];
        }

        return array_values(array_unique($abilities));
    }

    /**
     * The keys of the scopes of that type in which the user's roles grant the
     * ability, for `FakePermissions::scopesWith()`.
     *
     * @return list<int|string>
     */
    public function scopesGranting(Authenticatable $user, string $ability, string $scopeType): array
    {
        $keys = [];

        foreach ($this->assignments[self::userKey($user)] ?? [] as $where => $roles) {
            $scope = $this->scopes[$where] ?? null;
            $key = $scope?->getKey();

            if ($scope === null || ! self::isOfType($scope, $scopeType) || ! (is_int($key) || is_string($key))) {
                continue;
            }

            foreach (array_keys($roles) as $role) {
                if (in_array($ability, $this->grantsOf((string) $role, $scope) ?? [], true)) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys, SORT_REGULAR));
    }

    /**
     * The abilities the role grants in that scope, or null when it is not
     * defined for it.
     *
     * @return list<string>|null
     */
    private function grantsOf(string $role, ?Model $scope): ?array
    {
        if ($scope === null) {
            return $this->definitions[$role][''] ?? null;
        }

        foreach ($this->definitions[$role] ?? [] as $type => $abilities) {
            if ($type !== '' && self::isOfType($scope, $type)) {
                return $abilities;
            }
        }

        return null;
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
    private static function scopeKey(?Model $scope): string
    {
        if ($scope === null) {
            return 'global';
        }

        $key = $scope->getKey();

        return is_int($key) || is_string($key) ? $scope->getMorphClass().'#'.$key : $scope::class.'#object:'.spl_object_id($scope);
    }
}
