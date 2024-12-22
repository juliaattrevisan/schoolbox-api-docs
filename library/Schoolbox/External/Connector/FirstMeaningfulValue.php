<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

use Schoolbox\External\Connector\AllMeaningfulValues;

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
     * Find the first meaningful value from a given set of scalars
     *
     * Returns a trimmed string, ready for ramming into suds
     *
     * @param array $values
     * @return string
     */
    public function find(array $values): string
    {
        $allMeaningfulValues = AllMeaningfulValues::find($values);

        /* Return the first item in the list,
         * or an empty string if that didn't exist
         */
        return $allMeaningfulValues[0] ?? '';
    }
}
