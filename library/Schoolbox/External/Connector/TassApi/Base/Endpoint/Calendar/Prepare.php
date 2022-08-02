<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
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
        /* Set SUDS Events updated_at to something obvious
         * Anything that updates them will be safe from cleanup later
         */
        if ($this->perform) {
            $query = "
                UPDATE
                {$this->eventMapper->getTable()}
                SET {$this->eventMapper->getTable()}.updated_at = '0000-00-00 00:00:00'
                WHERE {$this->eventMapper->getTable()}.`source` = :source
                AND classification = :classification
            ";

            $statement = $this->eventMapper->getDatabase()->prepare($query);
            $statement->execute([
                'source' => $this->source,
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_EVENT
            ]);

            $this->log->debug(sprintf(
                'Marking all %s events for deletion',
                $this->source
            ));
        }
    }
}
