<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Mcp\Requests;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RefactorCircus\Keystone\Audit\Data\AuditEntry;
use RefactorCircus\Keystone\Audit\History;

/**
 * A package's audit entries for its history tool. The tool names the package.
 */
final class ListHistoryMcpRequest extends Request
{
    protected function authorize(): bool
    {
        return app(History::class)->allows($this->package(), $this->user(), $this->all());
    }

    protected function rules(): array
    {
        return History::rules();
    }

    protected function handle(array $validated): Response|ResponseFactory
    {
        $history = app(History::class);

        if (! $history->available()) {
            return Response::error('No audit log is installed, so there is no history to show. Install refactor-circus/keen to record it.');
        }

        if (! $this->package()->audits()) {
            return Response::error('Auditing is turned off for this package.');
        }

        $page = $history->page($this->package(), $validated);

        return $this->structuredCollection(
            array_map(fn (AuditEntry $entry): array => $entry->toArray(), $page->entries),
            ['next_cursor' => $page->nextCursor],
        );
    }
}
