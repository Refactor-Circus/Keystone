<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Events\WidgetCreatedEvent;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Events\WidgetRetrievedEvent;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Models\WidgetModel;

it('dispatches the lifecycle event the naming convention points at', function (): void {
    Event::fake([WidgetCreatedEvent::class]);

    WidgetModel::query()->create(['name' => 'Sprocket']);

    Event::assertDispatched(WidgetCreatedEvent::class, fn (WidgetCreatedEvent $event): bool => $event->model()->getAttribute('name') === 'Sprocket'
        && $event->hook() === 'created');
});

it('skips hooks without an event class', function (): void {
    expect((new WidgetModel)->dispatchesEvents())->toBe(['retrieved' => WidgetRetrievedEvent::class, 'created' => WidgetCreatedEvent::class]);
});

it('fires the package model events for an application subclass', function (): void {
    Event::fake([WidgetCreatedEvent::class]);

    ApplicationWidget::query()->create(['name' => 'Cog']);

    Event::assertDispatched(WidgetCreatedEvent::class);
});

it('builds the retrieved event only while something listens for it', function (): void {
    WidgetModel::query()->create(['name' => 'Gear']);
    WidgetRetrievedEvent::$built = 0;

    WidgetModel::query()->get();

    expect(WidgetRetrievedEvent::$built)->toBe(0);

    $seen = 0;
    Event::listen(ModelLifecycleEvent::class, function () use (&$seen): void {
        $seen++;
    });

    WidgetModel::query()->get();

    expect(WidgetRetrievedEvent::$built)->toBe(1)
        ->and($seen)->toBe(1);
});

final class ApplicationWidget extends WidgetModel {}
