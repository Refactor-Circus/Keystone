<?php

declare(strict_types=1);

use RefactorCircus\Keystone\Tests\Fixtures\Database\WidgetTables;

it('names a table from the package config, keeping the default for the rest', function (): void {
    config()->set('acme.tables', ['widgets' => 'crm_widgets', 'parts' => '']);

    expect(WidgetTables::name('widgets'))->toBe('crm_widgets')
        ->and(WidgetTables::name('parts'))->toBe('acme_parts');
});
