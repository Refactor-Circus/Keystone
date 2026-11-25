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

it('records the breaking changes of v2.0.0 under unreleased', function (): void {
    $unreleased = unreleasedChangelog();

    expect($unreleased)
        ->toContain('### Breaking')
        ->toContain('v2.0.0')
        ->toContain('registerAtriumPlugin()')
        ->toContain('ui.enabled')
        ->toContain("Surface::route('atrium.', 'atrium')")
        ->toContain('Foundry');
});

it('keeps only Foundry on the 0.1 line', function (): void {
    $staying = array_filter(
        explode("\n", unreleasedChangelog()),
        fn (string $line): bool => str_contains($line, '^0.1'),
    );

    expect($staying)->not->toBeEmpty();

    foreach ($staying as $line) {
        expect($line)->toContain('Foundry')->not->toContain('Guardian');
    }
});

it('documents the authorization contract in the readme', function (): void {
    $readme = packageFile('README.md');
    $start = strpos($readme, "\n## Authorization\n");

    expect($start)->not->toBeFalse();

    $section = substr($readme, (int) $start + strlen("\n## Authorization\n"));
    $next = strpos($section, "\n## ");
    $section = $next === false ? $section : substr($section, 0, $next);

    expect($section)
        ->toContain('Contracts\Permissions')
        ->toContain('Contracts\Roles')
        ->toContain('Contracts\Scope')
        ->toContain('GatePermissions')
        ->toContain('NullRoles')
        ->toContain('Abilities::define')
        ->toContain('Authorizer::allows')
        ->toContain('allowsAbility')
        ->toContain('Authorizer::can')
        ->toContain('singleton(')
        ->toContain('scoped(')
        ->toContain('extend(')
        ->toContain('FakePermissions')
        ->toContain('FakeRoles');
});

it('records the authorization contract as added under unreleased', function (): void {
    $unreleased = unreleasedChangelog();
    $start = strpos($unreleased, '### Added');

    expect($start)->not->toBeFalse();

    $added = substr($unreleased, (int) $start);
    $next = strpos($added, "\n### ", 1);
    $added = $next === false ? $added : substr($added, 0, $next);

    expect($added)
        ->toContain('Contracts\Permissions')
        ->toContain('Contracts\Roles')
        ->toContain('Contracts\Scope')
        ->toContain('GatePermissions')
        ->toContain('NullRoles')
        ->toContain('Abilities')
        ->toContain('Authorizer::allows()')
        ->toContain('allowsAbility()')
        ->toContain('FakePermissions')
        ->toContain('FakeRoles');
});

it('aliases the main branch to 2.0.x-dev', function (): void {
    /** @var array{extra: array{branch-alias: array{dev-main: string}}} $composer */
    $composer = json_decode(packageFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['branch-alias']['dev-main'])->toBe('2.0.x-dev');
});
