<?php

/**
 * Helper
 *
 * This contains a mixed bag of extra functionality that doesn't really belong in Liss/Server.php
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace External\Liss\Helper;

use Event\Source\Model as EventSource;
use Event\Source\Mapper as EventSourceMapper;
use Schoolbox\Calendar\Event;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use xmlrpcval;

class PublishDailyData
{
    /**
     * Source Name
     */
    const EVENT_SOURCE_NAME = 'liss';

    /**
     * @var IConfigLoader
     */
    private $configLoader;

    /**
     * Helper constructor.
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * Remove all events using this source
     */
    public function deleteEventsFromSource()
    {
        EventSourceMapper::getInstance()->deleteEventsFromSource($this->generateSourceName());
    }

    /**
     * Preprocess all this stuff
     * Make it easier to understand because their data structure is blasphemously terrible
     *
     * @param array $allData
     * @return array
     */
    public function reformatAllEvents($allData)
    {
        $allEvents = [];

        foreach ($allData as $currentEvent) {
            $allEvents[] = $this->reformatEvent($currentEvent);
        }

        return $allEvents;
    }

    /**
     * Preprocess all this stuff
     * Make it easier to understand because their data structure is blasphemously terrible
     *
     * @param xmlrpcval $currentEvent
     * @return array
     */
    private function reformatEvent($currentEvent)
    {
        $date = date('Y-m-d', strtotime($currentEvent->me['struct']['Date']->me['dateTime.iso8601']));
        $startDateTime = "{$date} {$currentEvent->me['struct']['StartTime']->me['string']}:00";
        $endDateTime = "{$date} {$currentEvent->me['struct']['EndTime']->me['string']}:00";

        /* Recompile everything
         */
        $restructured = [
            'rooms' => trim($currentEvent->me['struct']['Rooms']->me['string']),
            'classCode' => trim($currentEvent->me['struct']['ClassCode']->me['string']),
            'className' => trim($currentEvent->me['struct']['ClassName']->me['string']),
            'type' => trim($currentEvent->me['struct']['Type']->me['string']),
            'startDateTime' => new \Alaress_Date_Format($startDateTime, \Alaress_Date_Format::DATABASE_FORMAT),
            'endDateTime' => new \Alaress_Date_Format($endDateTime, \Alaress_Date_Format::DATABASE_FORMAT),
            'period' => trim($currentEvent->me['struct']['Period']->me['string']),
            'guid' => trim($currentEvent->me['struct']['Guid']->me['string']),
            'detail' => '',
            'students' => [],

            /* Assemble Teachers
             * No idea why this has to be a csv, but students is an array...wevs
             */
            'teacherIds' => explode(',', $currentEvent->me['struct']['TeacherIds']->me['string']),
        ];

        /* Assemble Students
         */
        if (isset($currentEvent->me['struct']['Students'])) {
            foreach ($currentEvent->me['struct']['Students']->me['array'] as $currentStudent) {
                $restructured['students'][] = $currentStudent->me['string'];
            }
        }

        return $restructured;
    }

    /**
     * Generate the source_name value used in event_status table
     *
     * @return string
     */
    public function generateSourceName()
    {
        return self::EVENT_SOURCE_NAME . '_' . $this->configLoader->get('external_liss_schoolcode');
    }

    /**
     * Save a corresponding eventSource so we know from whence this event came
     *
     * @param Event  $event
     * @param string $guid
     * @throws MapperException
     * @throws \PDOException
     */
    public function saveEventSource(Event $event, $guid)
    {
        $eventSourceMapper = EventSourceMapper::getInstance();

        /** @var EventSource $eventSource */
        $eventSource = EventSourceMapper::getInstance()->createModel();
        $eventSource->setEvent($event);
        $eventSource->setSourceName($this->generateSourceName());
        $eventSource->setGuid($guid);
        $eventSourceMapper->save($eventSource);
    }
}
