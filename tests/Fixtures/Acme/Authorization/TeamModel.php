<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Scope;

/**
 * A scope inside another: a team's grants come from its organization too, and
 * it grants nothing by itself.
 */
final class TeamModel extends Model implements Scope
{
    public ?Scope $parent = null;

    public function parentScope(): ?Scope
    {
        return $this->parent;
    }

    public function grantsByItself(Authenticatable $user, string $ability): bool
    {
        return false;
    }
}
