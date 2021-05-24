<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

interface IType
{

    /**
     * Return an array of strings that represent the
     * enumeration this Type factory supports
     *
     * @return string[]
     */
    public static function getStringValues();


    /**
     * Return all the enumerations of this factory as a regex string,
     * optionally mutated by the given array_map callback
     *
     * @param callable $map
     * @return string
     */
    public static function getStringValuesAsRegex(callable $map = null);
}
