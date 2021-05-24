<?php

/**
 * ValidDateOffset
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

class ValidDateOffset
{

    /**
     * Determine if one of the date offset policies contains a parseable value
     * Use string to time to decide with its linguistical bmf
     *
     * @param string $dateOffset
     * @return bool
     */
    public function isSatisfiedBy(string $dateOffset): bool
    {
        return false !== strtotime(trim($dateOffset));
    }
}
