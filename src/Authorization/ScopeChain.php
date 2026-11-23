<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Authorization;

use Illuminate\Database\Eloquent\Model;
use RefactorCircus\Keystone\Authorization\Contracts\Scope;

/**
 * A scope and the parents whose grants also apply in it.
 */
final class ScopeChain
{
    /**
     * The scope, then its parent, then that one's parent, nearest first.
     *
     * Stops at a scope already in the chain, whether the same instance or the
     * same saved model loaded again, so a parent loop cannot run forever.
     *
     * @return list<Scope>
     */
    public static function of(?Scope $scope): array
    {
        $chain = [];

        while ($scope !== null && ! self::contains($chain, $scope)) {
            $chain[] = $scope;
            $scope = $scope->parentScope();
        }

        return $chain;
    }

    /**
     * @param  list<Scope>  $chain
     */
    private static function contains(array $chain, Scope $scope): bool
    {
        foreach ($chain as $seen) {
            if ($seen === $scope) {
                return true;
            }

            if ($seen instanceof Model && $scope instanceof Model && $seen->getKey() !== null && $seen->is($scope)) {
                return true;
            }
        }

        return false;
    }
}
