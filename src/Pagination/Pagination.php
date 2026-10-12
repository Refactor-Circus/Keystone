<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Pagination;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * How a list pages, in one place: the input it accepts, its validation
 * rules, the schema an MCP tool declares for that input, and the `meta` its
 * answer carries. The rules and the tool schema come from the same numbers,
 * so they cannot disagree.
 *
 *     Pagination::pages()             // page + per_page; meta has the total (paginate())
 *     Pagination::simple()            // page + per_page; no total (simplePaginate())
 *     Pagination::cursor(default: 50) // cursor + per_page (cursorPaginate())
 *
 * A Data class for a list typically exposes it, so its rules and its tool
 * share it:
 *
 *     public static function pagination(): Pagination { return Pagination::pages(max: 200); }
 *     public static function rules(): array { return [...] + self::pagination()->rules(); }
 *     // tool: public function schema(JsonSchema $schema): array { return [...] + ListXData::pagination()->schema($schema); }
 */
final readonly class Pagination
{
    public const string PAGES = 'pages';

    public const string SIMPLE = 'simple';

    public const string CURSOR = 'cursor';

    private function __construct(
        public string $method,
        public int $default,
        public int $max,
    ) {}

    /**
     * Numbered pages with a total, as `paginate()` gives.
     */
    public static function pages(int $default = 15, int $max = 100): self
    {
        return new self(self::PAGES, $default, $max);
    }

    /**
     * Numbered pages without a total, as `simplePaginate()` gives: no count
     * query, for large tables.
     */
    public static function simple(int $default = 15, int $max = 100): self
    {
        return new self(self::SIMPLE, $default, $max);
    }

    /**
     * Pages reached by an opaque cursor, as `cursorPaginate()` gives: stable
     * while rows are added, and cheap at any depth.
     */
    public static function cursor(int $default = 15, int $max = 100): self
    {
        return new self(self::CURSOR, $default, $max);
    }

    /**
     * The name of the input that says where to start: `page` or `cursor`.
     */
    public function position(): string
    {
        return $this->method === self::CURSOR ? 'cursor' : 'page';
    }

    /**
     * Validation rules for `per_page` and the position input.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.$this->max],
            $this->position() => $this->method === self::CURSOR
                ? ['sometimes', 'nullable', 'string']
                : ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The same input as an MCP tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'per_page' => $schema->integer()->min(1)->max($this->max)
                ->description(sprintf('Results per page, 1-%d. Defaults to %d.', $this->max, $this->default)),
            $this->position() => $this->method === self::CURSOR
                ? $schema->string()->description('Cursor from a previous page (meta.next_cursor) to continue from.')
                : $schema->integer()->min(1)->description('Page number, from 1.'),
        ];
    }

    /**
     * The `meta` a paginated answer carries, as an output schema type.
     */
    public function meta(JsonSchema $schema): Type
    {
        $properties = match ($this->method) {
            self::PAGES => [
                'current_page' => $schema->integer()->required(),
                'last_page' => $schema->integer()->required(),
                'per_page' => $schema->integer()->required(),
                'total' => $schema->integer()->required(),
            ],
            self::SIMPLE => [
                'current_page' => $schema->integer()->required(),
                'per_page' => $schema->integer()->required(),
                'has_more' => $schema->boolean()->required(),
            ],
            default => [
                'per_page' => $schema->integer()->required(),
                'next_cursor' => $schema->string()->nullable()->description('Pass as cursor for the next page; null on the last.')->required(),
                'prev_cursor' => $schema->string()->nullable()->required(),
            ],
        };

        return $schema->object($properties)->description('The page this answer holds.');
    }
}
