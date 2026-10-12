<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Keystone\Http\Requests\Request;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Models\WidgetModel;

final class StoreWidgetRequest extends Request
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->allows('create', WidgetModel::class);
    }

    /**
     * Callers holding an Acme key identify themselves without signing in.
     */
    protected function isGuest(): bool
    {
        return parent::isGuest() && ! $this->hasHeader('X-Acme-Key');
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }

    public function persist(): JsonResponse
    {
        $widget = WidgetModel::query()->create(['name' => $this->string('name')->toString()]);

        return new JsonResponse(['id' => $widget->id, 'package' => $this->package()->key], 201);
    }
}
