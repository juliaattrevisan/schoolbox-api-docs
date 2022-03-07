<?php

/**
 * Event type that represents days from the external timetable handler.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Calendar\Event\Collection;
use Calendar\Event\Type\Timetable\Event;
use Schoolbox\User;

class Calendar_Api_EventType_TimetableDays_External_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * @var External_Timetable
     */
    private $externalTimetable;

    /**
     * @var EventTypeMapper
     */
    private EventTypeMapper $eventTypeMapper;


    /**
     * @inheritdoc
     */
    public function __construct(int $connectorId, User $user, User $viewer, \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper)
    {
        parent::__construct($connectorId, $user, $viewer);
        $this->externalTimetable = new External_Timetable();
        $this->eventTypeMapper   = $eventTypeMapper;
    }


    /**
     * Get day events from this data source
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): Collection
    {
        $stack            = new Collection();
        $currentTimestamp = $startTimestamp;

        // Figure out event type
        try {
            /** @var Calendar_Api_Config_Record $config */
            $config        = $this->loadConnectorConfiguration();
            $decodedConfig = $config->decodeConfig();
            $eventType     = $decodedConfig->getLuEventType();
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Something went awry with loadConnectorConfiguration()
             * Can't determine what event type this would be
             */
            $eventType = null;
        }

        /* Ensure $eventType is valid, finding first available one if it is invalid.
         * If you can't find one, we can't assign anything to the events from this subscription
         * so just return the empty stack; there's no way we could categorise those events
         *
         * getLuEventType() can return a deleted event type identifier.
         */
        $eventType = $this->eventTypeMapper->findFirstMeaningfulId($eventType);

        /* Do a detection on $allowedEventTypes, if provided and meaningful
         */
        if (null !== $allowedEventTypes && \count($allowedEventTypes)) {
            if (!\in_array((int)$eventType, $allowedEventTypes, true)) {
                return $stack;
            }
        }

        $eventColor = null;
        try {
            /** @var EventType $eventTypeObject */
            $eventTypeObject = $this->eventTypeMapper->findById($eventType);
            $eventColor      = $eventTypeObject->getColor();
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }

        $campusTitles = array();
        $userCampuses = $this->getUser()->getCampusCollection();

        foreach ($userCampuses as $campus) {
            $campusName = $campus->getName();
            $campusCode = $campus->getExtTimetableCode();
            if (isset($campusTitles[$campusCode])) {
                $campusTitles[$campusCode] .= '/' . $campusName;
            } else {
                $campusTitles[$campusCode] = $campusName;
            }
        }

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.
        while ($currentTimestamp < $endTimestamp) {
            //Combine titles of campuses with the same code (if any)

            $campusMerged = array();

            //Now build the stack
            foreach ($userCampuses as $campus) {
                $campusCode = $campus->getExtTimetableCode();
                //Check if a campus with the same timetable code hasn't already been added to the stack. Skip if yes.
                if (!array_key_exists($campusCode, $campusMerged)) {
                    $campusId  = $campus->getId();
                    $campusName = $campus->getName();
                    $dayNumber = $this->externalTimetable->getDayNumber($campus, $currentTimestamp);
                    if ($dayNumber <= 0) {
                        continue;
                    }
                    $dayName = $this->externalTimetable->getDayName($dayNumber);

                    $event = new Event();
                    $event->setAllDay(true);
                    $event->setStartTime(
                        new Alaress_Date_Format(strtotime('00:00:00', $currentTimestamp), 'U')
                    );
                    $event->setEndTime(
                        new Alaress_Date_Format(strtotime('00:00:00', $currentTimestamp), 'U')
                    );
                    $event->setEventId(md5($campusId . $event->getStartTime()->getDatabaseDate()));
                    $event->setPublishDate(null);
                    $event->setTitle(sprintf("%s: %s", trim($campusTitles[$campusCode], '/'), $dayName));
                    $event->setCampusList($campusId);
                    $event->setAccess(Event::CAMPUS_ACCESS);

                    /* Note that setType sets the event type's id and
                     * setEventType sets the event type's name, essentially
                     * decomposing the object into its constituent pieces
                     */
                    $event->setType($eventType);

                    $event->setColour($eventColor);
                    $event->setConnectorId($this->getConnectorId());

                    //Keep codes of processed campuses
                    $campusMerged[$campusCode] = $campusId;
                    $stack->add($event);
                }
            }
            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }
        return $stack;
    }

    /**
     * Find a particular event from this source
     *
     * @param int $eventId
     *
     * @return Collection
     */
    public function getEvent($eventId)
    {
        // Blah
        $stack = new Collection();
        return $stack;
    }
}
