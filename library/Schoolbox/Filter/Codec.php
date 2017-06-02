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
     * @param GenericCollection|IKeyConverter[] $keyConverters For enabled, user-configurable filters
     * @param User                              $user
     * @param string                            $page
     * @param int[][]                           $request       Indexed by filter.id, then selection.key
     * @return Collection|GenericCollection[]|Selection\Model[][]   Indexed by filter.id then selection.key
     * @throws \InvalidArgumentException        If any key is invalid TODO consider catching and discarding invalid items
     */
    public function buildSelections(
        Selection\Mapper $selectionMapper,
        GenericCollection $keyConverters,
        User $user,
        $page,
        array $request
    ) {
        // Clean aura remnant
        if (array_key_exists('page', $request)) {
            unset($request['page']);
        }

        /** @var Collection|GenericCollection[]|Selection\Model[][] $selections */
        $selections = new GenericCollection(GenericCollection::class);

        // Has any data been passed in? (will be empty if no filters available on this page)
        if (count($request)) {
            foreach ($request as $filterId => $filterSelectionArray) {
                // Not an array, this happens for empty sets, eg '?2='
                if (!is_array($filterSelectionArray)) {
                    continue;
                }

                $keyConverter = $keyConverters[$filterId];

                // This will occur when a controller hard-codes a filter, but the admin has
                // disabled the filter for this page. In this case we ignore the controller and respect the admin.
                if (!$keyConverter) {
                    continue;
                }

                $filter                = $keyConverter->getFilter();
                $filterSelections      = new GenericCollection(Selection\Model::class);
                $selections[$filterId] = $filterSelections;


                /** @var bool[] $filterSelectionArray */
                foreach ($filterSelectionArray as $key => $isSelected) {
                    // TODO insert permission checks here to ensure the end-user should be able to include this option in the report

                    $selection = $selectionMapper->createModel();
                    $key       = $keyConverter->sanitiseKey($key);
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
