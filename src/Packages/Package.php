<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Packages;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;

/**
 * One package of the suite, as the shared runtime knows it.
 *
 * The key names the package's config file, route names and audit source
 * (`showroom`); the namespace is how a class is traced back to the package
 * that owns it, so base classes find their package without being told.
 *
 *     Package::make('showroom', 'RefactorCircus\Showroom')
 *         ->label('Showroom')
 *         ->server(ShowroomServer::class);
 */
final class Package
{
    public private(set) string $label;

    /**
     * The package's MCP server, when it has one.
     *
     * @var class-string<Server>|null
     */
    public private(set) ?string $server = null;

    /**
     * Whether calls are authorized through the Gate when the package's
     * `authorization` config key is not set.
     */
    public private(set) bool $authorization = false;

    /**
     * The package's own rule for who may read its history, in place of the
     * subject's `view` policy and the `viewAuditLog` ability.
     *
     * @var (Closure(?Authenticatable, Model|class-string<Model>|null): bool)|null
     */
    private ?Closure $historyCheck = null;

    private function __construct(
        public private(set) string $key,
        public private(set) string $namespace,
    ) {
        $this->namespace = trim($namespace, '\\');
        $this->label = Str::headline($key);
    }

    public static function make(string $key, string $namespace): self
    {
        return new self($key, $namespace);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  class-string<Server>  $server
     */
    public function server(string $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function authorization(bool $authorization = true): self
    {
        $this->authorization = $authorization;

        return $this;
    }

    /**
     * Decide who may read the package's history with the package's own rule,
     * such as the permission that guards the rest of its API, for a package
     * that does not authorize through policies. The check receives the user
     * and the subject asked about: a model, its class once the model is gone,
     * or null for the whole history.
     *
     * @param  Closure(?Authenticatable, Model|class-string<Model>|null): bool  $check
     */
    public function authorizeHistory(Closure $check): self
    {
        $this->historyCheck = $check;

        return $this;
    }

    public function decidesHistory(): bool
    {
        return $this->historyCheck !== null;
    }

    /**
     * Whether the package's own history rule lets the user read about the
     * subject. Without a rule, it does.
     *
     * @param  Model|class-string<Model>|null  $subject
     */
    public function allowsHistory(?Authenticatable $user, Model|string|null $subject = null): bool
    {
        return $this->historyCheck === null || ($this->historyCheck)($user, $subject) === true;
    }

    /**
     * Whether the package records to and reads from the suite's audit log:
     * only while one is installed (refactor-circus/keen), and unless the
     * package's `audit.enabled` config key turns it off. While it does not,
     * its history route and tool are not offered at all.
     */
    public function audits(): bool
    {
        return app(Repository::class)->get($this->configKey('audit.enabled'), true) !== false
            && app(AuditTrail::class)->available();
    }

    /**
     * The full config key for a path under the package's config file.
     */
    public function configKey(string $path): string
    {
        return $this->key.'.'.$path;
    }

    /**
     * Whether a class lives in the package's namespace.
     *
     * @param  object|class-string  $class
     */
    public function owns(object|string $class): bool
    {
        $name = ltrim(is_object($class) ? $class::class : $class, '\\');

        return str_starts_with($name, $this->namespace.'\\');
    }
}
