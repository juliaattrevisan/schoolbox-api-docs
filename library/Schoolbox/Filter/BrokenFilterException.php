<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Throwable;

/**
 * Thrown if for whatever reason a filter receives data it cannot process.
 *
 * In this case, the filter user should be notified which filter has the issue,
 * and prompted to un-check all its options, thus setting it to NULL (which hopefully we can process).
 */
class BrokenFilterException extends \DomainException
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
     * @throws \InvalidArgumentException If filter name is not supplied
     */
    public function __construct($filterName, $message = '', $code = 0, Throwable $previous = null)
    {
        if(!is_string($filterName) || '' === $filterName){
            throw new \InvalidArgumentException("Filter name must be a non-empty string: $filterName");
        }
        parent::__construct($message, $code, $previous);
        $this->filterName = $filterName;
    }


    /**
     * @return string
     */
    public function getFilterName()
    {
        return $this->filterName;
    }
}
