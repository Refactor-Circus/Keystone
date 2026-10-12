<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Testing;

/**
 * Checks an MCP tool's structured content against the output schema it
 * declares, so a test catches a resource and its schema drifting apart.
 *
 * It understands what the JSON Schema builder writes: `type` (one or a
 * list, `null` for nullable), `properties`, `required`, `items` and `enum`.
 * It is stricter than JSON Schema in one way: a field the content holds
 * but the schema does not declare is a violation, unless the object
 * declares no properties at all.
 *
 *     expect(StructuredSchema::violations($tool->toArray()['outputSchema'], $content))->toBe([]);
 */
final class StructuredSchema
{
    /**
     * Every way the value breaks the schema, each naming its path.
     *
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public static function violations(array $schema, mixed $value, string $path = '$'): array
    {
        $types = isset($schema['type']) ? (array) $schema['type'] : [];

        if ($types !== [] && ! self::matchesAny($types, $value)) {
            return [sprintf('%s: expected %s, got %s', $path, implode('|', $types), get_debug_type($value))];
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            return [sprintf('%s: %s is not one of %s', $path, json_encode($value), json_encode($schema['enum']))];
        }

        if (is_array($value) && array_is_list($value) && isset($schema['items']) && is_array($schema['items'])) {
            $violations = [];

            foreach ($value as $index => $item) {
                array_push($violations, ...self::violations($schema['items'], $item, $path.'['.$index.']'));
            }

            return $violations;
        }

        if (is_array($value) && (! array_is_list($value) || $value === []) && in_array('object', $types, true)) {
            return self::objectViolations($schema, $value, $path);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<array-key, mixed>  $value
     * @return list<string>
     */
    private static function objectViolations(array $schema, array $value, string $path): array
    {
        /** @var array<string, array<string, mixed>> $properties */
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $violations = [];

        foreach ((array) ($schema['required'] ?? []) as $required) {
            if (! array_key_exists($required, $value)) {
                $violations[] = sprintf('%s.%s: required but missing', $path, $required);
            }
        }

        foreach ($value as $key => $item) {
            if (isset($properties[$key])) {
                array_push($violations, ...self::violations($properties[$key], $item, $path.'.'.$key));
            } elseif ($properties !== []) {
                $violations[] = sprintf('%s.%s: not declared in the schema', $path, $key);
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, mixed>  $types
     */
    private static function matchesAny(array $types, mixed $value): bool
    {
        foreach ($types as $type) {
            $matches = match ($type) {
                'null' => $value === null,
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                // JSON decodes an empty object to an empty array.
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && (! array_is_list($value) || $value === []),
                default => true,
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }
}
