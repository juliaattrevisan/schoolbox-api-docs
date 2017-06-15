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


    /**
     * For the user's posted selections (either checked or not checked), generate a collection
     * of items they represent.
     *
     * If the set of selections is the logical full set of that item type, and either all are
     * selected or none are selected, then return NULL to inform the DB query generator
     * that it can skip this filter entirely.
     *
     * @param GenericCollection|Selection\Model[] $availableSelections Indexed by setting.key
     * @return null|Collection|int[]|string[] (intentionally not GenericCollection as it only contains scalars)
     */
    public function getSelectedItems(GenericCollection $availableSelections);


    /**
     * Get all possible items for this filter (regardless of whether they are configured to be available).
     *
     * If the selected set of items equals the full possible set, we can forgo the filter check in our DB query for a performance boost
     *
     * @return Collection (intentionally not a GenericCollection)
     */
    public function getAllPossibleItems();
}
