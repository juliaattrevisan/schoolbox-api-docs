<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

/**
 * Combine $activityInstances with $sessions, based on start time
 */
class RecombineSessions
{
    /**
     * $sessions contains teachers who sometimes take precedence over those
     * specified in $activityInstances - these session teachers can be
     * arbitrarily spawned in Compass itself
     *
     * @param array $activityInstances
     * @param array $sessions
     * @return array
     */
    public function recombine(array $activityInstances, array $sessions): array
    {
        /* Recombine $enrolments['sessions'] with each $activityInstance
         * The combination of these allows us to find another place where
         * teachers of a class could be defined
         *
         * The only way to marry the two together is by start/finish
         */
        $combinedInstances = [];

        foreach ($activityInstances as $activityInstance) {
            $combinedInstance = $activityInstance;

            /* Set aside a special little cubby house for the associated
             * Session elements if any exist - additional teachers are here
             */
            $combinedInstance['sessions'] = [];

            foreach ($sessions as $session) {
                if ($activityInstance['start'] === $session['start']) {
                    $combinedInstance['sessions'] = $session;
                }
            }

            $combinedInstances[] = $combinedInstance;
        }

        /* sort instances ffs, because instances occur in a random haphazard order
         * but are actually chronologically ordered
         */
        usort($combinedInstances, static function ($a, $b) {
            if ($a['start'] < $b['start']) {
                return -1;
            }

            if ($a['start'] === $b['start']) {
                return 0;
            }

            return 1;
        });

        return $combinedInstances;
    }
}
