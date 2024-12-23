<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

/**
 * Class AllMeaningfulValues
 *
 * From an array of values, find all meaningful values
 * The value must not be any of the following possibilities:
 *
 * null
 * an empty string
 * consist only of spaces
 *
 * If the array of values contains no meaningful values,
 * default to an empty array
 *
 * @package Schoolbox\External\Connector
 */
class AllMeaningfulValues
{
    /**
     * Find all meaningful values from a given set of scalars
     *
     * Returns all values as trimmed strings, ready for ramming into suds
     *
     * @param array $values
     * @return array
     */
    public static function find(array $values): array
    {
        $temp = $values;

        /* Cast all the elements in $values to strings
         * because loosely typed data is poopy
         */
        $temp = array_map('strval', $temp);

        /* Trim all the strings in the set
         * because doing manual trimming is boring
         */
        $temp = array_map('trim', $temp);

        /* Filter out all the empty strings, null values
         * Use a custom callback so 0 is a valid outcome
         */
        $temp = array_filter($temp, static function ($val) {
            return '' !== $val;
        });

        /* Reindex the resultant array because the
         * array_filter preserved indexes
         */
        $temp = array_values($temp);

        /* Return the filtered list, possibly empty
         */
        return $temp;
    }

    /**
     * Find all unique meaningful values from a given set of scalars
     *
     * @param array $values
     * @return array
     */
    public static function findUnique(array $values): array
    {
        return array_unique(self::find($values));
    }
}
