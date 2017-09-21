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