<?php

declare(strict_types=1);

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
