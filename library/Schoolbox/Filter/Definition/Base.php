<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Definition;

use Schoolbox\Filter\Model as Filter;

/**
 * Provides common filter definition behaviour
 */
abstract class Base implements IFilter
{
    /** @var Filter */
    private $filter;

    /**
     * @param Filter $filter
     */
    public function __construct(Filter $filter)
    {
        $this->filter = $filter;
    }


    /**
     * @return Filter
     */
    public function getFilter()
    {
        return $this->filter;
    }
}
