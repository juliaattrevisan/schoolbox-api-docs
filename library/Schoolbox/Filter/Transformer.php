<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;

/**
 * Encapsulates the transform of filter Selection models into filters (Collections of disparate entities)
 * eg Term Models, WorkType Models, AssessmentType strings etc
 *
 * Eg, a user selects Terms 2017, 2015 + 2010 -> getSelectedItems(TermFilter::class) should return a set of 3 Term models.
 */
class Transformer
{
    /**
     * Indexed by filter.class
     *
     * @var GenericCollection|Model[]
     */
    private $filtersByClass;

    /**
     * Indexed by filter.id, then selection.key
     *
     * @var Collection|GenericCollection[]|Selection\Model[][] $selectionsPerFilter
     */
    private $selectionsPerFilter;

    /**
     * Indexed by filter.id
     *
     * @var GenericCollection|IKeyConverter[]
     */
    private $keyConverters;


    /**
     * @param GenericCollection|IKeyConverter[]                  $keyConverters       Indexed by filter.id
     * @param Collection|GenericCollection[]|Selection\Model[][] $selectionsPerFilter Indexed by filter.id, then selection.key
     *
     */
    public function __construct(GenericCollection $keyConverters, Collection $selectionsPerFilter)
    {
        $this->filtersByClass = new GenericCollection(Model::class);

        foreach ($keyConverters as $keyConverter) {
            $filter                                    = $keyConverter->getFilter();
            $this->filtersByClass[$filter->getClass()] = $filter;
        }

        $this->keyConverters       = $keyConverters;
        $this->selectionsPerFilter = $selectionsPerFilter;
    }

    /**
     * Get a set of entities represented by a filter component's selections
     *
     * @param string $filterClass
     * @return null|Collection|mixed[]
     */
    public function getSelectedItems($filterClass)
    {
        // Was the requested filter not injected into this transformer?
        if (!$this->filtersByClass->offsetExists($filterClass)) {
            return null;
        }
        $filterId = $this->filtersByClass[$filterClass]->getId();
        $keyConverter = $this->keyConverters[$filterId];

        return $keyConverter->getSelectedItems($this->selectionsPerFilter[$filterId]);
    }


    /**
     * Build a filter DTO by converting filterSelection models into Collections of disparate entities
     *
     * @return DataTransferObject
     */
    public function buildFiltersDto()
    {
        $filters = new DataTransferObject();
        foreach($this->getSelectionsPerFilter() as $filterId => $selections){
            $instance = $this->keyConverters[$filterId]->getFilter();
            $filters->setSelections($instance, $this->getSelectedItems($instance->getClass()));
        }
        return $filters;
    }

    /* Getters
     */

    /**
     * @return Collection|Selection\Model[][]|GenericCollection[]
     */
    protected function getSelectionsPerFilter()
    {
        return $this->selectionsPerFilter;
    }


    /**
     * @return Model[]|GenericCollection
     */
    protected function getFiltersByClass()
    {
        return $this->filtersByClass;
    }


    /**
     * @return IKeyConverter[]|GenericCollection
     */
    protected function getKeyConverters()
    {
        return $this->keyConverters;
    }
}
