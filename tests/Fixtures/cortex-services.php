<?php

declare(strict_types=1);

/*
 * Stand-ins for Cortex's MCP server services, which Keystone cannot install
 * (Cortex requires Keystone). The registry is declared where Cortex keeps it
 * since its `ConcreteServer` rename, the overrides where it kept them before,
 * so one process sees both lookups.
 */

namespace RefactorCircus\Cortex\Domains\ConcreteServer\Services {
    final class McpServerRegistry {}
}

namespace RefactorCircus\Cortex\Domains\McpServer\Services {
    final class McpInstructionOverrides {}
}
