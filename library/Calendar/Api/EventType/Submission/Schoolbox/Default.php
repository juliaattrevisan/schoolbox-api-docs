<?php

/**
 * Calendar_Api_EventType_Submission_Schoolbox_Default
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use Calendar\Event\Collection;
use Calendar\Event\Type\Submission\Event;

class Calendar_Api_EventType_Submission_Schoolbox_Default extends Calendar_Api_EventType_Abstract
{
    /** @var Calendar */
    private $calendarHandler = null;

    /**
     * Constructor
     *
     * @param \Schoolbox\User $user
     * @param                 $config
     */
    public function __construct(\Schoolbox\User $user, $config)
    {
        $this->setCalendarHandler(new Calendar());
        $this->setUser($user);
    }

    /**
     * Gets the display color for an event.
     *
     * @param  \Schoolbox\Assessment\Activity $activity
     * @param  string                         $eventType
     * @return string
     */
    protected function getEventColor($activity, $eventType)
    {
        return $this->getCalendarHandler()->displayColour($eventType);
    }

    /**
     * Get day events from this data source
     *
     * @param $startTimestamp
     * @param $endTimestamp
     *
     * @internal param $currentDay
     * @return Collection|Submission[]
     */
    public function getEvents($startTimestamp, $endTimestamp)
    {
        $stack = new Collection();
        // Configuration may not have been set, take this into account.
        try {
            $eventType = $this->loadConnectorConfiguration()->decodeConfig()->getLuEventType();
        } catch (Schoolbox\Mapper\Exception $e) {
            $eventType = 1;
        }

        //
        // Use the mapper classes to get events for this day.
        // Also, need the categories to filter on.
        // Don't like using session here but other event types use them, so meh.
        $cat_mapper = Schoolbox\Resource\CategoryMapper::getInstance();
        $cat_mapper->setExternalUser(new External_User());
        $category_id_list = array();
        foreach ($cat_mapper->findForUser($this->getUser()) as $category) {
            $category_id_list[] = $category->getId();
        }

        $box_mapper    = Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance();
        $assess_mapper = Schoolbox\Assessment\Component\OnlineAssessmentMapper::getInstance();

        /** @var \Schoolbox\Assessment\ActivityCollection|\Schoolbox\Assessment\Activity[] $activities */
        $activities    = $box_mapper->getDueActivities($category_id_list, $startTimestamp, $endTimestamp);
        $activities->merge($assess_mapper->getDueActivities($category_id_list, $startTimestamp, $endTimestamp));

        foreach ($activities as $activity) {
            $event = new Event();
            $event->setCategory($activity->getCategory());
            $event->setType($eventType);
            $event->setColour($this->getEventColor($activity, $eventType));
            $event->setStartTime($activity->getDate());
            $event->setEndTime($activity->getDate());

            //
            // Will only be all day if the event's date is one second before
            // midnight.
            $event->setAllDay($activity->getDate()->isAtEndOfDay());

            //
            // Populate differently based on whether an assessment or submission
            if ($activity->isAssessment()) {
                $assessment = $activity->getAssessment();
                $owner      = $assessment->getUser();
                $event->setIsAssessment(true);
                $event->setEventId($assessment->getId());
                $event->setTitle($assessment->getTitle());
                $event->setDetail($assessment->getDescription());
                $event->setPublishDate($assessment->getStart());
            } else {
                $submission_box = $activity->getSubmissionBox();
                $owner          = $submission_box->getOwner();
                $event->setIsAssessment(false);
                $event->setEventId($submission_box->getId());
                $event->setTitle($submission_box->getName());
                $event->setDetail($submission_box->getDescription());
                $event->setPublishDate($submission_box->getPublishDate());
            }

            $event->setAuthorId($owner->getId());
            $event->setAuthorName($owner->getFirstName() . ' ' . $owner->getLastName());
            $event->setConnectorId($this->getConnectorId());

            $stack->add($event);
        }
        return $stack;
    }

    /**
     * Find a particular event from this source
     *
     * @param $eventId
     *
     * @return Collection
     */
    public function getEvent($eventId)
    {
        $collection = new Collection();

        /* Fill in your query result set here and populate $collection
         */

        return $collection;
    }

    /* Getters/Setters
     */

    /**
     * Set calendar handler
     *
     * @param Calendar $calendarHandler
     */
    public function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * Get calendar handler
     *
     * @return Calendar
     */
    public function getCalendarHandler()
    {
        return $this->calendarHandler;
    }
}
