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
 * Knows how to map between an option.key and the item
 * represented by the key, and get the key from an item
 *
 * Must know about all possible items so it can decide whether a filter needs to be applied
 *
 * @see AbstractKeyConverter::getAllPossibleItems()
 */
interface IKeyConverter
{

    /**
     * @return Model
     */
    public function getFilter();


//    /**
//     * Get the concrete key for some item
//     *
//     * @param $item
//     * @return string
//     */
//    public function getKey($item);


    /**
     * Sanitises a key before insertion in filter_selection table
     *
     * Ensures the key is logically valid, and always returns a string
     *
     * @param string $key
     * @return string
     * @throws \InvalidArgumentException if the key is invalid
     */
    public function sanitiseKey($key);


    /**
     * For the user's posted selections, generate a collection of items they represent.
     *
     * If the set of selections is the logical full set of that item type, and either all are
     * selected or none are selected, then return NULL to inform the DB query generator
     * that it can skip this filter entirely.
     *
     * If $availableSelections is null, then the filter is not being uses, so return NULL
     * to indicate not to apply this filter
     *
     *
     * @param null|GenericCollection|Selection\Model[] $availableSelections Indexed by setting.key
     * @return null|Collection (intentionally not GenericCollection)
     * @throws \InvalidArgumentException
     */
    public function getSelectedItems(GenericCollection $availableSelections = null);


    /**
     * Get all possible items for this filter (regardless of whether they are configured to be available).
     *
     * If the selected set of items equals the full possible set, we can forgo the filter check in our DB query for a performance boost
     *
     * @return Collection (intentionally not a GenericCollection)
     */
    public function getAllPossibleItems();
}
