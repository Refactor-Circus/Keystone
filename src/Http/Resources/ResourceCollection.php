<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Http\Resources;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * A list of resources, as `JsonResource::collection()` returns it.
 *
 * `toStructuredResponse()` answers an MCP tool: the items under `data` (an
 * empty list still ships, which `Response::structured([])` would refuse),
 * the page under `meta` when the list is paginated (see Pagination), and `with()` and
 * `additional()` beside them, as over HTTP.
 */
class ResourceCollection extends AnonymousResourceCollection
{
    public function toStructuredResponse(): ResponseFactory
    {
        $request = request();

        return Response::structured(['data' => $this->resolve($request)] + $this->meta() + $this->with($request) + $this->additional);
    }

    /**
     * The page a paginated list holds, matching `Pagination::meta()`:
     * numbered pages with or without a total, or cursors.
     *
     * @return array{meta?: array<string, int|bool|string|null>}
     */
    private function meta(): array
    {
        $page = $this->resource;

        return match (true) {
            $page instanceof LengthAwarePaginator => ['meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ]],
            $page instanceof CursorPaginator => ['meta' => [
                'per_page' => $page->perPage(),
                'next_cursor' => $page->nextCursor()?->encode(),
                'prev_cursor' => $page->previousCursor()?->encode(),
            ]],
            $page instanceof Paginator => ['meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
            ]],
            default => [],
        };
    }
}
