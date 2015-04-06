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
     * User
     *
     * @var \Schoolbox\User
     */
    private $user = null;

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
        $this->setAllEvents($this->detonate($this->getAllEvents()));
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
        $collection = new Collection();

        foreach ($this->getAllEvents() as $currentEvent) {

            /* Same-day event, just throw it into the pile
             */
            if ($currentEvent->getStartTime()->getDatabaseDate() == $currentEvent->getEndTime()->getDatabaseDate()) {
                $collection->add($currentEvent);
            } /* Otherwise, multi-day event - detonate it
             */
            else {

                /* Special note to Renderer class
                 * Alter the end date of the first instance, so it doesn't
                 * display the end time at the start of the span
                 */
                $currentEvent->setCustom(
                    array(
                        'hideEnd' => true
                    )
                );

                $collection->add($currentEvent);

                /* Independence Day!
                 * Free clones from their imperial counterparts
                 */
                $clone      = clone($currentEvent);
                $cloneStart = new \Alaress_Date_Format($currentEvent->getStartTime()->getUnixTimestamp(), 'U');
                $clone->setStartTime($cloneStart);

                /* Add each detonated instance into collection
                 */
                while ($clone->getStartTime()->addDays(1)->getDatabaseDate() < $currentEvent->getEndTime()->getDatabaseDate()) {

                    /* Set all-day flag for the clones
                     */
                    $clone->setAllDay(true);
                    $collection->add($clone);

                    /* Self replicate, self independence
                     */
                    $clone = clone($clone);
                    $clone->setStartTime(new \Alaress_Date_Format($clone->getStartTime()->getUnixTimestamp(), 'U'));
                }

                /* This is the day on which start matches end.
                 * Display datetime in a special way
                 */
                $startDate = $clone->getStartTime()->getDatabaseDate();
                $startTime = $currentEvent->getStartTime()->getDatabaseTime();

                $clone = clone($clone);

                /* Reset AllDay status conditionally
                 */
                if (!$currentEvent->getAllDay()) {
                    $clone->setAllDay(false);
                }

                $clone->setStartTime(new \Alaress_Date_Format("{$startDate} {$startTime}", 'Y-m-d H:i:s'));

                /* Special note to Renderer class
                 */
                $clone->setCustom(
                    array(
                        'hideStart' => true
                    )
                );

                $collection->add($clone);
            }
        }

        return $collection;
    }
}
