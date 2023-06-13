<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Calendar\Event;

use Calendar\Event\Type\EventInterface;

/**
 * Standard sort strategy for calendar events.
 *
 * Sorts in this order:
 * - day of event, ascending
 * - all-day events first, then timed events
 * - time of event, ascending
 * - event's name, ascending
 */
class SortStrategy
{
    /**
     * Sort callback
     *
     * @param EventInterface $a
     * @param EventInterface $b
     * @return int
     */
    public function __invoke(EventInterface $a, EventInterface $b)
    {
        // Sort on day of event only (not time)
        $aDay = $a->getStartTime()->getUsingFormat('Ymd');
        $bDay = $b->getStartTime()->getUsingFormat('Ymd');
        if ($aDay < $bDay) {
            return -1;
        } else if ($aDay > $bDay) {
            return 1;
        }

        // Events on same day - all day events always before timed events
        $aAllday = $a->getAllday();
        $bAllday = $b->getAllday();
        if ($aAllday != $bAllday) {
            return $aAllday ? -1 : 1;
        }

        // Sort on start time of event
        $aTimestamp = $a->getStartTime()->getUnixTimestamp();
        $bTimestamp = $b->getStartTime()->getUnixTimestamp();
        if ($aTimestamp < $bTimestamp) {
            return -1;
        } else if ($aTimestamp > $bTimestamp) {
            return 1;
        }

        // Sort on end time of event
        $aTimestamp = $a->getEndTime()->getUnixTimestamp();
        $bTimestamp = $b->getEndTime()->getUnixTimestamp();
        if ($aTimestamp < $bTimestamp) {
            return -1;
        } else if ($aTimestamp > $bTimestamp) {
            return 1;
        }

        // Fallback to sorting on title
        return strcmp($a->getTitle(), $b->getTitle());
    }
}
