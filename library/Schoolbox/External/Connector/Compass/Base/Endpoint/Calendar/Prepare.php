<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Purge existing events so we don't have to worry about diffing their
 * relationships and stuff. It's going to be wrapped in a transaction
 * anyway, so if anything dies we roll back.
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
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


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
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param Mapper                                     $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param string                                     $source
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        string $source
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->eventMapper = $eventMapper;
        $this->overridesMapper = $overridesMapper;

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
        /* Set SUDS Events updated_at to something obvious
         * Anything that updates them will be safe from cleanup later
         */
        if ($this->perform) {
            /* Delete any overrides that existed
             */
            $this->overridesMapper->truncate();
            $this->log->debug('Purged timetable event overrides');

            $query = "
                UPDATE
                {$this->eventMapper->getTable()}
                SET {$this->eventMapper->getTable()}.updated_at = '0000-00-00 00:00:00'
                WHERE {$this->eventMapper->getTable()}.`source` = :source
            ";

            $statement = $this->eventMapper->getDatabase()->prepare($query);
            $statement->execute([
                'source' => $this->source
            ]);

            $this->log->debug(sprintf(
                'Marking all %s events for deletion',
                $this->source
            ));
        }
    }
}
