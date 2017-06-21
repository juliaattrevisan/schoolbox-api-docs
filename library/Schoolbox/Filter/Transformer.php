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
 * Encapsulates the transform of filter Selection models into filters (Collections of integers or string representing selected filter options)
 * eg Term Model Ids, WorkType Model Ids, AssessmentType strings etc
 *
 * Eg, a user selects Terms 2017, 2015 + 2010 -> getSelectedItems(TermFilter::class) should return a set of 3 Term model ids.
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
    protected function getSelectedItems($filterClass)
    {
        // Was the requested filter not injected into this transformer?
        if (!$this->filtersByClass->offsetExists($filterClass)) {
            return null;
        }
        $filterId     = $this->filtersByClass[$filterClass]->getId();
        $keyConverter = $this->keyConverters[$filterId];

        return $keyConverter->getSelectedItems($this->selectionsPerFilter[$filterId]);
    }


    /**
     * Build an array of filters, ready for injection into an IdFinder
     *
     * @return null[]|Collection[] Indexed by filter.class
     */
    public function getByClass()
    {
        $filters = [];

        /* Note: We iterate key-converters not the selections.
         *       This means we skip any selections that do not have a corresponding keyConverter.
         *       This is because the selections reflect was was sent by the client, but we don't rely on that,
         *       the key-converters are built by the system and reflect which filters are enabled.
         */
        foreach ($this->keyConverters as $keyConverter) {
            $filter          = $keyConverter->getFilter();
            $class           = $filter->getClass();
            $filters[$class] = $this->getSelectedItems($class);
        }

        return $filters;
    }

    /* Getters
     */

    /**
     * @return Collection|GenericCollection[]|Selection\Model[][]
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
