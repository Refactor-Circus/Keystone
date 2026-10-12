<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Http\Requests;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use RefactorCircus\Keystone\Auth\Authorizer;
use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Packages\PackageRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base HTTP request for every package in the suite.
 *
 * Each request owns its validation, its authorization, and the work itself.
 * Controllers collapse to `return $request->persist();`, so logic cannot drift
 * back into them. `persist()` calls the same Action the MCP surface calls, so
 * both speak to one implementation rather than two that drift.
 *
 * The request finds its package by namespace. With the package's
 * `authorization` config key on, every request acts as the authenticated
 * user and each call is checked against the policies in its `policies` key.
 */
abstract class Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authorizer()->authenticated($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Guests get a 401, signed-in users without the ability a 403.
     */
    protected function failedAuthorization(): void
    {
        if ($this->isGuest()) {
            throw new AuthenticationException;
        }

        parent::failedAuthorization();
    }

    /**
     * Whether the caller is unidentified, so a refusal asks them to sign in
     * (401) rather than telling them no (403). No Laravel user by default; a
     * request whose callers identify another way, such as by a subscriber's
     * own credentials, answers for itself.
     */
    protected function isGuest(): bool
    {
        return $this->user() === null;
    }

    /**
     * Carry out the request and return its response.
     */
    abstract public function persist(): Response;

    /**
     * The package this request belongs to.
     */
    protected function package(): Package
    {
        return app(PackageRegistry::class)->forOrFail(static::class);
    }

    protected function authorizer(): Authorizer
    {
        return Authorizer::for($this->package());
    }

    /**
     * The user calls act as, or null when authorization is off.
     */
    protected function actor(): ?Model
    {
        return $this->authorizer()->actor($this->user());
    }

    /**
     * Check an ability against the model's policy from the package's
     * `policies` config key.
     *
     * Pass the model class for abilities such as `viewAny` and `create`, and
     * the instance for `view`, `update` and `delete`.
     *
     * @param  Model|class-string<Model>  $subject
     * @param  array<int, mixed>  $arguments
     */
    protected function allows(string $ability, Model|string $subject, array $arguments = []): bool
    {
        return $this->authorizer()->can($this->user(), $ability, $subject, $arguments);
    }

    /**
     * Check an ability against every model given; an empty list passes.
     *
     * @param  iterable<int, Model>  $models
     */
    protected function allowsEach(string $ability, iterable $models): bool
    {
        foreach ($models as $model) {
            if (! $this->allows($ability, $model)) {
                return false;
            }
        }

        return true;
    }
}
