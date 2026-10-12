<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Testing;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ExecuteTools;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Transport\JsonRpcResponse;
use PHPUnit\Framework\Assert;
use RefactorCircus\Keystone\Mcp\Server;

/**
 * Call a package's MCP tool in a test the way a client reaches it: through
 * the server's `execute_tools` tool search when it has one, else directly,
 * as the signed-in user.
 *
 * The single result is lifted back to the top level, so the usual
 * TestResponse assertions apply to the tool that ran. A structured answer is
 * checked against the tool's output schema - a missing schema or a field it
 * does not declare fails the test - unless `$checkSchema` is false.
 *
 *     McpTools::call(ShowroomServer::class, ListProductsTool::class, ['per_page' => 5])->assertOk();
 */
final class McpTools
{
    /**
     * @param  class-string<Server>  $server
     * @param  class-string<Tool>  $tool
     * @param  array<string, mixed>  $arguments
     */
    public static function call(string $server, string $tool, array $arguments = [], bool $checkSchema = true): TestResponse
    {
        $entryPoints = (new $server(new FakeTransporter))->createContext()->tools();

        $execute = $entryPoints->first(fn (mixed $candidate): bool => $candidate instanceof ExecuteTools);

        /** @var Tool $primitive */
        $primitive = app($tool);

        // A server without tool search answers its tools directly.
        if (! $execute instanceof ExecuteTools) {
            $response = $server::tool($tool, $arguments);
            /** @var array{result?: array{structuredContent?: array<string, mixed>, isError?: bool}} $payload */
            $payload = (fn (): array => $this->response->toArray())->call($response);
            $result = $payload['result'] ?? [];

            if ($checkSchema && isset($result['structuredContent']) && ($result['isError'] ?? false) !== true) {
                self::assertMatchesSchema($primitive, $result['structuredContent']);
            }

            return $response;
        }

        $envelope = $execute->handle(new Request([
            'calls' => [['name' => $primitive->name(), 'arguments' => $arguments]],
        ]));

        $first = $envelope[0] ?? null;

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(
            (string) $first?->content(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        /** @var array<string, mixed> $result */
        $result = $decoded['results'][0] ?? [];

        if ($checkSchema && isset($result['structuredContent']) && ($result['isError'] ?? false) !== true) {
            self::assertMatchesSchema($primitive, (array) $result['structuredContent']);
        }

        /** @var array<int, array<string, mixed>> $content */
        $content = $result['content'] ?? [];

        // Since laravel/mcp 1.0.2, execute_tools leaves out the JSON text copy of
        // structured content that a direct tools/call carries, so put it back.
        if (isset($result['structuredContent'])) {
            $mirror = json_encode($result['structuredContent'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! in_array($mirror, array_column($content, 'text'), true)) {
                $content[] = ['type' => 'text', 'text' => $mirror];
            }
        }

        return new TestResponse($primitive, JsonRpcResponse::result(1, array_filter([
            'content' => $content,
            'structuredContent' => $result['structuredContent'] ?? null,
            'isError' => $result['isError'] ?? false,
        ], fn (mixed $value): bool => $value !== null)));
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private static function assertMatchesSchema(Tool $tool, array $content): void
    {
        /** @var array<string, mixed> $schema */
        $schema = $tool->toArray()['outputSchema'] ?? [];

        Assert::assertNotSame([], $schema, $tool->name().' answers with structured content but declares no outputSchema().');
        Assert::assertSame([], StructuredSchema::violations($schema, $content), $tool->name().' answered outside its outputSchema().');
    }
}
