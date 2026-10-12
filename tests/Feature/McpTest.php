<?php

declare(strict_types=1);

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool as AiTool;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Tools\Request as AiRequest;
use RefactorCircus\Keystone\Cortex\CortexIntegration;
use RefactorCircus\Keystone\Packages\PackageRegistry;
use RefactorCircus\Keystone\Support\Surface;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Mcp\AcmeServer;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Mcp\Tools\ProbeTool;

it('marks mcp calls with the mcp surface', function (): void {
    AcmeServer::tool(ProbeTool::class)
        ->assertOk()
        ->assertStructuredContent(['surface' => 'mcp', 'package' => 'acme']);
});

it('surfaces package exceptions to the caller', function (): void {
    AcmeServer::tool(ProbeTool::class, ['fail' => true])
        ->assertHasErrors(['Widgets are sold out.']);
});

it('keeps the declared description while cortex is absent', function (): void {
    expect(app(ProbeTool::class)->description())->toBe('Report the surface the call came through.');
});

it('keeps the cortex surface when an agent calls the tool', function (): void {
    $surface = app(Surface::class);
    $surface->enter('cortex');

    AcmeServer::tool(ProbeTool::class)->assertStructuredContent(['surface' => 'cortex', 'package' => 'acme']);

    $surface->leave();
});

it('marks agent tool calls with the cortex surface, resolving tool names once', function (): void {
    $integration = CortexIntegration::for(app(PackageRegistry::class)->get('acme'));
    (new ReflectionMethod($integration, 'recordAgentCalls'))->invoke($integration);

    $resolved = 0;
    app()->resolving(ProbeTool::class, function () use (&$resolved): void {
        $resolved++;
    });

    $agent = Mockery::mock(Agent::class);
    $tool = new class(app(ProbeTool::class)->name()) implements AiTool
    {
        public function __construct(private readonly string $name) {}

        public function name(): string
        {
            return $this->name;
        }

        public function description(): string
        {
            return '';
        }

        public function handle(AiRequest $request): string
        {
            return '';
        }

        /**
         * @return array<string, mixed>
         */
        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };
    $resolved = 0;
    $surface = app(Surface::class);

    foreach (range(1, 3) as $call) {
        event(new InvokingTool('i', 't'.$call, $agent, $tool, []));
        expect($surface->current())->toBe('cortex');
        event(new ToolInvoked('i', 't'.$call, $agent, $tool, [], null, 0.0));
        expect($surface->current())->not->toBe('cortex');
    }

    expect($resolved)->toBe(1);
});
