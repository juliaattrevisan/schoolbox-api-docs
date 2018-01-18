<?php

/**
 * Event type that represents days from the external timetable handler.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
    private $eventTypeMapper;


    /**
     * @inheritdoc
     */
    public function __construct(int $connectorId, User $user)
    {
        parent::__construct($connectorId, $user);
        $this->externalTimetable = new External_Timetable();
        $this->eventTypeMapper   = EventTypeMapper::getInstance();
    }

    /**
     * Get day events from this data source
     *
     * @param $startTimestamp
     * @param $endTimestamp
     *
     * @return Collection
     */
    public function getEvents($startTimestamp, $endTimestamp)
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
            $eventType = 1;
        }

        $eventColor = null;
        try {
            /** @var EventType $eventTypeObject */
            $eventTypeObject = $this->eventTypeMapper->findById($eventType);
            $eventColor      = $eventTypeObject->getColor();
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }

        $campusTitles = Array();
        $userCampuses = $this->getUser()->getCampusCollection();

        foreach ($userCampuses as $campus) {
            $campusName = $campus->getName();
            $campusCode = $campus->getExtTimetableCode();
            $campusTitles[$campusCode] = $campusTitles[$campusCode].'/'.$campusName;
        }

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.
        while ($currentTimestamp < $endTimestamp) {

            //Combine titles of campuses with the same code (if any)

            $campusMerged = Array();
            
            //Now build the stack
            foreach ($userCampuses as $campus) {

                $campusCode = $campus->getExtTimetableCode();
                //Check if a campus with the same timetable code hasn't already been added to the stack. Skip if yes.
                if(!array_key_exists($campusCode, $campusMerged)) {

                    $campusId  = $campus->getId();
                    $campusName = $campus->getName();
                    $dayNumber = $this->externalTimetable->getDayNumber($campus, $currentTimestamp);
                    if ($dayNumber <= 0) {
                        continue;
                    }
                    $dayName = $this->externalTimetable->getDayName($dayNumber);

                    $event = new Event();
                    $event->setAllday(true);
                    $event->setStartTime(
                        new Alaress_Date_Format(strtotime('00:00:00', $currentTimestamp), 'U')
                    );
                    $event->setEndTime(
                        new Alaress_Date_Format(strtotime('00:00:00', $currentTimestamp), 'U')
                    );
                    $event->setEventId(md5($campusId.$event->getStartTime()->getDatabaseDate()));
                    $event->setPublishDate(null);
                    $event->setTitle(sprintf("%s: %s", trim($campusTitles[$campusCode], '/'), $dayName));
                    $event->setCampusList($campusId);
                    $event->setAccess(Event::CAMPUS_ACCESS);
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
     * @param $eventId
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
