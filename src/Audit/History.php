<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Audit;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;
use RefactorCircus\Keystone\Audit\Data\AuditFilter;
use RefactorCircus\Keystone\Audit\Data\AuditPage;
use RefactorCircus\Keystone\Auth\Authorizer;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Pagination\Pagination;

/**
 * A package's own audit history, as its JSON API and MCP server serve it.
 *
 * Both surfaces validate with `rules()`, authorize with `allows()` and read
 * with `page()`, so the history endpoint and tool of every package agree.
 */
final readonly class History
{
    /**
     * The Gate ability that guards reading a package's whole history, when
     * an application defines it. Reading one model's history asks `view` on
     * that model instead.
     */
    public const string ABILITY = 'viewAuditLog';

    public function __construct(
        private AuditTrail $trail,
        private Gate $gate,
    ) {}

    /**
     * History pages by cursor, newest first.
     */
    public static function pagination(): Pagination
    {
        return Pagination::cursor(default: 25);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'subject_type' => ['nullable', 'string', 'max:191', 'required_with:subject_id'],
            'subject_id' => ['nullable', 'string', 'max:191', 'required_with:subject_type'],
            'action' => ['nullable', 'string', 'max:191'],
        ] + self::pagination()->rules();
    }

    public function available(): bool
    {
        return $this->trail->available();
    }

    /**
     * Whether the user may read this history: the package's own rule, when
     * it set one; otherwise `view` on the subject when one is named (`viewAny` on its class once the model is gone), otherwise the
     * `viewAuditLog` ability when the application defines it.
     *
     * @param  array<string, mixed>  $input
     */
    public function allows(Package $package, ?Authenticatable $user, array $input): bool
    {
        $authorizer = Authorizer::for($package);

        if (! $authorizer->authenticated($user)) {
            return false;
        }

        $subject = $this->subject($input);

        if ($package->decidesHistory()) {
            return $package->allowsHistory($user, $subject);
        }

        if ($subject !== null) {
            return $authorizer->can($user, $subject instanceof Model ? 'view' : 'viewAny', $subject);
        }

        if (! $authorizer->enabled() || ! $this->gate->has(self::ABILITY)) {
            return true;
        }

        return $user instanceof Model && $this->gate->forUser($user)->allows(self::ABILITY, [$package->key]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function page(Package $package, array $validated): AuditPage
    {
        return $this->trail->entries(AuditFilter::make()
            ->source($package->key)
            ->subjectKey($this->string($validated, 'subject_type'), $this->string($validated, 'subject_id'))
            ->action($this->string($validated, 'action'))
            ->cursor($this->string($validated, 'cursor'))
            ->limit(is_numeric($validated['per_page'] ?? null) ? (int) $validated['per_page'] : self::pagination()->default));
    }

    /**
     * The model the input names, its class once the model is gone, or null
     * when the input names none or an unknown type.
     *
     * @param  array<string, mixed>  $input
     * @return Model|class-string<Model>|null
     */
    private function subject(array $input): Model|string|null
    {
        $type = $this->string($input, 'subject_type');
        $id = $this->string($input, 'subject_id');

        if ($type === null || $id === null) {
            return null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        return $class::query()->find($id) ?? $class;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function string(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
