<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

use Event\Source\Mapper;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Purge events that used a specified event_source identifier
 * This ensures we only delete stuff imported via SUDS Compass
 */
class Prepare implements IStrategy
{

    /**
     * An arbitrarily-chosen string to denote where these events came from
     */
    public const EVENT_IMPORT_IDENTIFIER = 'suds_compass';


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var Mapper
     */
    private Mapper $eventSourceMapper;


    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param Mapper          $eventSourceMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Mapper $eventSourceMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;

        $this->eventSourceMapper = $eventSourceMapper;
    }


    /**
     * Delete all the events that used a particular source identifier
     *
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->eventSourceMapper->deleteEventsFromSource(self::EVENT_IMPORT_IDENTIFIER);

            $this->log->info(sprintf(
                'Purged existing events that used event_source: %s',
                self::EVENT_IMPORT_IDENTIFIER
            ));
        }
    }
}
