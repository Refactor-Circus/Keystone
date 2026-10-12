<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * Which surface the current call came through: `atrium`, `http`, `mcp`,
 * `cortex`, `cli`, `code`, or one a package names for its own routes (`scim`, `web`).
 *
 * The MCP request base marks its calls with `using('mcp', ...)`, and Cortex
 * agent tool calls are marked `cortex`. Otherwise the
 * route decides: `atrium.*` routes are the dashboard and a package's `{key}.*`
 * routes its JSON API, unless a package registered a more specific prefix.
 */
final class Surface
{
    /**
     * @var list<string>
     */
    private array $stack = [];

    /**
     * @var array<string, string>
     */
    private array $routes = [];

    /**
     * The route prefixes, most specific first, built on first use and
     * rebuilt when a prefix is named or a package registers.
     *
     * @var array<string, string>|null
     */
    private ?array $prefixes = null;

    private int $packageCount = 0;

    public function __construct(
        private readonly Application $app,
        private readonly PackageRegistry $packages,
    ) {}

    /**
     * Name the surface for routes whose name starts with `$prefix`.
     */
    public function route(string $prefix, string $surface): self
    {
        $this->routes[$prefix] = $surface;
        $this->prefixes = null;

        return $this;
    }

    /**
     * Mark everything inside the callback as coming from `$surface`.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function using(string $surface, callable $callback): mixed
    {
        $this->enter($surface);

        try {
            return $callback();
        } finally {
            $this->leave();
        }
    }

    /**
     * Mark everything from now until the matching `leave()` as coming from
     * `$surface`, for callers that only hear when a call starts and ends.
     */
    public function enter(string $surface): void
    {
        $this->stack[] = $surface;
    }

    public function leave(): void
    {
        array_pop($this->stack);
    }

    public function current(): string
    {
        if ($this->stack !== []) {
            return $this->stack[array_key_last($this->stack)];
        }

        $name = $this->request()?->route()?->getName();

        if (is_string($name)) {
            $surface = $this->forRoute($name);

            if ($surface !== null) {
                return $surface;
            }
        }

        return $this->app->runningInConsole() ? 'cli' : 'code';
    }

    /**
     * The signed-in user, from the default guard - also outside HTTP, e.g.
     * code running for a queued job that authenticated with `Auth::login()`.
     */
    public function actor(): ?Model
    {
        $user = $this->app->make('auth')->user();

        return $user instanceof Model ? $user : null;
    }

    public function request(): ?Request
    {
        $request = $this->app->bound('request') ? $this->app->make('request') : null;

        return $request instanceof Request ? $request : null;
    }

    private function forRoute(string $name): ?string
    {
        foreach ($this->prefixes() as $prefix => $surface) {
            if (str_starts_with($name, $prefix)) {
                return $surface;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function prefixes(): array
    {
        $packages = $this->packages->all();

        if ($this->prefixes !== null && count($packages) === $this->packageCount) {
            return $this->prefixes;
        }

        $prefixes = $this->routes;

        foreach ($packages as $package) {
            $prefixes[$package->key.'.'] ??= 'http';
        }

        $prefixes['atrium.'] = $this->routes['atrium.'] ?? 'atrium';

        // The most specific prefix wins: `roster.scim.` before `roster.`.
        uksort($prefixes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $this->packageCount = count($packages);

        return $this->prefixes = $prefixes;
    }
}
