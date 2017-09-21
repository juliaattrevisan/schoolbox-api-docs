<?php

/**
 * Helper
 *
 * This contains a mixed bag of extra functionality that doesn't really belong in Liss/Server.php
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace External\Liss\Helper;
use Event\Source\Model as EventSource;
use Event\Source\Mapper as EventSourceMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Calendar\Event;
use Schoolbox\Mapper\Exception as MapperException;

class PublishDailyData
{
    /**
     * Source Name
     */
    const EVENT_SOURCE_NAME = 'liss';

    /**
     * @var ConfigLoader
     */
    private $configLoader;

    /**
     * Helper constructor.
     * @param ConfigLoader $configLoader
     */
    public function __construct(ConfigLoader $configLoader)
    {
        $this->setConfigLoader($configLoader);
    }

    /**
     * Preprocess all this stuff
     * Make it easier to understand because their data structure is blasphemously terrible
     *
     * @param $currentEvent
     * @return array
     */
    public function reformatEvent($currentEvent)
    {
        /* Only need the first 8 chars
         * Assemble startDateTime and endDateTime
         */
        $date = substr($currentEvent->me['struct']['Date']->me['dateTime.iso8601'], 0, 4) . '-' . substr($currentEvent->me['struct']['Date']->me['dateTime.iso8601'], 4, 2) . '-' . substr($currentEvent->me['struct']['Date']->me['dateTime.iso8601'], 6, 2);
        $startDateTime = "{$date} {$currentEvent->me['struct']['StartTime']->me['string']}:00";
        $endDateTime = "{$date} {$currentEvent->me['struct']['EndTime']->me['string']}:00";

        /* Recompile everything
         */
        $restructured = [
            'rooms' => trim($currentEvent->me['struct']['Rooms']->me['string']),
            'classCode' => trim($currentEvent->me['struct']['ClassCode']->me['string']),
            'className' => trim($currentEvent->me['struct']['ClassName']->me['string']),
            'type' => trim($currentEvent->me['struct']['Type']->me['string']),
            'startDateTime' => new \Alaress_Date_Format($startDateTime, 'Y-m-d H:i:s'),
            'endDateTime' => new \Alaress_Date_Format($endDateTime, 'Y-m-d H:i:s'),
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
        foreach ($currentEvent->me['struct']['Students']->me['array'] as $currentStudent) {
            $restructured['students'][] = $currentStudent->me['string'];
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
        return self::EVENT_SOURCE_NAME . '_' . $this->getConfigLoader()->get('external_liss_schoolcode');
    }

    /**
     * Save an event source
     *
     * @param Event  $event
     * @param string $guid
     * @throws MapperException
     * @throws \PDOException
     */
    public function saveEventSource(Event $event, $guid)
    {
        /* Save a corresponding eventSource so we know from whence this event came
         */
        $eventSourceMapper = EventSourceMapper::getInstance();

        /** @var EventSource $eventSource */
        $eventSource = EventSourceMapper::getInstance()->createModel();
        $eventSource->setEvent($event);
        $eventSource->setSourceName($this->generateSourceName());
        $eventSource->setGuid($guid);
        $eventSourceMapper->save($eventSource);
    }

    /* Getters/Setters
     */

    /**
     * @return ConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->configLoader;
    }

    /**
     * @param ConfigLoader $configLoader
     * @return $this
     */
    public function setConfigLoader($configLoader)
    {
        $this->configLoader = $configLoader;
        return $this;
    }
}