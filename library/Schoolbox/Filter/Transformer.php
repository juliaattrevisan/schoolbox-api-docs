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
 * Encapsulates the transform of filter selections into filters (collections of entities)
 * eg Term Models, WorkType Models, AssessmentType strings etc
 *
 * Eg, a user selects Terms 2017, 2015 + 2010 -> getSelectedItems(TermFilter::class) should return a set of 3 Term models.
 */
class Transformer
{

    /**
     * Indexed by filter.id
     *
     * @var GenericCollection|Model[]
     */
    private $filtersById;

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
//        $this->filtersById    = new GenericCollection(Model::class);
        $this->filtersByClass = new GenericCollection(Model::class);

        foreach ($keyConverters as $keyConverter) {
            $filter                                    = $keyConverter->getFilter();
//            $this->filtersById[$filter->getId()]       = $filter;
            $this->filtersByClass[$filter->getClass()] = $filter;
        }

        $this->keyConverters       = $keyConverters;
        $this->selectionsPerFilter = $selectionsPerFilter;
//
//
//        // Convert indexing by filter.id to IFilterDefinition::class
//        $this->selectionsPerFilter = $selectionsPerFilter->emptyClone();
//        $this->keyConverters       = $keyConverters->emptyClone();
//
//        foreach ($keyConverters as $filterId => $keyConverter) {
//            $filter = $keyConverter->getFilter();
//            $this->keyConverters->offsetSet($filter->getClass(), $keyConverter);
//            if ($selectionsPerFilter->offsetExists($filter->getId())) {
//                $this->selectionsPerFilter[$filter->getClass()] = $selectionsPerFilter[$filter->getId()];
//            }
//        }
    }

    /**
     * Get a set of entities represented by a filter component's selections
     *
     * @param string $filterClass
     * @return null|Collection|mixed[]
     * @throws \InvalidArgumentException If no KeyConverter registered for specified filter class
     */
    public function getSelectedItems($filterClass)
    {
        if (!$this->filtersByClass->offsetExists($filterClass)) {
            throw new \InvalidArgumentException("No KeyConverter found for filter class: $filterClass");
        }
        $filterId = $this->filtersByClass[$filterClass]->getId();
        $keyConverter = $this->keyConverters[$filterId];

        return $keyConverter->getSelectedItems($this->selectionsPerFilter[$filterId]);
    }


    /**
     * Get all filter selections in the form accepted by an IdFinder, indexed by filterDefinition::class
     *
     * @todo think of a good name for the TODO class
     * @return Dto
     */
    public function getFiltersDto()
    {
        $filters = new Dto();
        foreach($this->getSelectionsPerFilter() as $filterId => $selections){
            try{
                $instance = $this->keyConverters[$filterId]->getFilter();
                $filters->setSelections($instance, $this->getSelectedItems($instance->getClass()));
            } catch (\InvalidArgumentException $e){
                // This is logically impossible to throw when called from here.
                // But just in case...
                if(function_exists('newrelic_notice_error')){
                    newrelic_notice_error(null, $e);
                }
            }
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
