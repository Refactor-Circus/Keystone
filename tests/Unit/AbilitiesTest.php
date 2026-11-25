<?php

declare(strict_types=1);

use RefactorCircus\Keystone\Authorization\Abilities;

it('lists defined abilities sorted by name', function (): void {
    $abilities = new Abilities;

    $returned = $abilities
        ->define('acme.widgets.view', 'See widgets.', ['member', 'admin'])
        ->define('acme.gadgets.view', 'See gadgets.');

    expect($returned)->toBe($abilities)
        ->and($abilities->all())->toBe([
            'acme.gadgets.view' => ['description' => 'See gadgets.', 'grantTo' => []],
            'acme.widgets.view' => ['description' => 'See widgets.', 'grantTo' => ['member', 'admin']],
        ]);
});

it('knows which abilities are defined', function (): void {
    $abilities = (new Abilities)->define('acme.widgets.view', 'See widgets.');

    expect($abilities->has('acme.widgets.view'))->toBeTrue()
        ->and($abilities->has('acme.widgets.delete'))->toBeFalse();
});

it('starts empty', function (): void {
    expect((new Abilities)->all())->toBe([]);
});

it('refuses an ability defined twice', function (): void {
    (new Abilities)
        ->define('acme.widgets.view', 'See widgets.')
        ->define('acme.widgets.view', 'See widgets again.');
})->throws(InvalidArgumentException::class, 'acme.widgets.view');

it('refuses a name that is empty or holds whitespace', function (string $name): void {
    (new Abilities)->define($name, 'A bad name.');
})->throws(InvalidArgumentException::class)->with([
    'empty' => '',
    'blank' => '   ',
    'inner space' => 'acme widgets.view',
    'leading space' => ' acme.widgets.view',
    'tab' => "acme.widgets\t.view",
    'newline' => "acme.widgets.view\n",
]);
