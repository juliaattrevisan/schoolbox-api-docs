<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

/**
 * Decide if the given csv contains valid strategy selections
 * for the importers. Only some are supported. Any that aren't
 * supported are discarded quietly.
 *
 * This is case insensitive.
 * Returns an array of valid strats among those provided as args
 *
 * If you don't specify any strats, or if the result is an empty set,
 * then this will be interpreted as the default "import everything"
 */
class ValidStrats
{
    /**
     * These are valid as csv values in the arg
     */
    private const STRATS = [
        /* Loads staff profiles
         */
        'staff',

        /* Loads students' profiles only, excluding parents
         */
        'student',

        /* Loads student+parent profiles together
         */
        'families',

        /* Loads all timetable-related stuff
         */
        'timetable'
    ];


    /**
     * Decide what values of the csv string $csvArgs are valid strats
     * Anything outside of self::STRATS is ignored
     *
     * You can end up with an empty set which is assumed to mean
     * "import everything"
     *
     * @param string $csvArgs
     * @return array|null
     */
    public function filter(string $csvArgs): ?array
    {
        /* Split $csv args into individual pieces
         */
        $temp = explode(',', $csvArgs);

        /* Cast all the elements in $values to strings
         * because loosely typed data is poopy
         */
        $temp = array_map('strval', $temp);

        /* Lowercase everything
         */
        $temp = array_map('strtolower', $temp);

        /* Trim all the strings in the set
         * because doing manual trimming is boring
         */
        $temp = array_map('trim', $temp);

        /* Remove anything that doesn't also appear in self::STRATS
         * and ignore those
         */
        $temp = array_intersect(self::STRATS, $temp);

        /* Return only the valid strats chosen
         * Each factory interprets this as desired
         */
        return array_values($temp);
    }
}