<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Data;

use Illuminate\Support\Str;
use RefactorCircus\Keystone\Data\Attributes\ListOf;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * What a Data class's constructor takes, read by reflection once per class
 * per process rather than on every `from()`: bulk work builds thousands.
 *
 * @internal
 */
final class Fields
{
    /**
     * @var array<class-string, list<array{name: string, key: string, nullable: bool, default: bool, types: list<string>, items: class-string|null}>>
     */
    private static array $fields = [];

    /**
     * @param  class-string  $class
     * @return list<array{name: string, key: string, nullable: bool, default: bool, types: list<string>, items: class-string|null}>
     */
    public static function of(string $class): array
    {
        return self::$fields[$class] ??= self::read($class);
    }

    /**
     * @param  class-string  $class
     * @return list<array{name: string, key: string, nullable: bool, default: bool, types: list<string>, items: class-string|null}>
     */
    private static function read(string $class): array
    {
        $fields = [];

        foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $members = match (true) {
                $type instanceof ReflectionNamedType => [$type],
                $type instanceof ReflectionUnionType => $type->getTypes(),
                default => [],
            };

            $listOf = $parameter->getAttributes(ListOf::class)[0] ?? null;

            $fields[] = [
                'name' => $parameter->getName(),
                'key' => Str::snake($parameter->getName()),
                'nullable' => $parameter->allowsNull(),
                'default' => $parameter->isDefaultValueAvailable(),
                'types' => array_values(array_map(
                    fn (ReflectionNamedType $member): string => $member->getName(),
                    array_filter($members, fn (mixed $member): bool => $member instanceof ReflectionNamedType),
                )),
                'items' => $listOf?->newInstance()->type,
            ];
        }

        return $fields;
    }
}
