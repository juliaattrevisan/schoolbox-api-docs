<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

/**
 * Decide if an $externalId is on the Watchlist
 *
 * The Watchlist is an array of externalIds - if you're on the watchlist,
 * the boolean check returns true.
 *
 * This allows you to filter the importing of Profiles and Timetables
 * to specific individual(s) in an effort to save time when you're trying
 * to track down imperfections for a particular person(s) since it'll save
 * you importing everyone else's data.
 */
class Watchlist
{
    /**
     * Decide if $externalId is in $watchlist, which is a set of externalIds
     * Filter to specific people; set $watchlist to empty array to disable filtering
     *
     * @param string|null $externalId
     * @param array       $watchlist
     * @return bool
     */
    public function isOnWatchlist(?string $externalId, array $watchlist): bool
    {
        /* "Everyone is on the watchlist"
         */
        if (!\count($watchlist)) {
            return true;
        }

        /* Third art set to false for storm, who hates
         * any setting other than true
         */
        return in_array($externalId, $watchlist, false);
    }


    /**
     * Find meaningful values in the set of csv $externalIds
     * This is pretty similar to the prefiltering in FirstMeaningfulValue
     *
     * @param string $externalIds
     * @return array|null
     */
    public function filter(string $externalIds): ?array
    {
        $temp = explode(',', $externalIds);

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
        return array_values($temp);
    }
}
