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
 *
 * @todo Consider if this class is even needed. If always called as getByClass(), then the transformed can just
 * @todo     return an array indexed by class. It is the codec's responsibility to convert between Selection models and GET url param strings
 */
class DataTransferObject
{
    /**
     * Indexed by filter.id
     *
     * @var Collection[] $selectionsPerId (can also be null[], but storm doesn't like that type-hint)
     */
    private $selectionsPerId;

    /**
     * Indexed by filter.class
     *
     * @var null[]|Collection[]
     */
    private $selectionsPerClass;


    public function setSelections(Model $filter, Collection $selections = null)
    {
        $this->selectionsPerClass[$filter->getClass()] = $selections;
        $this->selectionsPerId[$filter->getId()] = $selections;
    }


    /**
     * @return Collection[]
     * @todo is this required?
     */
    public function getById()
    {
        return $this->selectionsPerId;
    }

    public function getByClass()
    {
        return $this->selectionsPerClass;
    }
}
