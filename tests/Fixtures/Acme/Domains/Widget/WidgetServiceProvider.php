<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget;

use Illuminate\Support\Facades\Route;
use RefactorCircus\Keystone\Support\ServiceProvider;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Http\Controllers\WidgetController;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Models\WidgetModel;

final class WidgetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadApiRoutesFrom(function (): void {
            Route::post('widgets', WidgetController::class)->name('widgets.store');
            Route::get('widgets/{widget}', fn (WidgetModel $widget): WidgetModel => $widget)->name('widgets.show');
        });
    }
}
