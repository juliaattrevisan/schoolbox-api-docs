<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

use DebugBar\DebugBarException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\Mapper\Exception;

/**
 * Prototype for specially handlerising data going into sis_event
 *
 * There's a hypothesis that changing an indexed field causes
 * some kind of corruption to occur after a transaction is committed
 * leading to substantial performance impacts
 *
 * To avoid this we can use a raw query to only update sis_event.updated_at
 * if none of its other fields have changed, for example, thus avoiding
 * any alleged reindexing woes.
 *
 * Mappers always save every column regardless if they've been changed or
 * not so you can't just "do a diff" but you have to go back to the old
 * Handler ways from Handler days
 */
class EventHandler
{
    /**
     * A list of (sql, prepared statement) tuples that we will have
     * manufactured as we progress through the strats
     *
     * @var array
     */
    private array $existingQueries = [];


    /** Constructor, with optional logging
     *
     * @param Mapper               $eventMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(private Mapper $eventMapper, private ?LoggerInterface $log = null)
    {
    }


    /**
     * Specially persist $newEvent
     *
     * @param Model $newEvent
     * @param Model $oldEvent
     * @return void
     * @throws Exception
     * @throws DebugBarException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function save(Model $newEvent, Model $oldEvent): void
    {
        /* Insert a whole new row just like we always did
         */
        if (!$newEvent->getId()) {
            $this->eventMapper->save($newEvent);

            return;
        }

        /* Diff. If this is empty, the objects are the same
         * and we don't need to persist it again to the db
         */
        $diff = $this->eventMapper->diff($newEvent, $oldEvent);

        /* Objects' fields are the same; just set the updated_at field
         * so we know this record is current, though unchanged
         */
        if (empty($diff)) {
            $params = [
                'id' => (int)$newEvent->getId(),
                'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
            ];

            /* Manufacture query
             */
            $query = "
                UPDATE {$this->eventMapper->getTable()}
                SET updated_at = :updatedAt
                WHERE {$this->eventMapper->getTable()}.id = :id
            ";

            if (!isset($this->existingQueries[$query])) {
                $this->existingQueries[$query] = $this->eventMapper->getDatabase()->prepare($query);
            }

            $statement = $this->existingQueries[$query];
            $statement->execute($params);

            $this->logDebug(sprintf(
                '** Crafted update occurred for sis_event.id %s - changed updated_at only',
                $newEvent->getId()
            ));

            return;
        }

        /* Some part of $newEvent is different to $oldEvent somehow
         *
         * Avoid nuking every column via mapper save by piecing it together manually
         * just like we used to do in the previous millennium
         */
        $pieces = [];

        $params = [
            'id' => (int)$newEvent->getId(),
            'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
        ];

        /* Find the column/mapping relationship
         *
         * This allows us to map properties that were different via $diff
         * into a custom crafted sql query that only updates columns that
         * had somehow changed, rather than the whole row
         */
        $schema = [];
        $mapping = $this->eventMapper->getMap();

        foreach ($mapping as $property => $data) {
            $schema[$property] = $data['column'];
        }

        /* Individually craft updates for differing columns only
         */
        foreach ($diff as $property => $values) {
            $column = $schema[$property];
            $getter = $this->eventMapper->getGetMethodName($property);

            if ($newEvent->$getter() !== $oldEvent->$getter()) {
                $pieces[] = sprintf(
                    '%s = :%s',
                    $column,
                    $property,
                );

                /* Accommodate date to scalar conversions
                 */
                if (\in_array($column, ['start_at', 'finish_at'], true)) {
                    $params[$property] = $newEvent->$getter()->getDatabase();
                } else {
                    $params[$property] = $newEvent->$getter();
                }
            }
        }

        /* Manufacture query
         */
        $query = "UPDATE {$this->eventMapper->getTable()}";

        if (\count($pieces)) {
            $query .= ' SET ';
            $query .= implode(', ', $pieces);
            $query .= ', updated_at = :updatedAt';
        } else {
            $query .= ' SET updated_at = :updatedAt';
        }

        $query .= " WHERE {$this->eventMapper->getTable()}.id = :id";

        /* Slap it into prepared statement
         */
        if (!isset($this->existingQueries[$query])) {
            $this->existingQueries[$query] = $this->eventMapper->getDatabase()->prepare($query);
        }

        $statement = $this->existingQueries[$query];

        foreach ($params as $key => $value) {
            $statement->bindParam(":{$key}", $value);
        }

        /* Use the id
         */
        $id = (int)$newEvent->getId();
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        $statement->execute($params);

        $this->logDebug(sprintf(
            '** Crafted update occurred for sis_event.id %s - subset diff',
            $newEvent->getId()
        ));
    }


    /**
     * If logger exists, use it
     *
     * @param string $message
     * @return void
     */
    private function logDebug(string $message): void
    {
        $this->log?->debug($message);
    }
}
