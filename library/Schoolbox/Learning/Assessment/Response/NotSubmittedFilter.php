<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Response;

use Schoolbox\Learning\Assessment\IResponse;

/**
 * Filter for excluding any IResponse objects which are submitted.
 *
 * Usage:
 * ```
 * $collection->filter(new NotSubmittedFilter());
 * ```
 */
class NotSubmittedFilter
{
    /**
     * @var SubmittedFilter
     */
    private $negativeFilter;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->negativeFilter = new SubmittedFilter();
    }

    /**
     * @param IResponse $response
     * @return bool
     */
    public function __invoke(IResponse $response)
    {
        return !$this->negativeFilter->__invoke($response);
    }
}
