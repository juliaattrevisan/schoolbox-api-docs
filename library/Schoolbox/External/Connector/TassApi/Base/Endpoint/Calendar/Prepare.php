<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Event\Source\Mapper as EventSourceMapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Purge existing events so we don't have to worry about diffing their
 * relationships and stuff. It's going to be wrapped in a transaction
 * anyway, so if anything dies we roll back.
 */
class Prepare implements IStrategy
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
     * @var EventSourceMapper
     */
    private EventSourceMapper $eventSourceMapper;


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
     * @param EventSourceMapper $eventSourceMapper
     * @param string            $source
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        EventMapper $eventMapper,
        EventSourceMapper $eventSourceMapper,
        string $source
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;

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
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function import(): void
    {
        /* Set Events and Event Sources updated_at to something obvious
         * Anything that updates them will be safe from cleanup later
         */
        if ($this->perform) {
            $query = "
                UPDATE
                {$this->eventSourceMapper->getTable()}
                INNER JOIN {$this->eventSourceMapper->getJoinSql('event')}
                AND {$this->eventSourceMapper->getTable()}.`deleted_at` IS NULL
                SET {$this->eventMapper->getTable()}.updated_at = '0000-00-00 00:00:00',
                {$this->eventSourceMapper->getTable()}.updated_at = '0000-00-00 00:00:00'
                WHERE {$this->eventSourceMapper->getTable()}.`source_name` = :sourceName
            ";

            $statement = $this->eventSourceMapper->getDatabase()->prepare($query);
            $statement->execute([
                'sourceName' => $this->source
            ]);

            $this->log->debug(sprintf(
                'Marking all %s events for deletion',
                $this->source
            ));
        }
    }
}
