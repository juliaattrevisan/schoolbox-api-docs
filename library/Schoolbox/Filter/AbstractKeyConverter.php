<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 4:30 PM
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
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
     * Get all possible items for this filter (regardless of whether they are configured to be availble).
     *
     * If the selected set of items equals the full possible set, we can forgo the filter check in our DB query for a performance boost
     *
     * @return Collection (intentionally not a GenericCollection)
     */
    abstract protected function getAllPossibleItems();


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
        $allItems      = $this->getAllPossibleItems();
        $availableItems = $allItems->emptyClone();
        $selectedItems = $allItems->emptyClone();

        foreach ($availableSelections as $selection) {
            $item = $this->getItem($selection->getKey());
            $availableItems->add($item);
            if ($selection->getSelected()) {
                $selectedItems->add($item);
            }
        }

        // Was nothing selected ?
        if(0 === $selectedItems->count()){
            // Were all possible options available ?
            if($allItems->count() === $availableItems->count()){
                // Return an empty collection - so the DB generator can forgo the filter entirely
                return $allItems->emptyClone();
            }
            // Return the available options (ie none of a subset being selected equals all of the subset being selected)
            return $availableItems;
        }

        // Either non all possible items were available for selection, or a subset of items was selected.
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
