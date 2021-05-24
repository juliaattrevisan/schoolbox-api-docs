<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class BoolEnum implements IColumnType
{

    /**
     * Translates a value from a database field or other data store, into a
     * string, integer or object value for use in a model object.
     *
     * @param string $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return bool
     */
    public function hydrate($value, $mapper = null)
    {
        // convert string to boolean
        return $value === 'true' ? true : false;
    }

    /**
     * Translates a value from a value in a model object into a value for a
     * database field or other data store.
     *
     * @param bool $value
     * @param \Schoolbox\Mapper $mapper
     *
     * @return string
     */
    public function dehydrate($value, $mapper = null)
    {
        return $value == true ? 'true' : 'false';
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return true;
    }
}
