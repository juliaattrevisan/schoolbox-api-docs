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
 * Encapsulates the transform of filter selections into collection
 * of entities, eg Term Models, WorkType Models, AssessmentType strings etc
 *
 * Eg, a user selects Terms 2017, 2015 + 2010 -> getSelectedItems(TermFilter.class) should return a set of 3 Term models.
 */
class Transformer
{

    /**
     * Indexed by filter.class, then selection.key
     *
     * @var Collection|GenericCollection[]|Selection\Model[][] $selectionsPerFilter
     */
    private $selectionsPerFilter;

    /**
     * Indexed by filter.class
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
        // Convert indexing by filter.id to filter.class
        $this->selectionsPerFilter = $selectionsPerFilter->emptyClone();
        $this->keyConverters       = $keyConverters->emptyClone();

        foreach ($keyConverters as $filterId => $keyConverter) {
            $filter = $keyConverter->getFilter();
            $this->keyConverters->offsetSet($filter->getClass(), $keyConverter);
            if ($selectionsPerFilter->offsetExists($filter->getId())) {
                $this->selectionsPerFilter->offsetSet($filter->getClass(), $selectionsPerFilter[$filter->getId()]);
            }
        }
    }


    /**
     * Get a set of entities represented by a filter component's selections
     *
     * @param string $filterClass
     * @return Collection|mixed[]
     * @throws \InvalidArgumentException
     */
    public function getSelectedItems($filterClass)
    {
        if (!$this->keyConverters->offsetExists($filterClass)) {
            throw new \InvalidArgumentException("No KeyConverter found for class: $filterClass");
        }
        $keyConverter = $this->keyConverters[$filterClass];

        return $keyConverter->getSelectedItems($this->selectionsPerFilter[$filterClass]);
    }
}
