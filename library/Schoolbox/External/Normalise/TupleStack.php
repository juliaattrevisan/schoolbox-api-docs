<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Normalise;

/**
 * Class TupleStack
 *
 * Take an array of (key-values) structs and do some preprocessing on them,
 * removing empty strings and dupes. Depending on the method you use, you
 * can return a stack, or retain the key-value structure
 *
 * @package Schoolbox\External\Normalise
 */
class TupleStack
{

    /**
     * Take an array of values and do some preprocessing on them
     * Returns a stack of unique, trimmed and non-empty data
     *
     * Only processes scalars. Non-scalars are discarded from the result set.
     *
     * @param array       $stack  A one-dimensional stack of scalars
     * @param string|null $split  Optionally, a char used to split values
     * @return array
     */
    public function normaliseValues(array $stack, string $split = null): array
    {
        /* If you specified a character to split upon,
         * iterate and explode, then merge the exploded pieces in to $processed
         */
        if (null !== $split) {
            $processed = [];

            foreach ($stack as $value) {
                /* Skip some bad data
                 */
                if (is_scalar($value)) {
                    $processed = array_merge($processed, preg_split('/[' . $split . ']/', (string)$value));
                }
            }
        } else {
            $processed = $stack;
        }

        /* Then trim every value, filter and uniquify using built-in methods
         * Reindex the stack
         *
         * Return the cleansed and indexed stack of value
         */
        return array_map('trim', array_values(array_unique(array_filter($processed, static function ($value) {
            return is_scalar($value) && '' !== trim((string)$value);
        }))));
    }


    /**
     * Take an array of (key-values) structs and do some preprocessing on them:
     *   - remove dupes from $values
     *   - trim all elements inside $values
     *   - remove anything falsey like null, empty string from $values
     *   - filter and uniquify remaining $values
     *   - reset numerical indexes on $values
     *
     * If $values is a scalar, it'll get put into its own stack
     * If values is null, it'll be given an empty stack
     *
     * Other usages, such as having an array of arrays of arrays of objects will start
     * returning warnings, and maybe unexpected outcomes; this should predominantly be
     * used to filter (key-values) stacks where values is a stack of scalars.
     *
     * @param array $stack  A set of key-values structs
     * @return array
     */
    public function normaliseTuples(array $stack): array
    {
        $processed = [];

        foreach ($stack as $key => $values) {
            /* If $values is an array we can cleanse its contents en-masse with
             * existing in-built array functions; trim/filter/unique/reset indexes
             */
            if (is_array($values)) {
                $processed[$key] = array_values(array_unique(array_filter(array_map('trim', $values))));
            } elseif (is_scalar($values)) {
                /* Scalars get shoved into their own array
                 * (Alternatively you can omit the scalar; it may be wrong entirely)
                 */
                $processed[$key] = array_filter([trim((string)$values)]);
            } else {
                /* Nulls and other unknown types just get an empty stack
                 * (Alternatively you can omit the key entirely; it has no intrinsic meaning)
                 */
                $processed[$key] = [];
            }
        }

        /* Return the finished version
         */
        return $processed;
    }
}
