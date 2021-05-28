<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector;

/**
 * Class FirstMeaningfulValue
 *
 * From an array of values, find the first meaningful value
 * The value must not be any of the following possibilities:
 *
 * null
 * an empty string
 * consist only of spaces
 *
 * If the array of values contains no meaningful values,
 * default to an empty string
 *
 * @package Schoolbox\External\Connector
 */
class FirstMeaningfulValue
{

    /**
     * Find the first meaningful value from a given set
     *
     * Returns a trimmed string, ready for ramming into suds
     *
     * @param array $values
     */
    public function find(array $values): string
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
            return !is_null($val) && '' !== $val;
        });

        /* Reindex the resultant array because the
         * array_filter preserved indexes
         */
        $temp = array_values($temp);

        /* Return the first item in the list,
         * or an empty string if that didn't exist
         */
        return $temp[0] ?? '';
    }
}