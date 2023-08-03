<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\ResultFilter;

use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

/**
 * Interface for classes which may filter out results for a given user.
 *
 * TODO consider moving into hydrator: it works directly with models,
 *      and filtering already happens there
 */
interface IResultFilter
{
    /**
     * Should this result be displayed to the given viewer?
     *
     * @param FullResult $result
     * @param User       $viewer
     * @return bool
     */
    public function display(FullResult $result, User $viewer): bool;
}
