<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
     * @return mixed
     */
    public function hydrate($value, $mapper = null)
    {
        return explode(',', (string)$value);
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

} 