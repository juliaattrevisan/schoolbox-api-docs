<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

/**
 * Knows how to map between an option.key and the entity
 * represented by the key, and get the key from an entity
 */
interface IKeyConverter
{

    /**
     * @return Model
     */
    public function getFilter();

    /**
     * Given a key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @param string $key
     * @return mixed
     */
    public function getItem($key);


    /**
     * For a given option, return its key
     *
     * @param mixed $element
     * @return string
     */
    public function getKey($element);

    /**
     * Sanitises a key before insertion in filter_selection table
     * @param string $key
     * @return string
     * @todo should this throw an exception if the key is invalid ? Or just return an empty string?
     */
    public function sanitiseKey($key);
}
