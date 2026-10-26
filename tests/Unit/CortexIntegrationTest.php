<?php

declare(strict_types=1);

use RefactorCircus\Keystone\Cortex\CortexIntegration;

require_once dirname(__DIR__).'/Fixtures/cortex-services.php';

it('looks up a cortex server service under the ConcreteServer domain', function (): void {
    $service = new ReflectionMethod(CortexIntegration::class, 'serverService');

    expect($service->invoke(null, 'McpServerRegistry'))
        ->toBe('RefactorCircus\Cortex\Domains\ConcreteServer\Services\McpServerRegistry');
});

it('falls back to the McpServer domain for a cortex release before the rename', function (): void {
    $service = new ReflectionMethod(CortexIntegration::class, 'serverService');

    expect($service->invoke(null, 'McpInstructionOverrides'))
        ->toBe('RefactorCircus\Cortex\Domains\McpServer\Services\McpInstructionOverrides');
});

it('names the ConcreteServer class when neither domain has the service', function (): void {
    $service = new ReflectionMethod(CortexIntegration::class, 'serverService');

    expect($service->invoke(null, 'MissingService'))
        ->toBe('RefactorCircus\Cortex\Domains\ConcreteServer\Services\MissingService');
});
