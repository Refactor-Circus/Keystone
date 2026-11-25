<?php

declare(strict_types=1);

use RefactorCircus\Keystone\Authorization\Abilities;
use RefactorCircus\Keystone\Authorization\GatePermissions;
use RefactorCircus\Keystone\Authorization\NullRoles;
use RefactorCircus\Keystone\Authorization\ScopeChain;

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('RefactorCircus\Keystone')
    ->toUseStrictTypes();

arch('the package source never uses Atrium')
    ->expect('RefactorCircus\Keystone')
    ->not->toUse('RefactorCircus\Atrium');

it('names no dashboard and reads no ui config in the package source', function (): void {
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (stripos($contents, 'atrium') !== false || str_contains($contents, 'ui.enabled') || str_contains($contents, "configKey('ui")) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBeEmpty();
});

arch('the authorization contracts are interfaces')
    ->expect('RefactorCircus\Keystone\Authorization\Contracts')
    ->toBeInterfaces();

arch('the authorization defaults are final')
    ->expect([GatePermissions::class, NullRoles::class, Abilities::class, ScopeChain::class])
    ->toBeFinal();

arch('the authorization seam never uses a role store package')
    ->expect('RefactorCircus\Keystone\Authorization')
    ->not->toUse(['RefactorCircus\Roster', 'RefactorCircus\Guardian']);

it('names no role store package in the authorization source', function (): void {
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src/Authorization', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        if (stripos($contents, 'roster') !== false || stripos($contents, 'guardian') !== false) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBeEmpty();
});
