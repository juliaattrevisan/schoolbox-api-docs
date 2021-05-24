<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Sync\Provisioner;

/**
 * Class UserAccessXOR
 *
 * Calculate the exclusive-or elements from both arrays
 * If the result is empty, then both arrays are the same
 *
 * Otherwise obtain a list of elements that are different
 * which is the combination of the following:
 *
 * elements in $array1 that aren't in $array2
 * elements in $array2 that aren't in $array1
 *
 * This also takes into account the access level as a change
 * there represents a change in behaviour elsewhere in the app
 *
 * @package Schoolbox\Sync\Provisioner
 */
class UserAccessXOR
{

    /**
     * The xor also must account for changes in access level,
     * which is the value of each tuple, since
     *
     * (123, 1)  // folder 123 has accesslevel 1
     * (123, 2)  // folder 123 has accesslevel 2
     *
     * ...represents a change in behaviour for users and therefore
     * a candidate to log in our diff of userAccess elements.
     * You can't compute the diff on just keys or just values;
     * you have to compare the combined effect
     *
     * Which means you're comparing against the serialization of data
     * then unserializing it later to see what its tuple really was
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public function generate(array $array1, array $array2): array
    {
        /* Serialize each array's contents
         *
         * We can take advantage of the schema definition that (uid, sid, level)
         * is a primary key and therefore, each user, (sid, level) is a unique tuple
         */
        $serialized1 = $serialized2 = [];

        /* I thought of json_encode, base64_encode, serialize() but concluded that ramming
         * everything together with a concat would be faster and easier to understand
         */
        foreach ($array1 as $key => $value) {
            $serialized1[] = "{$key}-{$value}";
        }

        foreach ($array2 as $key => $value) {
            $serialized2[] = "{$key}-{$value}";
        }

        /* Find the xor of this serialized data
         */
        $diff1 = array_diff($serialized1, $serialized2);
        $diff2 = array_diff($serialized2, $serialized1);

        $xor = array_merge($diff1, $diff2);

        /* Detonate the xor's contents back into the unserialized format
         * Now you can see the folder and accesslevel change separately
         */
        $diff = [];

        foreach ($xor as $current) {
            $bits = explode('-', $current);

            $diff[$bits[0]] = $bits[1];
        }

        return $diff;
    }
}
