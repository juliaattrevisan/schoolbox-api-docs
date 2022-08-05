<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

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
     * Filter to specific people; set to empty array to disable filtering
     */
    public const WATCHLIST = [];


    /**
     * Decide if $externalId is in $watchlist, which is a set of externalIds
     *
     * @param string|null $externalId
     * @return bool
     */
    public function isOnWatchlist(?string $externalId): bool
    {
        /* "Everyone is on the watchlist"
         */
        if (!\count(self::WATCHLIST)) {
            return true;
        }

        /* Third art set to false for storm, who hates
         * any setting other than true
         */
        return in_array($externalId, self::WATCHLIST, false);
    }
}
