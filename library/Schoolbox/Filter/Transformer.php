<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * @param string          $filterClass
     * @param \Schoolbox\User $user
     * @param string          $page
     * @return null|Collection|mixed[]
     */
    protected function getSelectedItems(string $filterClass, \Schoolbox\User $user, string $page)
    {
        // Was the requested filter not injected into this transformer?
        if (!$this->filtersByClass->offsetExists($filterClass)) {
            return null;
        }
        $filterId     = $this->filtersByClass[$filterClass]->getId();
        $keyConverter = $this->keyConverters[$filterId];

        return $keyConverter->getSelectedItems($this->selectionsPerFilter[$filterId], $user, $page);
    }


    /**
     * Build an array of filters, ready for injection into an IdFinder
     *
     * @param \Schoolbox\User $user
     * @param string          $page
     * @return null[]|Collection[] Indexed by filter.class
     */
    public function getByClass(\Schoolbox\User $user, string $page): array
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
            $filters[$class] = $this->getSelectedItems($class, $user, $page);
        }

        return $filters;
    }
}
