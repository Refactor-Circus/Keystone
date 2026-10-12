<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Contracts;

/**
 * Marks the starting or finished event of an action that only reads, such
 * as checking a flag or searching records. Audit logs skip it, whatever its
 * name; without it, refactor-circus/keen only recognises reads named
 * `...Listing`/`...Listed`/`...Showing`/`...Shown`.
 */
interface ReadActionEvent
{
    //
}
