<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Data;

use Illuminate\Support\Carbon;
use RefactorCircus\Keystone\Data\Data;
use RefactorCircus\Keystone\Data\Missing;

final readonly class WidgetData extends Data
{
    /**
     * @param  array<int, string>|Missing  $tags
     */
    public function __construct(
        public string $name,
        public int $perPage = 15,
        public bool|Missing $active = new Missing,
        public Color|Missing|null $color = new Missing,
        public string|Missing|null $note = new Missing,
        public Carbon|Missing $startsAt = new Missing,
        public AddressData|Missing $address = new Missing,
        public array|Missing $tags = new Missing,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(string $reserved = ''): array
    {
        return [
            'name' => ['required', 'string', 'not_in:'.$reserved],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
            'color' => ['sometimes', 'nullable', 'in:red,blue'],
            'note' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'date'],
            'address' => ['sometimes', 'array'],
            'address.city' => ['required_with:address', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
        ];
    }
}
