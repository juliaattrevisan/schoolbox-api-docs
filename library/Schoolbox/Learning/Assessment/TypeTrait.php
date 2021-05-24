<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment;

/**
 * Class TypeTrait
 *
 * Reinforced by IType
 *
 * @package Schoolbox\Learning\Assessment
 */
trait TypeTrait
{

    /**
     * Return as a regex string, optionally mutated by an array_map function
     *
     * @param callable $map
     * @return string
     */
    public static function getStringValuesAsRegex(callable $map = null)
    {
        $values = self::getStringValues();

        if ($map) {
            $values = array_map($map, $values);
        }

        return '(?:' . implode('|', $values) . ')';
    }


    /**
     * @return string[]
     */
    public static function getStringValues()
    {
        // bypass the fact you can't have an abstract static method
        return (new self())->toArray();
    }


    /**
     * @return string[]
     */
    abstract protected function toArray();
}
