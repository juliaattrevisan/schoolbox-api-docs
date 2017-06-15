<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext;

use Schoolbox\GenericCollection;
use Schoolbox\Filter;


/**
 * Specialised logic to deal with the 'KEY_NONE' semantics
 */
abstract class KeyConverter extends Filter\AbstractKeyConverter
{

    /**
     * The returned Collection contains ids and/or 'KEY_NONE' => null
     *
     * @inheritdoc
     * @param GenericCollection|Filter\Selection\Model[] $availableSelections
     * @param bool                                       $foldersFiltered Are folders being filtered?
     */
    public function getSelectedItems(GenericCollection $availableSelections, $foldersFiltered = true)
    {
        $allItems       = $this->getAllPossibleItems();
        $availableItems = $allItems->emptyClone();
        $selectedItems  = $allItems->emptyClone();

        // No available items?
        if (0 === $availableSelections->count()) {
            return $selectedItems;
        }

        foreach ($availableSelections as $selection) {
            $key  = $selection->getKey();
            $item = $this->getItem($key);
            $availableItems->offsetSet($key, $item);
            if ($selection->getSelected()) {
                $selectedItems->offsetSet($key, $item);
            }
        }

        // Was nothing selected ?
        if (0 === $selectedItems->count()) {

            // Are folders being filtered?
            //
            if ($foldersFiltered) {
                return null;
            }

            // Were all possible options available ?
            if ($allItems->count() === $availableItems->count()) {
                // Return NULL - so the DB generator can forgo the filter entirely
                return null;
            }
            // Return the available options (ie none of a subset being selected equals all of the subset being selected)
            return $availableItems;
        }

        // Was just the '(none)' option selected ?
        if (1 === $selectedItems->count() && null === $selectedItems->first()) {
            // return an empty set. ie assessments not in this context
            return $allItems->emptyClone();
        }

        // Either not all possible items were available for selection, or a subset of items was selected.
        return $selectedItems;

    }
}
