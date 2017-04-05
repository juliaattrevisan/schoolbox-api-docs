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
     * For the user's posted selections, generate a collection of items they represent
     *
     * @param GenericCollection|Selection\Model[] $selections Indexed by setting.key
     * @return Collection (intentionally not GenericCollection)
     * @throws \InvalidArgumentException
     */
    public function getSelectedItems(GenericCollection $selections);
}
