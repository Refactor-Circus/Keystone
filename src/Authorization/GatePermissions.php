<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Authorization\Contracts\Scope;

/**
 * The permissions while no role store is installed: the host's Gate decides.
 *
 * An ability is allowed when a `Scope` in the chain grants it by itself, or
 * when the Gate allows it (`Gate::define()`, `Gate::before()`, a policy). The
 * fallback knows no roles, so it cannot list abilities and remembers nothing.
 */
final class GatePermissions implements Permissions
{
    /**
     * The checks being answered right now, keyed by user, ability and scope,
     * so a Gate callback that asks again gets false instead of a loop.
     *
     * @var array<string, true>
     */
    private array $running = [];

    public function __construct(private readonly Gate $gate) {}

    public function allows(?Authenticatable $user, string $ability, mixed $scope = null): bool
    {
        $key = $this->key($user, $ability, $scope);

        if (isset($this->running[$key])) {
            return false;
        }

        $this->running[$key] = true;

        try {
            if ($user !== null && $scope instanceof Scope) {
                foreach (ScopeChain::of($scope) as $link) {
                    if ($link->grantsByItself($user, $ability)) {
                        return true;
                    }
                }
            }

            return $this->gate->forUser($user)->allows($ability, $scope === null ? [] : [$scope]);
        } finally {
            unset($this->running[$key]);
        }
    }

    /**
     * Null when the user holds the ability with no scope, otherwise none: the
     * Gate cannot say which scopes would allow it.
     */
    public function scopesWith(?Authenticatable $user, string $ability, string $scopeType): ?array
    {
        return $this->allows($user, $ability) ? null : [];
    }

    public function abilities(?Authenticatable $user, mixed $scope = null): ?array
    {
        return null;
    }

    public function isSuperAdmin(?Authenticatable $user): bool
    {
        return $user !== null && $this->allows($user, self::SUPER_ADMIN);
    }

    public function forget(?Authenticatable $user = null): void {}

    private function key(?Authenticatable $user, string $ability, mixed $scope): string
    {
        $who = $user === null ? 'guest' : $this->identify($user, $user->getAuthIdentifier());
        $where = $scope instanceof Model ? $this->identify($scope, $scope->getKey()) : $this->identify($scope, null);

        return $who."\0".$ability."\0".$where;
    }

    /**
     * A stable name for a user or scope: its class and key when it has one,
     * so the same record loaded twice matches, otherwise the instance.
     */
    private function identify(mixed $value, mixed $key): string
    {
        if (is_object($value)) {
            return $value::class.'#'.(is_int($key) || is_string($key) ? $key : 'object:'.spl_object_id($value));
        }

        if (is_scalar($value)) {
            return get_debug_type($value).':'.$value;
        }

        return get_debug_type($value);
    }
}
