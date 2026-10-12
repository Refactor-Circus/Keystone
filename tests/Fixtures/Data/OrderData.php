<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Data;

use BackedEnum;
use RefactorCircus\Keystone\Data\Attributes\ListOf;
use RefactorCircus\Keystone\Data\Data;

final readonly class OrderData extends Data
{
    /**
     * @param  list<AddressData>  $addresses
     * @param  list<Color>  $colors
     */
    public function __construct(
        #[ListOf(AddressData::class)]
        public array $addresses,
        #[ListOf(Color::class)]
        public array $colors = [],
        public BackedEnum|string $tone = 'plain',
        public ?string $label = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'addresses' => ['present', 'array', 'list'],
            'addresses.*.city' => ['required', 'string'],
            'colors' => ['sometimes', 'array'],
            'colors.*' => ['string'],
            'tone' => ['sometimes', 'string'],
            'code' => ['sometimes', 'string'],
        ];
    }

    /**
     * The label is looked up from the code the input names.
     */
    protected static function prepare(array $validated): array
    {
        return isset($validated['code']) ? [...$validated, 'label' => 'Order '.$validated['code']] : $validated;
    }
}
