<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response;

use Schoolbox\Learning\Assessment\IResponse;

/**
 * Filter for excluding any IResponse objects which are not submitted.
 *
 * Usage:
 * ```
 * $collection->filter(new SubmittedFilter());
 * ```
 */
class SubmittedFilter
{
    /**
     * @param IResponse $response
     * @return bool
     */
    public function __invoke(IResponse $response)
    {
        return $response->getSubmittedAt() && !$response->getSubmittedAt()->isNull();
    }
}
