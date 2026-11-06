<?php

declare(strict_types=1);

use RefactorCircus\Keystone\Tests\Fixtures\Database\WidgetTables;

it('names a table from the package config, keeping the default for the rest', function (): void {
    config()->set('acme.tables', ['widgets' => 'crm_widgets', 'parts' => '']);

    expect(WidgetTables::name('widgets'))->toBe('crm_widgets')
        ->and(WidgetTables::name('parts'))->toBe('acme_parts');
});

it('uses the default connection when the package configures none', function (mixed $connection): void {
    config()->set('acme.database.connection', $connection);

    expect(WidgetTables::connection())->toBeNull()
        ->and(WidgetTables::db()->getName())->toBe(config('database.default'))
        ->and(WidgetTables::qualified('widgets'))->toBe('acme_widgets');
})->with([null, '', 42]);

it('puts the package on its own connection when one is configured', function (): void {
    config()->set('database.connections.acme', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    config()->set('acme.database.connection', 'acme');
    config()->set('acme.tables.widgets', 'crm_widgets');

    expect(WidgetTables::connection())->toBe('acme')
        ->and(WidgetTables::db()->getName())->toBe('acme')
        ->and(WidgetTables::qualified('widgets'))->toBe('acme.crm_widgets');
});
