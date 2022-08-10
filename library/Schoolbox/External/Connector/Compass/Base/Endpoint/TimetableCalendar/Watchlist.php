<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

/**
 * For a given activity name, dump some meaningful contents
 */
class Watchlist
{
    /**
     * Decide if $activityCode is on the $watchlist
     *
     * @param string $activityCode
     * @param array  $watchlist
     * @return bool
     */
    public function isOnWatchlist(string $activityCode, array $watchlist): bool
    {
        if (!\count($watchlist)) {
            return true;
        }

        return in_array($activityCode, $watchlist, false);
    }


    /**
     * Each activity instance comes with a bunch of data that is hard to read
     * such as compassPersonIds - these aren't externalIds, so you have to find
     * out who they are via the $staffLookup
     *
     * @param array $activityInstances
     * @param array $staffLookup
     * @return array
     */
    public function translate(array $activityInstances, array $staffLookup): array
    {
        $stack = [];

        foreach ($activityInstances as $instance) {
            $i = $instance;

            /* Try to identify these people via their compass id
             */
            $i['managingCompassPersonId'] = $staffLookup[$instance['managingCompassPersonId']] ?? $instance['managingCompassPersonId'];
            $i['coveringCompassPersonId'] = $staffLookup[$instance['coveringCompassPersonId']] ?? $instance['coveringCompassPersonId'];

            foreach ($instance['attendees'] as $attendee) {
                $i['attendees'][] = [
                    'compassPersonId' => $staffLookup[$attendee['compassPersonId']] ?? $attendee['compassPersonId'],
                    'coveringCompassPersonId' => $staffLookup[$attendee['coveringCompassPersonId']] ?? $attendee['coveringCompassPersonId'],
                ];
            }

            $stack[] = $i;
        }

        /* sort instances ffs, because instances occur in a random haphazard order
         * but are actually chronologically ordered
         */
        usort($stack, static function ($a, $b) {
            if ($a['start'] < $b['start']) {
                return -1;
            }

            if ($a['start'] === $b['start']) {
                return 0;
            }

            return 1;
        });

        return $stack;
    }
}
