<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Data;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LogicException;
use Stringable;

/**
 * An Action's input: typed, immutable, and validated by its own rules.
 *
 * A subclass declares its fields as promoted constructor properties and its
 * validation as a static `rules()` method, which may take context such as the
 * record being updated:
 *
 *     final readonly class UpdateTeamData extends Data
 *     {
 *         public function __construct(
 *             public string|Missing $name = new Missing,
 *             public string|Missing $slug = new Missing,
 *         ) {}
 *
 *         public static function rules(?TeamModel $team = null): array { ... }
 *     }
 *
 * `from()` validates untrusted input (a request, a tool call, a CSV row)
 * against those rules, throwing a ValidationException, and hydrates the
 * fields from the snake_case keys: `per_page` fills `$perPage`. Values are
 * cast to the field's type - int, float, bool, string, backed enums, dates
 * and nested Data. A field the input left out keeps its default: `Missing`
 * for fields of a partial update, so a null that was sent stays distinct.
 *
 * A request that validated its input already, with these rules among its
 * own, hands its `validated()` to `fromValidated()` instead, so input is
 * checked once and every error is reported together.
 *
 * A nested Data field is built from the parent's validated input, so the
 * parent's rules check it under its key: `address.city`, required with
 * `required_with:address` when the field is optional. An array field
 * marked `#[ListOf(ItemData::class)]` is built item by item the same way.
 * A field that needs a lookup - a model the input names by id, say - is
 * filled by overriding `prepare()`, which sees the validated input first.
 *
 * Construct one with `new` for input the code itself vouches for.
 */
abstract readonly class Data
{
    /**
     * Validate untrusted input and build the data from it.
     *
     * @param  array<array-key, mixed>  $input
     */
    public static function from(array $input, mixed ...$context): static
    {
        /** @var array<string, mixed> $rules */
        $rules = method_exists(static::class, 'rules') ? (static::class)::rules(...$context) : [];

        return static::fromValidated(Validator::make($input, $rules)->validate());
    }

    /**
     * Build the data from input already validated against `rules()`, such as
     * a FormRequest's `validated()`, without validating it again. Values are
     * filled and cast as `from()` does.
     *
     * @param  array<array-key, mixed>  $validated
     */
    public static function fromValidated(array $validated): static
    {
        $validated = static::prepare($validated);
        $arguments = [];

        foreach (Fields::of(static::class) as $field) {
            $key = $field['key'];
            $value = array_key_exists($key, $validated) ? self::blankToNull($field['types'], $validated[$key]) : null;

            if (array_key_exists($key, $validated) && ($value !== null || $field['nullable'])) {
                $arguments[$field['name']] = $field['items'] !== null && is_array($value)
                    ? array_map(fn (mixed $item): mixed => self::castItem($field['items'], $item), $value)
                    : self::cast($field['types'], $value);
            } elseif (array_key_exists($key, $validated) && $field['default']) {
                continue;
            } elseif (! $field['default']) {
                if (! $field['nullable']) {
                    throw new LogicException(sprintf('%s needs `%s`, which its rules do not require.', static::class, $key));
                }

                $arguments[$field['name']] = null;
            }
        }

        return new static(...$arguments); // @phpstan-ignore new.static
    }

    /**
     * Turn validated input into field values before the fields are filled:
     * add a field that needs a lookup, such as the model an id names, from
     * the keys that name it. Override it; by default the input is used as is.
     *
     * @param  array<array-key, mixed>  $validated
     * @return array<array-key, mixed>
     */
    protected static function prepare(array $validated): array
    {
        return $validated;
    }

    /**
     * Whether a field was given: false while it holds `Missing`.
     */
    public function has(string $field): bool
    {
        return ! $this->{Str::camel($field)} instanceof Missing;
    }

    /**
     * The given fields under their snake_case keys, nested data included.
     * Fields left `Missing` are left out.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = [];

        foreach (get_object_vars($this) as $name => $value) {
            if ($value instanceof Missing) {
                continue;
            }

            $values[Str::snake($name)] = self::export($value);
        }

        return $values;
    }

    private static function export(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(self::export(...), $value);
        }

        return $value;
    }

    /**
     * An empty string for a field that takes no string, as HTTP input gives
     * it (Laravel's ConvertEmptyStringsToNull) and validation lets through:
     * null, or the field's default when it cannot be null.
     */
    /**
     * @param  list<string>  $types
     */
    private static function blankToNull(array $types, mixed $value): mixed
    {
        if ($value !== '' || in_array('string', $types, true) || in_array('mixed', $types, true)) {
            return $value;
        }

        return null;
    }

    /**
     * Cast a validated value to the first of the field's types it fits.
     */
    /**
     * @param  list<string>  $types
     */
    private static function cast(array $types, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        foreach ($types as $name) {
            if ($name === Missing::class) {
                continue;
            }

            [$fits, $cast] = self::castTo($name, $value);

            if ($fits) {
                return $cast;
            }
        }

        return $value;
    }

    /**
     * One item of a `#[ListOf]` field: Data from validated input (the parent's
     * rules checked it), anything else cast like a field of that type.
     *
     * @param  class-string  $type
     */
    private static function castItem(string $type, mixed $item): mixed
    {
        if (is_a($type, self::class, true) && is_array($item)) {
            return $type::fromValidated($item);
        }

        return self::cast([$type], $item);
    }

    /**
     * Whether the value fits the named type, and the value cast to it.
     *
     * @return array{0: bool, 1: mixed}
     */
    private static function castTo(string $type, mixed $value): array
    {
        $cast = match (true) {
            $type === 'mixed' => $value,
            $type === 'int' => is_numeric($value) ? (int) $value : null,
            $type === 'float' => is_numeric($value) ? (float) $value : null,
            $type === 'bool' => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            $type === 'string' => is_scalar($value) || $value instanceof Stringable ? (string) $value : null,
            $type === 'array' => is_array($value) ? $value : null,
            enum_exists($type) && is_a($type, BackedEnum::class, true) => $value instanceof $type ? $value : (is_int($value) || is_string($value) ? $type::tryFrom($value) : null),
            $type !== self::class && is_a($type, self::class, true) => $value instanceof $type ? $value : (is_array($value) ? $type::fromValidated($value) : null),
            is_a($type, DateTimeInterface::class, true) => $value instanceof DateTimeInterface ? $value : (is_string($value) ? Carbon::parse($value) : null),
            default => $value instanceof $type ? $value : null,
        };

        // Null is never a fit: a null value returned before any cast.
        return [$cast !== null, $cast];
    }
}
