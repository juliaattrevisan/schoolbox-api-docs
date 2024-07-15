<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

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
        /* A list of fields that are indexed in the db schema
         *
         * Classification, though indexed, should never change in this current usage
         * It should always be 'timetable'
         *
         * TODO Probably could generate this in a more dynamic way via the mapper
         *  but may not be worth the effort right now; not sure if this helps
         */
        $fields = [
            'title' => 'title',
            'detail' => 'detail',
            'location' => 'location',
            'code' => 'code',
            'day' => 'day',
            'period' => 'period',
            'start_at' => 'startAt',
            'finish_at' => 'finishAt',
            'type' => 'type',
            'guid' => 'guid',
            'classification' => 'classification',
            'source' => 'source'
        ];

        /* Insert a whole new row just like we always did
         */
        if (!$newEvent->getId()) {
            $this->eventMapper->save($newEvent);
        } else {
            $params = [
                'id' => (int)$newEvent->getId(),
                'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
            ];

            $pieces = [];

            foreach ($fields as $column => $property) {
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
            $statement = $this->eventMapper->getDatabase()->prepare($query);

            foreach ($params as $key => $value) {
                $statement->bindParam(":{$key}", $value);
            }

            /* Use the id
             */
            $id = (int)$newEvent->getId();
            $statement->bindParam(':id', $id, \PDO::PARAM_INT);
            $statement->execute($params);

            $this->logDebug(sprintf(
                'Crafted update occurred for sis_event.id %s',
                $newEvent->getId()
            ));
        }
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
