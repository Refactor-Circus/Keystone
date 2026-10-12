<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RefactorCircus\Keystone\Data\Missing;
use RefactorCircus\Keystone\Tests\Fixtures\Data\AddressData;
use RefactorCircus\Keystone\Tests\Fixtures\Data\Color;
use RefactorCircus\Keystone\Tests\Fixtures\Data\OrderData;
use RefactorCircus\Keystone\Tests\Fixtures\Data\WidgetData;

it('validates input against its rules', function (): void {
    expect(fn () => WidgetData::from(['per_page' => 0]))->toThrow(ValidationException::class);

    try {
        WidgetData::from(['per_page' => 0]);
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['name', 'per_page']);
    }
});

it('passes context to its rules', function (): void {
    expect(fn () => WidgetData::from(['name' => 'admin'], 'admin'))->toThrow(ValidationException::class)
        ->and(WidgetData::from(['name' => 'admin'])->name)->toBe('admin');
});

it('hydrates fields from snake_case keys and casts them to their types', function (): void {
    $data = WidgetData::from([
        'name' => 'Gear',
        'per_page' => '25',
        'active' => '0',
        'color' => 'blue',
        'starts_at' => '2026-10-10 09:00:00',
        'address' => ['city' => 'Leeds'],
        'tags' => ['a', 'b'],
        'ignored' => 'not in the rules',
    ]);

    expect($data->perPage)->toBe(25)
        ->and($data->active)->toBeFalse()
        ->and($data->color)->toBe(Color::Blue)
        ->and($data->startsAt)->toBeInstanceOf(Carbon::class)
        ->and($data->address)->toEqual(new AddressData('Leeds'))
        ->and($data->tags)->toBe(['a', 'b']);
});

it('validates nested data through the parent rules', function (): void {
    expect(fn () => WidgetData::from(['name' => 'Gear', 'address' => ['town' => 'Leeds']]))->toThrow(ValidationException::class);

    try {
        WidgetData::from(['name' => 'Gear', 'address' => ['town' => 'Leeds']]);
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['address.city']);
    }
});

it('builds nested data without validating it again', function (): void {
    $data = WidgetData::fromValidated(['name' => 'Gear', 'address' => ['city' => '']]);

    expect($data->address)->toEqual(new AddressData(''));
});

it('keeps fields left out apart from fields set to null', function (): void {
    $absent = WidgetData::from(['name' => 'Gear']);
    $cleared = WidgetData::from(['name' => 'Gear', 'note' => null]);

    expect($absent->note)->toBeInstanceOf(Missing::class)
        ->and($absent->has('note'))->toBeFalse()
        ->and($absent->perPage)->toBe(15)
        ->and($cleared->note)->toBeNull()
        ->and($cleared->has('note'))->toBeTrue();
});

it('exports the given fields under their snake_case keys', function (): void {
    $data = new WidgetData(name: 'Gear', perPage: 5, address: new AddressData('Leeds'), note: null);

    expect($data->toArray())->toBe(['name' => 'Gear', 'per_page' => 5, 'note' => null, 'address' => ['city' => 'Leeds']]);
});

it('treats an empty string as null for fields that take no string', function (): void {
    $data = WidgetData::from(['name' => 'Gear', 'color' => '', 'per_page' => '', 'note' => '']);

    expect($data->color)->toBeNull()
        ->and($data->perPage)->toBe(15)
        ->and($data->note)->toBe('');
});

it('builds from input validated elsewhere without validating it again', function (): void {
    $data = WidgetData::fromValidated(['name' => 'Gear', 'per_page' => '0', 'color' => 'red']);

    expect($data->perPage)->toBe(0)
        ->and($data->color)->toBe(Color::Red);
});

it('builds a list field item by item', function (): void {
    $data = OrderData::from([
        'addresses' => [['city' => 'Oslo'], ['city' => 'Lima']],
        'colors' => ['red', 'blue'],
    ]);

    expect($data->addresses)->toHaveCount(2)
        ->and($data->addresses[0])->toBeInstanceOf(AddressData::class)
        ->and($data->addresses[1]->city)->toBe('Lima')
        ->and($data->colors)->toBe([Color::Red, Color::Blue])
        ->and($data->toArray()['addresses'])->toBe([['city' => 'Oslo'], ['city' => 'Lima']]);
});

it('validates list items through the parent rules', function (): void {
    expect(fn () => OrderData::from(['addresses' => [['city' => 'Oslo'], []]]))->toThrow(ValidationException::class);
});

it('fills a looked-up field from prepare()', function (): void {
    expect(OrderData::from(['addresses' => [], 'code' => 'A7'])->label)->toBe('Order A7')
        ->and(OrderData::fromValidated(['addresses' => []])->label)->toBeNull();
});

it('keeps a string for a field typed as any enum or a string', function (): void {
    expect(OrderData::from(['addresses' => [], 'tone' => 'loud'])->tone)->toBe('loud')
        ->and((new OrderData([], tone: Color::Red))->tone)->toBe(Color::Red);
});
