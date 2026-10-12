<?php

declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Pagination\LengthAwarePaginator;
use RefactorCircus\Keystone\Testing\StructuredSchema;
use RefactorCircus\Keystone\Tests\Fixtures\Resources\GadgetResource;

/**
 * A tool output schema, as laravel/mcp serializes it.
 *
 * @param  array<string, mixed>  $properties
 * @return array<string, mixed>
 */
function outputSchema(array $properties): array
{
    return (new JsonSchemaTypeFactory)->object($properties)->toArray();
}

it('accepts content that matches, including nullable and paginated lists', function (): void {
    $schema = new JsonSchemaTypeFactory;
    $page = new LengthAwarePaginator([['name' => 'A']], total: 1, perPage: 15, currentPage: 1);

    expect(StructuredSchema::violations(outputSchema(GadgetResource::collectionSchema($schema)), GadgetResource::collection($page)->toStructuredResponse()->getStructuredContent()))->toBe([])
        ->and(StructuredSchema::violations(outputSchema(GadgetResource::structuredSchema($schema)), ['data' => ['name' => 'Gear']]))->toBe([])
        ->and(StructuredSchema::violations(outputSchema(['note' => $schema->string()->nullable()->required()]), ['note' => null]))->toBe([]);
});

it('reports missing, mistyped, undeclared and out-of-enum fields by path', function (): void {
    $schema = new JsonSchemaTypeFactory;

    expect(StructuredSchema::violations(outputSchema(GadgetResource::structuredSchema($schema)), ['data' => ['name' => 5, 'colour' => 'red']]))->toBe([
        '$.data.name: expected string, got int',
        '$.data.colour: not declared in the schema',
    ])->and(StructuredSchema::violations(outputSchema(['status' => $schema->string()->enum(['a'])->required(), 'id' => $schema->integer()->required()]), ['status' => 'b']))->toBe([
        '$.id: required but missing',
        '$.status: "b" is not one of ["a"]',
    ]);
});
