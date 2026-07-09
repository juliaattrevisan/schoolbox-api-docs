<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar;

use DebugBar\DebugBarException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\Mapper\Exception;

/**
 * Specially handle data going into sis_event.
 *
 * Ported from the Veracross connector's EventHandler. A full mapper save()
 * rewrites every column of sis_event on every occurrence, which forces all
 * seven secondary indexes (guid, code, source, classification, type, location,
 * start_at) to be rebuilt per row even when nothing changed. For a calendar
 * expansion that is the dominant cost.
 *
 * To avoid it we:
 *   - INSERT brand new rows via the mapper as usual;
 *   - for an existing row whose fields are unchanged, only bump updated_at;
 *   - for an existing row with changes, UPDATE only the columns that differ.
 *
 * Prepared statements are reused across calls (keyed by query shape) so PDO
 * does not re-parse them per row.
 *
 * Mappers always save every column regardless of whether it changed, so we
 * can't lean on save() for the partial-update case — we craft the UPDATE here.
 */
class EventHandler
{
    /**
     * Prepared statements manufactured as we go, keyed by their SQL.
     *
     * @var array
     */
    private array $existingQueries = [];

    /**
     * Constructor, with optional logging.
     */
    public function __construct(private Mapper $eventMapper, private ?LoggerInterface $log = null)
    {
    }

    /**
     * Persist $newEvent, doing the least DB work necessary relative to $oldEvent.
     *
     * @throws Exception
     * @throws DebugBarException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function save(Model $newEvent, Model $oldEvent): void
    {
        /* Insert a whole new row just like the mapper always did.
         */
        if (!$newEvent->getId()) {
            $this->eventMapper->save($newEvent);

            return;
        }

        /* Diff. If empty, the objects are the same and we don't need to
         * rewrite the row — just mark it current via updated_at.
         */
        $diff = $this->eventMapper->diff($newEvent, $oldEvent);

        if (empty($diff)) {
            $params = [
                'id' => (int)$newEvent->getId(),
                'updatedAt' => \Alaress_Date_Format::now()->getDatabase(),
            ];

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

        /* Some part of $newEvent differs. Avoid nuking every column via the
         * mapper by crafting an UPDATE for only the columns that changed.
         */
        $pieces = [];

        $params = [
            'id' => (int)$newEvent->getId(),
            'updatedAt' => \Alaress_Date_Format::now()->getDatabase(),
        ];

        $schema = [];
        $mapping = $this->eventMapper->getMap();

        foreach ($mapping as $property => $data) {
            $schema[$property] = $data['column'];
        }

        foreach ($diff as $property => $values) {
            $column = $schema[$property];
            $getter = $this->eventMapper->getGetMethodName($property);

            if ($newEvent->$getter() !== $oldEvent->$getter()) {
                $pieces[] = sprintf('%s = :%s', $column, $property);

                /* Accommodate date to scalar conversions
                 */
                if (\in_array($column, ['start_at', 'finish_at'], true)) {
                    $params[$property] = $newEvent->$getter()->getDatabase();
                } else {
                    $params[$property] = $newEvent->$getter();
                }
            }
        }

        $query = "UPDATE {$this->eventMapper->getTable()}";

        if (\count($pieces)) {
            $query .= ' SET ';
            $query .= implode(', ', $pieces);
            $query .= ', updated_at = :updatedAt';
        } else {
            $query .= ' SET updated_at = :updatedAt';
        }

        $query .= " WHERE {$this->eventMapper->getTable()}.id = :id";

        if (!isset($this->existingQueries[$query])) {
            $this->existingQueries[$query] = $this->eventMapper->getDatabase()->prepare($query);
        }

        $statement = $this->existingQueries[$query];

        foreach ($params as $key => $value) {
            $statement->bindParam(":{$key}", $value);
        }

        $id = (int)$newEvent->getId();
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        $statement->execute($params);

        $this->logDebug(sprintf(
            '** Crafted update occurred for sis_event.id %s - subset diff',
            $newEvent->getId()
        ));
    }

    /**
     * If a logger exists, use it.
     */
    private function logDebug(string $message): void
    {
        $this->log?->debug($message);
    }
}
