<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class Set implements IColumnType
{

    /**
     * Translates a value from a database field or other data store, into a
     * string, integer or object value for use in a model object.
     *
     * @param string            $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return array
     */
    public function hydrate($value, $mapper = null)
    {
        $value = (string)$value;
        return $value ? explode(',', $value) : [];
    }


    /**
     * Translates a value from a value in a model object into a value for a
     * database field or other data store.
     *
     * @param mixed             $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return string
     */
    public function dehydrate($value, $mapper = null)
    {
        return implode(',', (array)$value);
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return false;
    }
}
