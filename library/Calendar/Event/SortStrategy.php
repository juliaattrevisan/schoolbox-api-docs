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
        $a_day = $a->getStartTime()->getUsingFormat('Ymd');
        $b_day = $b->getStartTime()->getUsingFormat('Ymd');
        if ($a_day < $b_day) {
            return -1;
        } else if ($a_day > $b_day) {
            return 1;
        }

        // Events on same day - all day events always before timed events
        $a_allday = $a->getAllday();
        $b_allday = $b->getAllday();
        if ($a_allday != $b_allday) {
            return $a_allday ? -1 : 1;
        }

        // Sort on start time of event
        $a_timestamp = $a->getStartTime()->getUnixTimestamp();
        $b_timestamp = $b->getStartTime()->getUnixTimestamp();
        if ($a_timestamp < $b_timestamp) {
            return -1;
        } else if ($a_timestamp > $b_timestamp) {
            return 1;
        }

        // Sort on end time of event
        $a_timestamp = $a->getEndTime()->getUnixTimestamp();
        $b_timestamp = $b->getEndTime()->getUnixTimestamp();
        if ($a_timestamp < $b_timestamp) {
            return -1;
        } else if ($a_timestamp > $b_timestamp) {
            return 1;
        }

        // Fallback to sorting on title
        return strcmp($a->getTitle(), $b->getTitle());
    }
}
