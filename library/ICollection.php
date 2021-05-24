<?php

/**
 * Defined interface for both Alex's mapper Collection class and my
 * Schoolbox\Collection class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
interface ICollection extends IteratorAggregate, Countable
{
    /**
     * Add item to end of this collection
     *
     * @param mixed $value
     */
    public function add($value);

    /**
     * Returns entire collection as an array.
     *
     * @return array
     */
    public function getAll();


    /**
     * Each ICollection implementation knows how to create an empty clone of itself
     *
     * @return static
     */
    public function emptyClone();
}
