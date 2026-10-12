<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Data\Attributes;

use Attribute;

/**
 * What each item of an array field holds, so the field is built item by
 * item: Data from each item's input, a backed enum from each value.
 *
 *     public function __construct(
 *         #[ListOf(AddLineData::class)]
 *         public array $lines,
 *     ) {}
 *
 * The parent's rules validate the items (`lines.*.quantity`), so each item
 * is built with `fromValidated()` and not validated again.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class ListOf
{
    /**
     * @param  class-string  $type
     */
    public function __construct(public string $type) {}
}
