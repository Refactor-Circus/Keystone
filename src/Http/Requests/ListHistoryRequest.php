<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Http\Requests;

use Illuminate\Http\JsonResponse;
use RefactorCircus\Keystone\Audit\History;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * `GET {prefix}/history`: a package's audit entries, newest first, cursor
 * paginated. The route names the package; see `loadHistoryRoutes()`.
 */
final class ListHistoryRequest extends Request
{
    public function authorize(): bool
    {
        return app(History::class)->allows($this->package(), $this->user(), $this->all());
    }

    public function rules(): array
    {
        return History::rules();
    }

    public function persist(): JsonResponse
    {
        $history = app(History::class);

        if (! $history->available()) {
            return new JsonResponse(['message' => 'No audit log is installed. Install refactor-circus/keen to record history.'], 404);
        }

        if (! $this->package()->audits()) {
            return new JsonResponse(['message' => 'Auditing is turned off for this package.'], 404);
        }

        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new JsonResponse($history->page($this->package(), $validated)->toArray());
    }

    protected function package(): Package
    {
        $key = $this->route('package');

        return app(PackageRegistry::class)->get(is_string($key) ? $key : '');
    }
}
