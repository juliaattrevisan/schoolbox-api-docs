<?php

/**
 * Lister
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Collection;

use Calendar\Event\Collection;
use Schoolbox\User;

class Lister extends Renderer
{
    /**
     * Constructor
     *
     * @param $api
     * @param $startTimestamp
     * @param $endTimestamp
     * @param $viewer
     * @param $page
     */
    public function __construct($api, $startTimestamp, $endTimestamp, User $viewer, \SitePage $page)
    {
        parent::__construct($api, $startTimestamp, $endTimestamp, $viewer, $page);

        /* Detonate multi-day events into their individual day ingredients
         * Merje and sort
         */
        $this->setAllEvents($this->detonate());
        $this->getAllEvents()->sort();
    }

    /**
     * Find out if there are multi-day events which also occur
     * on this day - if there are, render out an all-day instance
     * if this isn't its end day. Do this by exploding multi-day
     * events into its constituent parts, due to edge cases that
     * occur in the foreach()
     *
     * e.g. only one multi-day event
     *
     * Turned out to be easier to simply clone and explode, then
     * re-sort on date. This is a detonate-and-merge.
     */
    private function detonate()
    {
        $startTime = new \Alaress_Date_Format($this->getStartTimestamp(), \Alaress_Date_Format::TIMESTAMP);
        $endTime   = new \Alaress_Date_Format($this->getEndTimestamp(), \Alaress_Date_Format::TIMESTAMP);

        $newCollection = new Collection;

        foreach ($this->getAllEvents() as $currentEvent) {
            if ($currentEvent->getStartTime()->getDatabaseDate() != $currentEvent->getEndTime()->getDatabaseDate()) {

                // Event is multiday so add clones for each day in our timespan

                if($currentEvent->getStartTime()->gt($startTime)) {
                    $eventStartTime = clone $currentEvent->getStartTime();
                }
                else {
                    $eventStartTime = clone $startTime;
                }

                if($currentEvent->getEndTime()->lt($endTime)) {
                    $eventEndTime = clone $currentEvent->getEndTime();
                }
                else {
                    $eventEndTime = clone $endTime;
                }

                for($i=0; $eventStartTime->lt($eventEndTime); $i++)  {
                    $clone      = clone($currentEvent);
                    $clone->setStartTime(clone $eventStartTime);
                    $clone->setEndTime(clone $eventStartTime);

                    $clone->setAllDay(true);

                    $newCollection->add($clone);

                    // Increment day counter
                    // Ensure start time is start of day for comparing against end time
                    $eventStartTime->startOfDay()->addDays(1);
                }
            }
            else {
                $newCollection->add($currentEvent);
            }
        }

        return $newCollection;
    }
}
