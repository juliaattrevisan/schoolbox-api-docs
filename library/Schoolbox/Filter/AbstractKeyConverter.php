<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 4:30 PM
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter;


abstract class AbstractKeyConverter implements IKeyConverter
{
    /** @var Filter\Model */
    private $filter;


    /**
     * @param Filter\Model $filter
     */
    public function __construct(Filter\Model $filter)
    {
        $this->filter = $filter;
    }


    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
    }


    /**
     * Sanitises a key before insertion in filter_selection table
     * @param string $key
     * @return string
     */
    public function sanitiseKey($key)
    {
        return (string)(int)$key;
    }
}
