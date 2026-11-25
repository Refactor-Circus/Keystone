<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Scope;

/**
 * A top-level scope whose owner may do anything inside it, roles or not.
 */
final class OrganizationModel extends Model implements Scope
{
    public int|string|null $ownerId = null;

    public ?Scope $parent = null;

    /**
     * @var list<string>
     */
    public array $asked = [];

    public function parentScope(): ?Scope
    {
        return $this->parent;
    }

    public function grantsByItself(Authenticatable $user, string $ability): bool
    {
        $this->asked[] = $ability;

        return $this->ownerId !== null && $user->getAuthIdentifier() === $this->ownerId;
    }
}
