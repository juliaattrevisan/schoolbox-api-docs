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
        // Convert indexing by filter.id to IFilterDefinition::SLUG
        $this->selectionsPerFilter = $selectionsPerFilter->emptyClone();
        $this->keyConverters       = $keyConverters->emptyClone();

        foreach ($keyConverters as $filterId => $keyConverter) {
            $filter = $keyConverter->getFilter();
            /** @var IFilterDefinition $filterDefinitionClass */
            $filterDefinitionClass = $filter->getClass();
            $filterSlug            = $filterDefinitionClass::getSlug();
            $this->keyConverters->offsetSet($filterSlug, $keyConverter);
            if ($selectionsPerFilter->offsetExists($filter->getId())) {
                $this->selectionsPerFilter[$filterSlug] = $selectionsPerFilter[$filter->getId()];
            }
        }
    }

    /**
     * Get a set of entities represented by a filter component's selections
     *
     * @param string $filterSlug
     * @return null|Collection|mixed[]
     * @throws \InvalidArgumentException If no KeyConverter registered for specified slug
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
     * Get all filter selections in the form accepted by an IdFindere
     * @todo pass an interface/object instead of array
     * @return array
     */
    public function getFilters()
    {
        $filters = [];
        foreach($this->getSelectionsPerFilter() as $slug => $selections){
            try{
                $filters[$slug] = $this->getSelectedItems($slug);
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
     * @return IKeyConverter[]|GenericCollection
     */
    protected function getKeyConverters()
    {
        return $this->keyConverters;
    }
}
