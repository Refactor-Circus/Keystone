<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;
use RefactorCircus\Keystone\Pagination\Pagination;
use RefactorCircus\Keystone\Testing\StructuredSchema;
use RefactorCircus\Keystone\Tests\Fixtures\Resources\GadgetResource;

/**
 * @param  array<string, mixed>  $properties
 * @return array<string, mixed>
 */
function paginationSchema(array $properties): array
{
    return (new JsonSchemaTypeFactory)->object($properties)->toArray();
}

it('validates the input of each method from the same numbers its tool schema states', function (Pagination $pagination, string $position, mixed $valid, mixed $invalid): void {
    $schema = paginationSchema($pagination->schema(new JsonSchemaTypeFactory));

    expect($pagination->position())->toBe($position)
        ->and(Validator::make(['per_page' => 50, $position => $valid], $pagination->rules())->passes())->toBeTrue()
        ->and(Validator::make(['per_page' => 51], $pagination->rules())->passes())->toBeFalse()
        ->and(Validator::make([$position => $invalid], $pagination->rules())->passes())->toBeFalse()
        ->and($schema['properties']['per_page']['maximum'])->toBe(50)
        ->and($schema['properties']['per_page']['description'])->toBe('Results per page, 1-50. Defaults to 10.');
})->with([
    'pages' => [Pagination::pages(10, 50), 'page', 2, 0],
    'simple' => [Pagination::simple(10, 50), 'page', 2, 'two'],
    'cursor' => [Pagination::cursor(10, 50), 'cursor', 'eyJpZCI6MX0', ['not', 'a', 'string']],
]);

it('answers each paginator with the meta its method declares', function (Pagination $pagination, Closure $paginate): void {
    $content = GadgetResource::collection($paginate())->toStructuredResponse()->getStructuredContent();

    expect(StructuredSchema::violations(paginationSchema(GadgetResource::collectionSchema(new JsonSchemaTypeFactory, $pagination)), $content))->toBe([]);
})->with([
    'pages' => [Pagination::pages(), fn () => new LengthAwarePaginator([['name' => 'A']], total: 3, perPage: 1, currentPage: 2)],
    'simple' => [Pagination::simple(), fn () => new Paginator([['name' => 'A'], ['name' => 'B']], perPage: 1, currentPage: 1)],
    'cursor' => [Pagination::cursor(), fn () => new CursorPaginator([['name' => 'A'], ['name' => 'B']], perPage: 1, cursor: new Cursor(['id' => 1]))],
]);

it('reports the cursor to continue from', function (): void {
    $content = GadgetResource::collection(new CursorPaginator([['name' => 'A'], ['name' => 'B']], perPage: 1, options: ['parameters' => ['name']]))
        ->toStructuredResponse()
        ->getStructuredContent();

    expect($content['meta']['per_page'])->toBe(1)
        ->and($content['meta']['next_cursor'])->toBeString()
        ->and($content['meta']['prev_cursor'])->toBeNull();
});
