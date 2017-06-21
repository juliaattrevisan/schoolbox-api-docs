<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;


abstract class AbstractKeyConverter implements IKeyConverter
{

    /** @var Filter\Model */
    private $filter;


    /**
     * @param Filter\Model $filter
     */
    public function __construct(Filter\Model $filter)
    {
        $this->filter = $filter;
    }


    /**
     * Fetch the item represented by the key
     *
     * @param string $key
     * @return mixed
     * @throws \InvalidArgumentException If key is invalid
     */
    abstract protected function getItem($key);


    /**
     * @inheritdoc
     */
    public function getSelectedItems(GenericCollection $availableSelections)
    {
        $allItems       = $this->getAllPossibleItems();
        $availableItems = $allItems->emptyClone();
        $selectedItems  = $allItems->emptyClone();

        // No available selections?
        if (0 === $availableSelections->count()) {
            // Short-circuit and return empty collection
            return $selectedItems;
        }

        // Build the available and selected item collections
        foreach ($availableSelections as $selection) {
            $key  = $selection->getKey();
            $item = $this->getItem($key);
            $availableItems->offsetSet($key, $item);
            if ($selection->getSelected()) {
                $selectedItems->offsetSet($key, $item);
            }
        }

        $countAll       = $allItems->count();
        $countSelected  = $selectedItems->count();
        $countAvailable = $availableItems->count();

        // Are are all possible options available, and none or all were selected ?
        if ($countAll === $countAvailable && (0 === $countSelected || $countAvailable === $countSelected)) {
            // Return NULL - so the DB generator can forgo the filter entirely
            return null;
        }

        // Are a subset of possible options available, and none of the subset were selected ?
        if($countAll !== $countAvailable && 0 === $countSelected){
            // Nothing selected is the same as all available options being selected.
            return $availableItems;
        }

        // Either not all possible items were available for selection, or a subset of items was selected.
        return $selectedItems;
    }


    /**
     * @param string $key A concrete key
     * @throws \InvalidArgumentException If key is invalid
     */
    abstract protected function assertValidKey($key);

    /* Getters
     */

    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
    }

}
