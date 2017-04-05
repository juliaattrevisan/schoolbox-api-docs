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
 * @see AbstractKeyConverter::getAllPossibleItems()
 */
interface IKeyConverter
{

    /**
     * @return Model
     */
    public function getFilter();


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
     * selected or none are selected, then return an empty collection to inform the DB query generator
     * that it can skip this filter entirely.
     *
     * @param GenericCollection|Selection\Model[] $availableSelections Indexed by setting.key
     * @return Collection (intentionally not GenericCollection)
     * @throws \InvalidArgumentException
     */
    public function getSelectedItems(GenericCollection $availableSelections);
}
