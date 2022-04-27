<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

use Event\Source\Mapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventMapper;
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
     * @var EventMapper
     */
    private EventMapper $eventMapper;


    /**
     * @var Mapper
     */
    private Mapper $eventSourceMapper;


    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param EventMapper     $eventMapper
     * @param Mapper          $eventSourceMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        EventMapper $eventMapper,
        Mapper $eventSourceMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;

        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;
    }


    /**
     * Delete all the events that used a particular source identifier
     *
     * @throws \PDOException
     */
    public function import(): void
    {
        if ($this->perform) {
            $queries = [];

            /* Mark events for deletion to avoid a mega where-in clause
             */
            $queries['Event Primer'] = [
                'query' => "
                    UPDATE `event` e
                    INNER JOIN event_source s on e.id = s.event_id
                    SET e.updated_at = '0000-00-00 00:00:00'
                    WHERE s.source_name = :sourceName
                ",
                'params' => [
                    'sourceName' => self::EVENT_IMPORT_IDENTIFIER
                ]
            ];

            /* Avoid using the eventSourceMapper to delete things via objects etc.
             * It is super super slow for heaps of events, such as a huge compass import
             *
             * Just assassinate them directly
             */
            $queries['Event Attendance Relations'] = [
                'query' => '
                    DELETE a
                    FROM `event` e
                    INNER JOIN event_attendance a ON a.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
                'params' => [
                    'sourceName' => self::EVENT_IMPORT_IDENTIFIER
                ]
            ];

            $queries['Event Campus Relations'] = [
                'query' => '
                    DELETE c
                    FROM `event` e
                    INNER JOIN event_campus c ON c.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
                'params' => [
                    'sourceName' => self::EVENT_IMPORT_IDENTIFIER
                ]
            ];

            $queries['Event Folder Relations'] = [
                'query' => '
                    DELETE f
                    FROM `event` e
                    INNER JOIN event_folder f ON f.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
                'params' => [
                    'sourceName' => self::EVENT_IMPORT_IDENTIFIER
                ]
            ];

            $queries['Event Role Relations'] = [
                'query' => '
                    DELETE r
                    FROM `event` e
                    INNER JOIN event_role r ON r.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
                'params' => [
                    'sourceName' => self::EVENT_IMPORT_IDENTIFIER
                ]
            ];

            $queries['Event User Relations'] = [
                'query' => '
                    DELETE u
                    FROM `event` e
                    INNER JOIN event_user u ON u.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
                'params' => [
                    'sourceName' => self::EVENT_IMPORT_IDENTIFIER
                ]
            ];

            $queries['Event Source'] = [
                'query' => '
                    DELETE FROM event_source
                    WHERE source_name = :sourceName
                ',
                'params' => [
                    'sourceName' => self::EVENT_IMPORT_IDENTIFIER
                ]
            ];

            $queries['Event Detonator'] = [
                'query' => "
                    DELETE FROM event
                    WHERE updated_at = '0000-00-00 00:00:00'
                ",
                'params' => null
            ];

            $queries['SIS Classes'] = [
                'query' => '
                    DELETE FROM sis_class
                ',
                'params' => null
            ];

            $queries['SIS Class Involvements'] = [
                'query' => '
                    DELETE FROM sis_class_user
                ',
                'params' => null
            ];

            $this->log->debug(sprintf(
                'Beginning the great purge of existing events/relations that used event_source: %s',
                self::EVENT_IMPORT_IDENTIFIER
            ));

            $this->log->debug('This might take a while.');
            $this->log->debug('');

            /* Delete relations
             */
            foreach ($queries as $name => $setup) {
                $this->log->debug(sprintf(
                    'Executing %s purge...',
                    $name
                ));

                $statement = $this->eventSourceMapper->getDatabase()->prepare($setup['query']);

                if (null !== $setup['params']) {
                    $statement->execute($setup['params']);
                } else {
                    $statement->execute();
                }

                $this->log->debug('Done.');
            }

            $this->log->info(sprintf(
                'Purged existing events/relations that used event_source: %s',
                self::EVENT_IMPORT_IDENTIFIER
            ));
        }
    }
}
