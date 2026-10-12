<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Contracts;

/**
 * Marks the starting or finished event of a step that runs once per row or
 * page of a larger job, such as applying one import row. Audit logs skip it:
 * the Actions the step runs are recorded themselves, and the job is recorded
 * by its own start and finish, so an entry per row would only add noise.
 */
interface UnauditedActionEvent
{
    //
}
