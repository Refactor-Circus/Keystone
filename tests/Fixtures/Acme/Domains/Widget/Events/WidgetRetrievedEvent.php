<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Models\WidgetModel;

final class WidgetRetrievedEvent implements ModelLifecycleEvent
{
    use Dispatchable;
    use SerializesModels;

    public static int $built = 0;

    public function __construct(public WidgetModel $widget)
    {
        self::$built++;
    }

    public function model(): Model
    {
        return $this->widget;
    }

    public function hook(): string
    {
        return 'retrieved';
    }
}
