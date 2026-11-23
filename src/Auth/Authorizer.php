<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Permissions;
use RefactorCircus\Keystone\Packages\Package;

/**
 * How a package's JSON API and MCP tools decide whether the caller may act.
 *
 * With the package's `authorization` config key on, every call acts as the
 * authenticated user and goes through the Gate, so the policies in its
 * `policies` key apply. With it off, the route middleware is the only check,
 * as suits an operator API or a trusted server-to-server integration.
 */
final class Authorizer
{
    public function __construct(
        private readonly Config $config,
        private readonly Gate $gate,
        private readonly Package $package,
    ) {}

    public static function for(Package $package): self
    {
        return new self(app(Config::class), app(Gate::class), $package);
    }

    public function enabled(): bool
    {
        return $this->config->get($this->package->configKey('authorization'), $this->package->authorization) === true;
    }

    /**
     * Whether the caller may use the surface at all.
     */
    public function authenticated(?Authenticatable $user): bool
    {
        return ! $this->enabled() || $user instanceof Model;
    }

    /**
     * The model calls act as, or null when authorization is off.
     */
    public function actor(?Authenticatable $user): ?Model
    {
        return $this->enabled() && $user instanceof Model ? $user : null;
    }

    /**
     * Whether the user may perform an ability on a model, or on a model class
     * for abilities such as `viewAny` and `create`.
     *
     * @param  Model|class-string<Model>  $subject
     * @param  array<int, mixed>  $arguments
     */
    public function can(?Authenticatable $user, string $ability, Model|string $subject, array $arguments = []): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        return $user instanceof Model && $this->gate->forUser($user)->allows($ability, [$subject, ...$arguments]);
    }

    /**
     * Whether the user may use a named ability (`acme.widgets.view`), globally
     * or in a scope, as the bound `Permissions` answers it. A guest is asked
     * about too.
     *
     * Unlike `can()`, which asks the Gate about a model so its policy decides,
     * this asks the role store; neither calls the other. The binding is looked
     * up on every call, so one replaced later (a test fake) still answers.
     */
    public function allows(?Authenticatable $user, string $ability, mixed $scope = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        return app(Permissions::class)->allows($user, $ability, $scope);
    }
}
