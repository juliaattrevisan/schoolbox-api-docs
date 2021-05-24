<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter;

use Throwable;
use DomainException;

/**
 * Thrown if for whatever reason a filter receives data it cannot process.
 *
 * In this case, the filter user should be notified which filter has the issue,
 * and prompted to un-check all its options, thus setting it to NULL (which hopefully we can process).
 */
class BrokenFilterException extends DomainException
{

    /**
     * The filter that is broken
     *
     * @var string
     */
    private $filterName;


    /**
     * @param string         $filterName
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $filterName, string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->filterName = $filterName;
    }


    /**
     * @return string
     */
    public function getFilterName(): string
    {
        return $this->filterName;
    }
}
