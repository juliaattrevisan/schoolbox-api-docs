<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\User;

use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\ResultFilter\IResultFilter;
use Schoolbox\User;

/**
 * Result filter for user search results.
 *
 * Restrictions:
 * - if `config.show_disabled_users` is off, do not show users which are not
 *   enabled
 * - guest users do not see parents and students; only staff and guests
 * - non-staff users may only see parents or students if configured to be
 *   allowed (`config.parent_search_enable`, `config.student_search_enable`)
 */
class ResultFilter implements IResultFilter
{
    /**
     * @inheritDoc
     */
    public function display(FullResult $result, User $viewer): bool
    {
        // Can this
    }
}
