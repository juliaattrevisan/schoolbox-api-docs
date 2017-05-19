<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;

/**
 * Encapsulates collections of entities we wish to match when filtering.
 *
 * Indexed by both filter.id and filter.class to bridge the internal vs external divide
 *
 * @todo Consider changing this name to something more informative
 */
class DataTransferObject
{
    /**
     * Indexed by filter.class
     *
     * @var null[]|Collection[]
     */
    private $selectionsPerClass;


    /**
     * Inject selections, overriding any that already exist for this filter.
     *
     * @param Model           $filter
     * @param Collection|null $selections
     * @return $this
     */
    public function setSelections(Model $filter, Collection $selections = null)
    {
        $this->selectionsPerClass[$filter->getClass()] = $selections;

        return $this;
    }


    /**
     * @return \null[]|Collection[]
     */
    public function getByClass()
    {
        return $this->selectionsPerClass;
    }
}
