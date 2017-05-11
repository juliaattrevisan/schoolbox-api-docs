<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 4:30 PM
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
    public function sanitiseKey($key)
    {
        $this->assertValidKey($key);
        return (string)$key;
    }


    /**
     * @inheritdoc
     */
    public function getSelectedItems(GenericCollection $availableSelections = null)
    {
        if (null === $availableSelections) {
            return null;
        }

        $allItems       = $this->getAllPossibleItems();
        $availableItems = $allItems->emptyClone();
        $selectedItems  = $allItems->emptyClone();

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
            // Were all possible options available ?
            if ($allItems->count() === $availableItems->count()) {
                // Return NULL - so the DB generator can forgo the filter entirely
                return null;
            }
            // Return the available options (ie none of a subset being selected equals all of the subset being selected)
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
