<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Mcp;

use Laravel\Mcp\Server as McpServer;
use Laravel\Mcp\Server\ServerContext;
use RefactorCircus\Keystone\Cortex\CortexIntegration;
use RefactorCircus\Keystone\Mcp\Tools\ListHistoryTool;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * Base class for every package's MCP server.
 *
 * A server lists its tools in a `TOOLS` constant, offers them behind
 * ToolSearch, and is named on its package's definition, which also registers
 * the tools with Cortex when Cortex is installed:
 *
 *     public const array TOOLS = [ListProductsTool::class, ...];
 *
 *     protected array $tools = [ToolSearch::class => self::TOOLS];
 */
abstract class Server extends McpServer
{
    /**
     * The tools less any history tool, at any depth (tool search nests them).
     *
     * @param  array<array-key, mixed>  $tools
     * @return array<array-key, mixed>
     */
    public static function withoutHistory(array $tools): array
    {
        $kept = [];

        foreach ($tools as $key => $tool) {
            if (is_array($tool)) {
                $kept[$key] = self::withoutHistory($tool);
            } elseif (! (is_string($tool) && is_a($tool, ListHistoryTool::class, true))) {
                $kept[$key] = $tool;
            }
        }

        return array_is_list($tools) ? array_values($kept) : $kept;
    }

    /**
     * Serve Cortex's published instructions override, when Cortex is
     * installed and one is published, in place of the declared ones, and the
     * history tool only while the package audits.
     */
    public function createContext(): ServerContext
    {
        $package = app(PackageRegistry::class)->for(static::class);

        // The history tool only while the package audits (see Package::audits()).
        if ($package !== null && ! $package->audits()) {
            $this->tools = self::withoutHistory($this->tools);
        }

        $context = parent::createContext();
        $override = $package === null ? null : CortexIntegration::for($package)->instructions();

        if ($override !== null) {
            $context->instructions = $override;
        }

        return $context;
    }
}
