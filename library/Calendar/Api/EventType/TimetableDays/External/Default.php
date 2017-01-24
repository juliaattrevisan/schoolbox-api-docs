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


class Calendar_Api_EventType_TimetableDays_External_Default extends Calendar_Api_EventType_Abstract
{

    /**
     * @var External_Timetable
     */
    protected $externalTimetable;

    /**
     * @var EventTypeMapper
     */
    protected $eventTypeMapper;

    /**
     * @var Calendar_Api_Config_Mapper
     */
    protected $configMapper;

    /**
     * Constructor
     *
     * @param \Schoolbox\User $user
     * @param                 $config
     */
    public function __construct(\Schoolbox\User $user, $config)
    {
        $this->setUser($user);
        $this->setExternalTimetable(new External_Timetable());
        $this->setEventTypeMapper(EventTypeMapper::getInstance());
        $this->setConfigMapper(Calendar_Api_Config_Mapper::getInstance());
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
        $currentTimestamp = $startTimestamp;

        // Collection that holds the list of events to be rendered

        $stack            = new Collection();

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
            $eventTypeObject = $this->getEventTypeMapper()->findById($eventType);
            $eventColor      = $eventTypeObject->getColor();
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }

        $days = [];

        // Scan through each day from $startTimestamp to $endTimestamp, figuring
        // out that day's timetable day number for each of the user's campuses.
        while ($currentTimestamp < $endTimestamp) {
            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $campusId  = $campus->getId();
                $campusName = $campus->getName();
                $dayNumber = $this->getExternalTimetable()->getDayNumber($campus, $currentTimestamp);
                if ($dayNumber <= 0) {
                    continue;
                }
                $dayName = $this->getExternalTimetable()->getDayName($dayNumber);

                $event = new Event();
                $event->setAllday(true);
                $event->setStartTime(new Alaress_Date_Format(strtotime('00:00:00', $currentTimestamp), 'U'));
                $event->setEndTime(new Alaress_Date_Format(strtotime('00:00:00', $currentTimestamp), 'U'));
                $event->setEventId(md5($campusId.$event->getStartTime()->getDatabaseDate()));
                $event->setPublishDate(null);
                $event->setCampusList($campusId);
                $event->setTitle(sprintf("%s: %s", $campusName, $dayName));
                $event->setAccess(Event::CAMPUS_ACCESS);
                $event->setType($eventType);
                $event->setColour($eventColor);
                $event->setConnectorId($this->getConnectorId());

                // Store the event in a temporary array so we can deduplicate when there are multiple campuses

                $index = "{$dayNumber}_{$campus->getExtTimetableCode()}";

                if (!isset($days[$index])) {
                    $days[$index] = [];
                }

                $days[$index]['event'] = $event;

                if (!isset($days[$index]['campus'])) {
                    $days[$index]['campus'] = [];
                }

                $days[$index]['records'][] = [
                    'campusName' => $campusName,
                    'dayName' => $dayName
                ];
            }

            $currentTimestamp = strtotime('+1 day', $currentTimestamp);
        }

        // Deduplicate multiple campuses

        foreach ($days as $day) {

            /** @var Event $event */
            $event = $day['event'];

            // Check how many campuses there were

            if (count($day['records']) == 1) {

                // There was only one campus, just shunt the appointment in as it was

                $stack->add($event);

            } else {

                // There is more than one campus, need to mangle the title to contain both campus names

                $title = '';
                $dayName = '';

                foreach ($day['records'] as $record) {
                    if ($title != '') {
                        $title .= " / ";
                    }
                    $title .= $record['campusName'];
                    $dayName = $record['dayName'];
                }

                $event->setTitle($title . ": {$dayName}");
                $stack->add($event);
            }
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

    /**
     * @param  External_Timetable $externalTimetable
     * @return $this
     */
    public function setExternalTimetable($externalTimetable)
    {
        $this->externalTimetable = $externalTimetable;
        return $this;
    }

    /**
     * @return External_Timetable
     */
    public function getExternalTimetable()
    {
        return $this->externalTimetable;
    }

    /**
     * @param  EventTypeMapper $eventTypeMapper
     * @return $this
     */
    public function setEventTypeMapper($eventTypeMapper)
    {
        $this->eventTypeMapper = $eventTypeMapper;
        return $this;
    }

    /**
     * @return EventTypeMapper
     */
    public function getEventTypeMapper()
    {
        return $this->eventTypeMapper;
    }

    /**
     * @param  Calendar_Api_Config_Mapper $configMapper
     * @return $this
     */
    public function setConfigMapper($configMapper)
    {
        $this->configMapper = $configMapper;
        return $this;
    }

    /**
     * @return Calendar_Api_Config_Mapper
     */
    public function getConfigMapper()
    {
        return $this->configMapper;
    }
}
