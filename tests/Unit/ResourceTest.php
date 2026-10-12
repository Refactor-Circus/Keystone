<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use RefactorCircus\Keystone\Http\Resources\ResourceCollection;
use RefactorCircus\Keystone\Tests\Fixtures\Resources\GadgetResource;

it('answers MCP with the same payload as HTTP', function (): void {
    $resource = (new GadgetResource(['name' => 'Gear']))->additional(['outcome' => 'created']);

    expect($resource->toStructuredResponse()->getStructuredContent())
        ->toBe(['data' => ['name' => 'Gear'], 'outcome' => 'created'])
        ->toBe($resource->response()->getData(true));
});

it('collects into a ResourceCollection that ships an empty list', function (): void {
    $collection = GadgetResource::collection([]);

    expect($collection)->toBeInstanceOf(ResourceCollection::class)
        ->and($collection->toStructuredResponse()->getStructuredContent())->toBe(['data' => []]);
});

it('adds the page of a paginated list', function (): void {
    $page = new LengthAwarePaginator([['name' => 'A'], ['name' => 'B']], total: 7, perPage: 2, currentPage: 3);

    expect(GadgetResource::collection($page)->toStructuredResponse()->getStructuredContent())->toBe([
        'data' => [['name' => 'A'], ['name' => 'B']],
        'meta' => ['current_page' => 3, 'last_page' => 4, 'per_page' => 2, 'total' => 7],
    ]);

    expect(GadgetResource::collection(new Paginator([['name' => 'A']], perPage: 1, currentPage: 2))->toStructuredResponse()->getStructuredContent()['meta'])
        ->toBe(['current_page' => 2, 'per_page' => 1, 'has_more' => false]);
});
