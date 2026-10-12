<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use RefactorCircus\Keystone\Http\Resources\JsonResource;

/**
 * @property array{name: string} $resource
 */
final class GadgetResource extends JsonResource
{
    public static function schema(JsonSchema $schema): array
    {
        return ['name' => $schema->string()->required()];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['name' => $this->resource['name']];
    }
}
