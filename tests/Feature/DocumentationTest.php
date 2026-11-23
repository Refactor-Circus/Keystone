<?php

declare(strict_types=1);

function packageFile(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

function unreleasedChangelog(): string
{
    $changelog = packageFile('CHANGELOG.md');
    $start = strpos($changelog, '## [Unreleased]');

    expect($start)->not->toBeFalse();

    $section = substr($changelog, (int) $start + strlen('## [Unreleased]'));
    $next = strpos($section, "\n## [");

    return $next === false ? $section : substr($section, 0, $next);
}

it('keeps the readme free of the dashboard hook and the ui config key', function (): void {
    $readme = packageFile('README.md');

    expect(stripos($readme, 'atrium'))->toBeFalse()
        ->and($readme)->not->toContain('registerAtriumPlugin')
        ->and($readme)->not->toContain("'ui' =>");
});

it('shows only the remaining helpers and config keys in the readme examples', function (): void {
    $readme = packageFile('README.md');

    expect($readme)
        ->toContain('$this->registerPolicies();')
        ->toContain('$this->registerMcpServer();')
        ->toContain('$this->registerCortex();')
        ->toContain('$this->loadHistoryRoutes();')
        ->toContain("'authorization' =>")
        ->toContain("'policies' =>")
        ->toContain("'routes' =>")
        ->toContain("'mcp' =>")
        ->toContain("'cortex' =>");
});

it('records the breaking changes of v0.2.0 under unreleased', function (): void {
    $unreleased = unreleasedChangelog();

    expect($unreleased)
        ->toContain('### Breaking')
        ->toContain('v0.2.0')
        ->toContain('registerAtriumPlugin()')
        ->toContain('ui.enabled')
        ->toContain("Surface::route('atrium.', 'atrium')")
        ->toContain('Foundry')
        ->toContain('Guardian');
});

it('aliases the main branch to 0.2.x-dev', function (): void {
    /** @var array{extra: array{branch-alias: array{dev-main: string}}} $composer */
    $composer = json_decode(packageFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['branch-alias']['dev-main'])->toBe('0.2.x-dev');
});
