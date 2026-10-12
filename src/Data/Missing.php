<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Data;

/**
 * A field the input left out, as opposed to one it set to null.
 *
 * Optional fields of a partial update default to it, so an Action changes
 * only what was sent: `if (! $data->email instanceof Missing) { ... }`, or
 * `$data->has('email')`.
 */
final readonly class Missing
{
    //
}
