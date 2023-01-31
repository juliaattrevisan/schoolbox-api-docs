<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

/**
 * Takes a set of included value_list records and converts
 * them into a convenient lookup format to save you immense
 * quantities of traversing
 *
 * According to Veracross,
 *     "An array of value list items whose id field matches the value in the response data"
 *
 * TODO Find out if the contents of a value_list changes depending
 *  on what ids were present in the payload; that might force you
 *  to compile value_lists PER PAYLOAD which is what we currently do
 */
class ValueListConverter
{
    /**
     * $valueLists is all the lookups in a paginated payload
     * This reindexes them based on key/value tuples, in order
     * they were given (pre-sorted by Veracross)
     *
     * @param array $valueLists
     * @return array
     */
    public function generate(array $valueLists): array
    {
        $stack = [];

        /* Reindex each value list
         */
        foreach ($valueLists as $valueList) {
            $data = [];

            foreach ($valueList['items'] as $value) {
                $data[$value['id']] = $value['description'];
            }

            $stack[$valueList['fields'][0]] = $data;
        }

        return $stack;
    }
}
