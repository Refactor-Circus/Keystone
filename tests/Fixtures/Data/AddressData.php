<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Data;

use RefactorCircus\Keystone\Data\Data;

final readonly class AddressData extends Data
{
    public function __construct(
        public string $city,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return ['city' => ['required', 'string']];
    }
}
