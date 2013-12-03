<?php
/**
 * Event type that represents events from the Schoolbox timetable.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Calendar_Api_EventType_Timetable_Schoolbox_Default extends Calendar_Api_EventType_Abstract {

    /**
     * @var External_Timetable
     */
    protected $externalTimetable = null;

    /**
     * Constructor
     *
     * @param \Schoolbox\User $user
     * @param $config
     */
    public function __construct(\Schoolbox\User $user, $config) {
        $this->setUser($user);
        $this->externalTimetable = new External_Timetable();
    }

    /**
     * Get day events from this data source
     *
     * @param $startTimestamp
     * @param $endTimestamp
     * @return Calendar_Event_Collection
     */
    public function getEvents($startTimestamp, $endTimestamp) {
        $stack = new Calendar_Event_Collection();
        $dayTimetable = array();
        $currentTimestamp = $startTimestamp;

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.
        while ($currentTimestamp <= $endTimestamp) {
            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $campusId  = $campus->getId();
                $dayNumber = $this->getExternalTimetable()->getDayNumber($campusId, $currentTimestamp);
                if ($dayNumber <= 0) {
                    continue;
                }
                // Cache timetable for this campus/day combination
                if (!isset($dayTimetable[$dayNumber][$campusId])) {
                    $dayTimetable[$dayNumber][$campusId] = $this->getExternalTimetable()->getDayTimetable(
                        $this->getUser()->getExternalId(),
                        $this->getUser()->getId(),
                        $campusId,
                        $dayNumber
                    );
                }
                // Load events for this campus
                foreach ($dayTimetable[$dayNumber][$campusId] as $period) {
                    if (isset($period['description'])) {
                        $event = new Calendar_Event_Type_Day();
                        // Make this up as type: campus for now
                        // It also has a category.
                        $event->setType(3);
                        $event->setAllday(false);
                        $event->setLocation($period['room']);
                        $event->setStartTime(new Alaress_Date_Format(strtotime($period['periodStart'], $currentTimestamp), 'U'));
                        $event->setEndTime(new Alaress_Date_Format(strtotime($period['periodEnd'], $currentTimestamp), 'U'));
                        // Currently event needs a publish date.
                        $event->setPublishDate($event->getStartTime());
                        $event->setNote($period['description']);
                        $event->setCampusList($campusId);
                        $stack->add($event);
                    }
                }
            }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        return $stack;
    }

    /**
     * Find a particular event from this source
     *
     * @param $eventId
     * @return Calendar_Event_Collection
     */
    public function getEvent($eventId) {
        // Blah
        $stack = new Calendar_Event_Collection();
        return $stack;
    }

    /**
     * @param  \External_Timetable $externalTimetable
     *
     * @return $this
     */
    public function setExternalTimetable($externalTimetable) {
        $this->externalTimetable = $externalTimetable;
        return $this;
    }

    /**
     * @return \External_Timetable
     */
    public function getExternalTimetable() {
        return $this->externalTimetable;
    }
}
