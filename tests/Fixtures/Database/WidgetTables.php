<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Database;

use RefactorCircus\Keystone\Database\Tables;

final class WidgetTables extends Tables
{
    protected const string CONFIG = 'acme.tables';

    public const array DEFAULTS = [
        'widgets' => 'acme_widgets',
        'parts' => 'acme_parts',
    ];
}
