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
 * Eg, a user selects Terms 2017, 2015 + 2010 -> getSelectedItems(TermFilter::SLUG) should return a set of 3 Term models.
 */
class Transformer
{

    /**
     * Indexed by IFilterDefinition::SLUG, then selection.key
     *
     * @var Collection|GenericCollection[]|Selection\Model[][] $selectionsPerFilter
     */
    private $selectionsPerFilter;

    /**
     * Indexed by IFilterDefinition::SLUG
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
        // Convert indexing by filter.id to IFilterDefinition::KEY
        $this->selectionsPerFilter = $selectionsPerFilter->emptyClone();
        $this->keyConverters       = $keyConverters->emptyClone();

        foreach ($keyConverters as $filterId => $keyConverter) {
            $filter = $keyConverter->getFilter();
            /** @var IFilterDefinition $filterDefinitionClass */
            $filterDefinitionClass = $filter->getClass();
            $filterSlug            = $filterDefinitionClass::getSlug();
            $this->keyConverters->offsetSet($filterSlug, $keyConverter);
            if ($selectionsPerFilter->offsetExists($filter->getId())) {
                $this->selectionsPerFilter->offsetSet($filterSlug, $selectionsPerFilter[$filter->getId()]);
            }
        }
    }


    /**
     * Get a set of entities represented by a filter component's selections
     *
     * @param string $filterSlug
     * @return null|Collection|mixed[]
     * @throws \InvalidArgumentException
     */
    public function getSelectedItems($filterSlug)
    {
        if (!$this->keyConverters->offsetExists($filterSlug)) {
            throw new \InvalidArgumentException("No KeyConverter found for filter slug: $filterSlug");
        }
        $keyConverter = $this->keyConverters[$filterSlug];

        return $keyConverter->getSelectedItems($this->selectionsPerFilter[$filterSlug]);
    }

    /**
     * @return Collection|Selection\Model[][]|GenericCollection[]
     */
    protected function getSelectionsPerFilter()
    {
        return $this->selectionsPerFilter;
    }

    /**
     * @return IKeyConverter[]|GenericCollection
     */
    protected function getKeyConverters()
    {
        return $this->keyConverters;
    }
}
