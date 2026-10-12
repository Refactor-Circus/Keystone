<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Http\Resources;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Resources\Json\JsonResource as BaseJsonResource;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use LogicException;
use RefactorCircus\Keystone\Pagination\Pagination;

/**
 * Base API resource for every package in the suite.
 *
 * The same resource answers both surfaces: `toResponse()` (Laravel's) for
 * HTTP, `toStructuredResponse()` for an MCP tool, with the same payload -
 * the resource under `data`, plus `with()` and `additional()` beside it - so
 * the two cannot drift. `collection()` returns a ResourceCollection, which
 * does the same for lists.
 *
 *     return (new OrganizationResource($organization))->toStructuredResponse();
 *     return OrganizationResource::collection($page)->toStructuredResponse();
 */
abstract class JsonResource extends BaseJsonResource
{
    /**
     * The fields `toArray()` returns, as JSON Schema types, so a tool can
     * declare its output: mark fields that are always present `required()`,
     * and those that can be null `nullable()`. Nested resources describe
     * theirs with their own `schema()`.
     *
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * The output schema of a tool answering with `toStructuredResponse()`:
     * the resource under `data`. Add the keys a tool sets with
     * `additional()`.
     *
     * @return array<string, Type>
     */
    public static function structuredSchema(JsonSchema $schema): array
    {
        return ['data' => $schema->object(static::schema($schema))->required()];
    }

    /**
     * The output schema of a tool answering with a collection's
     * `toStructuredResponse()`: the resources under `data`, and under `meta`
     * the page, shaped by how the list pages (numbered pages with a total
     * unless told otherwise).
     *
     * @return array<string, Type>
     */
    public static function collectionSchema(JsonSchema $schema, ?Pagination $pagination = null): array
    {
        return [
            'data' => $schema->array()->items($schema->object(static::schema($schema)))->required(),
            'meta' => ($pagination ?? Pagination::pages())->meta($schema),
        ];
    }

    /**
     * The resource as an MCP tool's structured response.
     */
    public function toStructuredResponse(): ResponseFactory
    {
        $request = request();

        return Response::structured(['data' => $this->resolve($request)] + $this->with($request) + $this->additional);
    }

    /**
     * @param  mixed  $resource
     */
    public static function collection($resource): ResourceCollection
    {
        $collection = parent::collection($resource);

        if (! $collection instanceof ResourceCollection) {
            throw new LogicException(static::class.'::newCollection() must return a '.ResourceCollection::class.'.');
        }

        return $collection;
    }

    /**
     * @param  mixed  $resource
     */
    protected static function newCollection($resource): ResourceCollection
    {
        return new ResourceCollection($resource, static::class);
    }
}
