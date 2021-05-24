<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;

/**
 * Knows how to map between an option.key and the item
 * represented by the key, and get the key from an item
 *
 * Must know how many possible items in each filter, so it can decide whether a
 * filter needs to be applied
 *
 * @see AbstractKeyConverter::possibleItemCount()
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
     * @param \Schoolbox\User                     $user
     * @param string                              $page
     * @return null|Collection|int[]|string[] (intentionally not GenericCollection as it only contains scalars)
     */
    public function getSelectedItems(GenericCollection $availableSelections, \Schoolbox\User $user, string $page): ?Collection;


    /**
     * Get the number of possible items for this filter.
     *
     * @return int
     */
    public function possibleItemCount(): int;
}
