<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Cortex;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\ToolNameResolver;
use Laravel\Mcp\Server\Tool;
use RefactorCircus\Cortex\CortexServiceProvider;
use RefactorCircus\Cortex\Domains\ConcreteServer\Services\McpInstructionOverrides;
use RefactorCircus\Cortex\Domains\ConcreteServer\Services\McpServerRegistry;
use RefactorCircus\Cortex\Domains\Tool\Services\ToolDescriptionOverrides;
use RefactorCircus\Cortex\Domains\Tool\Services\ToolRegistry;
use RefactorCircus\Keystone\Mcp\Server;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Support\Surface;

/**
 * Connects a package's MCP server to Cortex, when Cortex is installed.
 *
 * - The server is registered with Cortex, so its instructions can be
 *   overridden with versioned, publishable content.
 * - Each tool is registered in Cortex's tool registry under its own name,
 *   tagged (by default with the server's name), so Cortex agents can use it,
 *   and its description can be overridden the same way.
 * - Changes an agent makes through one of the tools record `cortex` as their
 *   surface, so the audit log tells agents and people apart.
 *
 * Cortex is optional. Nothing here runs unless its service provider is
 * loaded and the package's `cortex.enabled` config key is true, and every
 * Cortex class is referenced only behind that check — so servers and tools
 * cannot extend Cortex's own base classes, and do its lookups here instead.
 */
final readonly class CortexIntegration
{
    /**
     * Where Cortex keeps its MCP server services: the `ConcreteServer` domain,
     * or the `McpServer` domain in releases before the rename.
     */
    private const string SERVER_SERVICES = 'RefactorCircus\\Cortex\\Domains\\ConcreteServer\\Services\\';

    private const string LEGACY_SERVER_SERVICES = 'RefactorCircus\\Cortex\\Domains\\McpServer\\Services\\';

    public function __construct(
        private Application $app,
        private Config $config,
        private Package $package,
    ) {}

    public static function for(Package $package): self
    {
        return new self(app(Application::class), app(Config::class), $package);
    }

    public function active(): bool
    {
        return $this->package->server !== null
            && $this->config->get($this->package->configKey('cortex.enabled'), true) === true
            && class_exists(CortexServiceProvider::class)
            && $this->app->getProvider(CortexServiceProvider::class) !== null;
    }

    /**
     * Register with Cortex's registries as they are first resolved, so an
     * application that never touches Cortex pays nothing.
     */
    public function register(): void
    {
        if (! $this->active() || $this->package->server === null) {
            return;
        }

        $server = $this->package->server;

        $this->app->afterResolving(self::serverService('McpServerRegistry'), function (object $servers) use ($server): void {
            /** @var McpServerRegistry $servers */
            if (! $servers->has($this->serverName())) {
                $servers->register($this->serverName(), $server);
            }
        });

        $this->app->afterResolving(ToolRegistry::class, function (ToolRegistry $tools, Container $container): void {
            foreach ($this->tools() as $class) {
                $tool = $container->make($class);

                if ($tool instanceof Tool && ! $tools->has($tool->name())) {
                    $tools->register($tool->name(), $class, $this->tags());
                }
            }
        });

        $this->recordAgentCalls();
    }

    /**
     * The name the server is registered under in Cortex.
     */
    public function serverName(): string
    {
        return $this->config->string($this->package->configKey('cortex.server'), $this->package->key);
    }

    /**
     * The tags the tools are grouped under in Cortex.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        /** @var list<string> $tags */
        $tags = $this->config->get($this->package->configKey('cortex.tags'), [$this->serverName()]);

        return $tags;
    }

    /**
     * The tools offered to Cortex: all of the server's `TOOLS`, or those
     * named in the package's `cortex.tools` config key.
     *
     * @return array<int, class-string<Tool>>
     */
    public function tools(): array
    {
        $all = $this->allTools();

        /** @var array<int, string>|null $only */
        $only = $this->config->get($this->package->configKey('cortex.tools'));

        if ($only === null) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn (string $class): bool => in_array($this->app->make($class)->name(), $only, true),
        ));
    }

    /**
     * The published instructions override for the server, if any.
     */
    public function instructions(): ?string
    {
        if (! $this->active() || $this->package->server === null) {
            return null;
        }

        /** @var McpServerRegistry $servers */
        $servers = $this->app->make(self::serverService('McpServerRegistry'));
        $name = $servers->nameFor($this->package->server);

        if ($name === null) {
            return null;
        }

        /** @var McpInstructionOverrides $overrides */
        $overrides = $this->app->make(self::serverService('McpInstructionOverrides'));

        return $overrides->for($name);
    }

    /**
     * The published description override for a tool, if any.
     */
    public function description(string $tool): ?string
    {
        return $this->active() ? $this->app->make(ToolDescriptionOverrides::class)->for($tool) : null;
    }

    /**
     * The class of one of Cortex's MCP server services, in the `ConcreteServer`
     * domain, or in the `McpServer` domain when only that exists, so Keystone
     * works with Cortex before and after the rename.
     */
    private static function serverService(string $service): string
    {
        $legacy = self::LEGACY_SERVER_SERVICES.$service;

        return ! class_exists(self::SERVER_SERVICES.$service) && class_exists($legacy)
            ? $legacy
            : self::SERVER_SERVICES.$service;
    }

    /**
     * Mark changes an agent makes through one of the package's tools with
     * the `cortex` surface, for exactly as long as the tool runs.
     */
    private function recordAgentCalls(): void
    {
        if (! class_exists(InvokingTool::class)) {
            return;
        }

        $events = $this->app->make(Dispatcher::class);

        // Every package listens to every agent tool call, so the names of
        // the server's tools are resolved once, on the first call, and kept:
        // they only change on deploy.
        $names = null;

        $owns = function (string $tool) use (&$names): bool {
            $names ??= $this->toolNames();

            return isset($names[$tool]);
        };

        $events->listen(InvokingTool::class, function (InvokingTool $event) use ($owns): void {
            if ($owns(ToolNameResolver::resolve($event->tool))) {
                $this->app->make(Surface::class)->enter('cortex');
            }
        });

        $leave = function (ToolInvoked|ToolFailed $event) use ($owns): void {
            if ($owns(ToolNameResolver::resolve($event->tool))) {
                $this->app->make(Surface::class)->leave();
            }
        };

        $events->listen(ToolInvoked::class, $leave);
        $events->listen(ToolFailed::class, $leave);
    }

    /**
     * The names of every tool on the server, as a set. The history tool is
     * included whether or not the package audits: a tool that is not
     * offered is never invoked, so it cannot claim a call by mistake.
     *
     * @return array<string, true>
     */
    private function toolNames(): array
    {
        $names = [];

        foreach ($this->serverTools() as $class) {
            $tool = $this->app->make($class);

            if ($tool instanceof Tool) {
                $names[$tool->name()] = true;
            }
        }

        return $names;
    }

    /**
     * @return array<int, class-string<Tool>>
     */
    private function serverTools(): array
    {
        $server = $this->package->server;

        if ($server === null || ! defined($server.'::TOOLS')) {
            return [];
        }

        /** @var array<int, class-string<Tool>> $tools */
        $tools = constant($server.'::TOOLS');

        return $tools;
    }

    /**
     * @return array<int, class-string<Tool>>
     */
    private function allTools(): array
    {
        $tools = $this->serverTools();

        // The history tool only while the package audits, as on its server.
        return $this->package->audits() ? $tools : array_values(Server::withoutHistory($tools));
    }
}
