<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

use function count;
use function is_array;

/**
 * Responsible:
 * * Convert POST/GET array to Selection\Model[][]
 * * Convert Selection\Model[][] to GET param string
 *
 * TODO - for now there is no compression. Need to add this in SA-3201
 * TODO - This is placeholder. All server-side URL compression/decompression + encoding/decoding will happen inside here
 */
class Codec
{
    /**
     * @param Mapper           $filterMapper
     * @param Selection\Mapper $selectionMapper
     */
    public function __construct(private Mapper $filterMapper, private Selection\Mapper $selectionMapper) {}

    /**
     * @param User  $user
     * @param       $page
     * @param array $request
     * @return GenericCollection
     * @throws Exception
     */
    public function convertRequestToSelections(User $user, $page, array $request): GenericCollection
    {
        /** @var GenericCollection|GenericCollection[]|Selection\Model[][] $selections */
        $selections = new GenericCollection(GenericCollection::class);

        // Has any data been passed in? (will be empty if no filters enabled on this page)
        if (count($request)) {
            /** @var string[]|bool[] $filterSelectionArray */
            foreach ($request as $filterId => $filterSelectionArray) {
                // Create the collection before checking if empty.
                // This is so the controller can still clear user-selections.
                $filterSelections      = $this->selectionMapper->createCollection();
                $selections[$filterId] = $filterSelections;

                // Not an array, this happens for empty sets, eg '?2='
                if (!is_array($filterSelectionArray)) {
                    continue;
                }

                $filter = $this->filterMapper->createModel($filterId);
                $page   = (string)$page;

                foreach ($filterSelectionArray as $key => $isSelected) {
                    $selection = $this->selectionMapper->createModel();
                    $selection
                        ->setFilter($filter)
                        ->setUser($user)
                        ->setPage($page)
                        ->setKey((string)$key)
                        ->setSelected((bool)$isSelected);
                    $filterSelections[$key] = $selection;
                }
            }
        }
        return $selections;
    }

    /**
     * @param Collection $selections
     * @return string
     */
    public function convertSelectionsToQueryString(Collection $selections): string
    {
        $queryStrings = [];

        /** @var Selection\Model[] $filterSelections */
        foreach ($selections as $filterId => $filterSelections) {
            if (!$filterSelections instanceof Collection) {
                continue;
            }

            if (0 === $filterSelections->count()) {
                // Note that this is NOT array syntax, eg '1=&2=' meaning both 1 and 2 are empty arrays
                $queryStrings[] = "$filterId=";
                continue;
            }

            foreach ($filterSelections as $selection) {
                if ($selection->getSelected()) {
                    $queryStrings[] = "{$filterId}[{$selection->getKey()}]=1";
                }
            }
        }

        return implode('&', $queryStrings);
    }
}
