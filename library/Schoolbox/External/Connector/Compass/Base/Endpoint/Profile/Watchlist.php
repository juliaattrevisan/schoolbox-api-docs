<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Profile;

/**
 * For a string value, return whether it's list of strings $watchlist
 */
class Watchlist
{
    /**
     * Decide if $val is on the $watchlist
     *
     * @param string $val
     * @param array  $watchlist
     * @return bool
     */
    public function isOnWatchlist(string $val, array $watchlist): bool
    {
        if (!\count($watchlist)) {
            return true;
        }

        return in_array($val, $watchlist, false);
    }
}
