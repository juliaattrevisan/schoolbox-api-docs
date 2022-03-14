<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Blow away all the Event and Event Sources that never got updated during the import process
 */
class Cleanup implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var EventMapper
     */
    private EventMapper $eventMapper;


    /**
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     *
     * @var string
     */
    private string $source;


    /**
     * Base constructor.
     *
     * @param LoggerInterface   $log
     * @param bool              $perform
     * @param EventMapper       $eventMapper
     * @param string            $source
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        EventMapper $eventMapper,
        string $source
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->eventMapper = $eventMapper;

        /* Event source is an arbitrary string that helps identify the thing that imported
         * them into the local event table so you can do a mass remove if needed
         */
        $this->source = $source;
    }


    /**
     * Purge existing events so we don't have to worry about diffing their
     * relationships and stuff. It's going to be wrapped in a transaction
     * anyway, so if anything dies we roll back.
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function import(): void
    {
        if ($this->perform) {
            $deadEvents = $this->eventMapper->findByColumn('updated_at', '0000-00-00 00:00:00');

            foreach ($deadEvents as $deadEvent) {
                $this->log->debug(sprintf(
                    'Deleting %s event "%s"; it was never updated during import',
                    $this->source,
                    $deadEvent->getTitle()
                ));

                $this->eventMapper->delete($deadEvent);
            }
        }
    }
}
