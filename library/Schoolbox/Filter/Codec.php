<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Responsible:
 * * Convert POST/GET array to Selection\Model[][]
 * * Convert Selection\Model[][] to GET param string
 * * Convert Filters to GET param string
 *
 * TODO - for now there is no compression. Need to add this in SA-3201
 * TODO - This is placeholder. All server-side URL compression/decompression + encoding/decoding will happen inside here
 */
class Codec
{

    /**
     * Convert a POST/GET request to a set of Selection Models, index by filter.id, then selection.key
     *
     * @param Selection\Mapper                  $selectionMapper
     * @param GenericCollection|IKeyConverter[] $keyConverters   Indexed by filter.id
     * @param User                              $user
     * @param string                            $page
     * @param int[][]                           $selectionsArray Indexed by filter.id, then selection.key
     * @return Collection|Selection\Model[][] Indexed by filter.id, then selection.key
     */
    public function convertRequestToSelectionModels(
        Selection\Mapper $selectionMapper,
        GenericCollection $keyConverters,
        User $user,
        $page,
        array $selectionsArray
    ) {
        /** @var Collection|GenericCollection[]|Selection\Model[][] $selections */
        $selections = new GenericCollection(GenericCollection::class);

        // Has any data been passed in? (will be empty if no filters available on this page)
        if (count($selectionsArray)) {
            foreach ($selectionsArray as $filterId => $filterSelectionArray) {

                // This can occur if we have manually inserted a filter for some reason, eg to exclude assessments in unit-spec contexts
                if (!$keyConverters->offsetExists($filterId)) {
                    // Don't include in build
                    continue;
                }
                $keyConverter          = $keyConverters[$filterId];
                $filter                = $keyConverter->getFilter();
                $filterSelections      = new GenericCollection(Selection\Model::class);
                $selections[$filterId] = $filterSelections;

                // Not an array, this happens for empty sets, eg '?2='
                if (!is_array($filterSelectionArray)) {
                    continue;
                }

                /** @var bool[] $filterSelectionArray */
                foreach ($filterSelectionArray as $key => $isSelected) {

                    $selection = $selectionMapper->createModel();
                    $key = $keyConverter->sanitiseKey($key);
                    $selection
                        ->setFilter($filter)
                        ->setUser($user)
                        ->setPage($page)
                        ->setKey($key)
                        ->setSelected((bool)$isSelected);
                    $filterSelections[$key] = $selection;
                }
            }
        }
        return $selections;
    }


    /**
     * Convert filters to a GET query string
     *
     * @param GenericCollection|IKeyConverter[] $keyConverters TODO index by class or id?
     * @param null[]                            Collection     []|mixed[][] $filters  TODO index by class or id?
     * @return string
     * @todo is this even needed?
     */
    public function convertFiltersToQueryString(GenericCollection $keyConverters, array $filters)
    {
        $queryStrings = [];

        foreach ($filters as $class => $selectedItems) {
            if (!$selectedItems instanceof Collection) {
                continue;
            }

            $keyConverter = $keyConverters[$class];
            $id           = $keyConverter->getFilter()->getId();

            if (0 === $selectedItems->count()) {
                $queryStrings[] = "{$id}=";  // TODO note that this is NOT array syntax
                continue;
            }

            /** @var Collection|mixed[] $selectedItems */
            foreach ($selectedItems as $item) {
                $queryStrings[] = "{$id}[{$keyConverter->getKey($item)}]=1";
            }
        }

        return implode('&', $queryStrings);
    }


    /**
     * @param Collection|Selection\Model[][] $selections Indexed by filter.id, then selection.key
     * @return string
     */
    public function convertSelectionsToQueryString(Collection $selections)
    {
        $queryStrings = [];

        foreach ($selections as $filterId => $filterSelections) {
            if (!$filterSelections instanceof Collection) {
                continue;
            }

            if (0 === $filterSelections->count()) {
                $queryStrings[] = "{$filterId}=";  // TODO note that this is NOT array syntax
                continue;
            }

            /** @var Selection\Model[] $filterSelections */
            foreach ($filterSelections as $selection) {
                $selected       = $selection->getSelected() ? '1' : '0';
                $queryStrings[] = "{$filterId}[{$selection->getKey()}]={$selected}";
            }
        }

        return implode('&', $queryStrings);
    }
}
