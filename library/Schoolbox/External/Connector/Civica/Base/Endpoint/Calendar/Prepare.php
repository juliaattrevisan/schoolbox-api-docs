<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Calendar;

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
     * Base constructor.
     *
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param Mapper $eventMapper
     * @param \Schoolbox\External\Event\User\Mapper $eventUserMapper
     * @param string $source
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\User\Mapper $eventUserMapper,
        private string $source
    ) {
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
            /* Delete any user involvements that existed
             */
            $query = "
                DELETE seu
                FROM {$this->eventUserMapper->getTable()} seu
                INNER JOIN {$this->eventMapper->getTable()} ON seu.event_id = {$this->eventMapper->getTable()}.id
                WHERE {$this->eventMapper->getTable()}.classification = :classification
                AND {$this->eventMapper->getTable()}.`source` = :source
            ";

            $statement = $this->eventUserMapper->getDatabase()->prepare($query);
            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_EVENT,
                'source' => $this->source,
            ]);

            $this->log->debug('Purged calendar event user involvement');

            /* Mark all suds events for deletion
             */
            $query = "
                UPDATE {$this->eventMapper->getTable()}
                SET updated_at = '0000-00-00 00:00:00'
                WHERE classification = :classification
                AND source = :source
            ";

            $statement = $this->eventMapper->getDatabase()->prepare($query);
            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_EVENT,
                'source' => $this->source,
            ]);

            $this->log->debug(sprintf(
                'Marking all %s events for deletion',
                $this->source
            ));
        }
    }
}
