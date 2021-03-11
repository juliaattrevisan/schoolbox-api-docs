<?php

/**
 * Base interface for column type classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

use Schoolbox\Mapper;

interface IColumnType
{
    /**
     * Translates a value from a database field or other data store, into a
     * string, integer or object value for use in a model object.
     *
     * @param mixed  $value
     * @param Mapper $mapper
     *
     * @return mixed
     */
    public function hydrate($value, $mapper = null);

    /**
     * Translates a value from a value in a model object into a value for a
     * database field or other data store.
     *
     * @param mixed  $value
     * @param Mapper $mapper
     *
     * @return mixed
     */
    public function dehydrate($value, $mapper = null);


    /**
     * Returns whether the hydrated data type is *always* scalar
     *
     * For clarity, the following are not scalars:
     * * objects
     * * arrays
     *
     * @return bool
     */
    public function isScalar(): bool;
}
