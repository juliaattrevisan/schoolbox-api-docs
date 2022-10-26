<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

/**
 * For a given externalId, dump some meaningful contents
 */
class Watchlist
{
    /**
     * Decide if $externalId is on the $watchlist
     *
     * @param string $externalId
     * @param array  $watchlist
     * @return bool
     */
    public function isOnWatchlist(string $externalId, array $watchlist): bool
    {
        if (!\count($watchlist)) {
            return true;
        }

        return in_array($externalId, $watchlist, false);
    }
}
