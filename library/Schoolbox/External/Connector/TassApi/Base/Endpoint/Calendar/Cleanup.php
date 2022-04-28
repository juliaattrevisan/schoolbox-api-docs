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
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


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
     * @param LoggerInterface                  $log
     * @param bool                             $perform
     * @param \Schoolbox\External\Event\Mapper $eventMapper
     * @param string                           $source
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        \Schoolbox\External\Event\Mapper $eventMapper,
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
     * @throws \PDOException
     */
    public function import(): void
    {
        if ($this->perform) {
            $queries = [
                [
                    'query' => "
                        DELETE sis_event_campus
                        FROM sis_event_campus
                        INNER JOIN sis_event ON sis_event.id = sis_event_campus.event_id AND sis_event.updated_at = '0000-00-00 00:00:00'
                        WHERE sis_event.source = :source
                    ",
                    'params' => [
                        'source' => $this->source
                    ]
                ],
                [
                    'query' => "
                        DELETE sis_event_roletype
                        FROM sis_event_roletype
                        INNER JOIN sis_event ON sis_event.id = sis_event_roletype.event_id AND sis_event.updated_at = '0000-00-00 00:00:00'
                        WHERE sis_event.source = :source       
                    ",
                    'params' => [
                        'source' => $this->source
                    ]
                ],
                [
                    'query' => "
                        DELETE FROM sis_event
                        WHERE updated_at = '0000-00-00 00:00:00'
                        AND sis_event.source = :source 
                    ",
                    'params' => [
                        'source' => $this->source
                    ]
                ],
            ];

            foreach ($queries as $setup) {
                $statement = $this->eventMapper->getDatabase()->prepare($setup['query']);
                $statement->execute($setup['params']);
            }

            $this->log->debug(sprintf(
                'Deleting all %s events that never updated during import',
                $this->source,
            ));
        }
    }
}
