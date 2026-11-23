<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Authorization;

use InvalidArgumentException;

/**
 * The abilities the installed packages define, filled from their providers'
 * `boot()`.
 *
 * A role store reads it to list the abilities it can grant and to seed the
 * default roles it creates. It holds code-defined state only, so one instance
 * serves the whole process.
 */
final class Abilities
{
    /**
     * @var array<string, array{description: string, grantTo: list<string>}>
     */
    private array $abilities = [];

    /**
     * Define an ability. `$grantTo` lists the slugs of the default roles a
     * role store may grant it to when it creates them.
     *
     * @param  array<int, string>  $grantTo
     *
     * @throws InvalidArgumentException when the name is empty, holds
     *                                  whitespace, or is already defined
     */
    public function define(string $ability, string $description, array $grantTo = []): self
    {
        if ($ability === '' || preg_match('/\s/u', $ability) === 1) {
            throw new InvalidArgumentException(sprintf('The ability name [%s] must not be empty or hold whitespace.', $ability));
        }

        if (isset($this->abilities[$ability])) {
            throw new InvalidArgumentException(sprintf('The ability [%s] is already defined.', $ability));
        }

        $this->abilities[$ability] = ['description' => $description, 'grantTo' => array_values($grantTo)];

        return $this;
    }

    public function has(string $ability): bool
    {
        return isset($this->abilities[$ability]);
    }

    /**
     * Every defined ability, sorted by name.
     *
     * @return array<string, array{description: string, grantTo: list<string>}>
     */
    public function all(): array
    {
        $abilities = $this->abilities;
        ksort($abilities, SORT_STRING);

        return $abilities;
    }
}
