<?php
/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\Column\Date;
use Schoolbox\External\Profile\Model as BaseProfile;
use Schoolbox\External\Profile\Parent\Model as ParentProfile;
use Schoolbox\External\Profile\Staff\Model as StaffProfile;
use Schoolbox\External\Profile\Student\Model as StudentProfile;
use Schoolbox\External\Event\Model as Event;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;

/**
 * Class Chandler
 *
 * Creates a custom UPDATE query specifically to address partial row updates
 * when you only need to update a subset of columns
 *
 * It's pseudopolymorphic and -circumstantially- works for Profile and TimetableCalendar
 * data, using the provided $mapper to do the diff and derive column, properties
 * and hydrator schemes.
 */
class Chandler
{
    /**
     * Constructor
     *
     * Pass in a base profile mapper, a student profile mapper, etc.
     *
     * @param Mapper               $mapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        protected Mapper $mapper,
        private ?LoggerInterface $log = null
    ) {
    }


    /**
     * Returns a scalar corresponding to the identifier for $newObject
     *
     * @param $newObject
     * @return int|null
     */
    protected function getIdentifierValue($newObject): ?int
    {
        $identifierGetter = $this->mapper->getGetIdentifierMethodName();

        return $newObject->$identifierGetter();
    }


    /**
     * A somewhat wonky pseudo-polymorphic rendition
     * All these profiles and mappers just happen to behave the same way
     *
     * TODO probably add to phpstan baseline to ignore mixed definitions
     *  as this could be used for TimetableCalendar imports too
     *
     * @param BaseProfile|StudentProfile|ParentProfile|StaffProfile|Event $newObject
     * @param BaseProfile|StudentProfile|ParentProfile|StaffProfile|Event $oldObject
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function save($newObject, $oldObject): void
    {
        /* Insert a whole new row just like we always did
         */
        $identifierColumn = $this->mapper->getIdentifierColumn();
        $identifierValue = $this->getIdentifierValue($newObject);

        if (!$this->getIdentifierValue($newObject)) {
            $this->log->debug(sprintf(
                '** Standard mapper save for %s.%s',
                $this->mapper->getTable(),
                $identifierColumn,
            ));

            $this->mapper->save($newObject);

            return;
        }

        /* Diff. If this is empty, the objects are the same
         * and we don't need to persist it again to the db
         */
        $diff = $this->mapper->diff($newObject, $oldObject);

        /* Objects' fields are the same; just set the updated_at field
         * so we know this record is current, though unchanged
         */
        if (empty($diff)) {
            $params = [
                $identifierColumn => $this->getIdentifierValue($newObject),
                'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
            ];

            /* Otherwise, craft the differences into a chandler update
             * and do that instead
             */
            $query = "
                UPDATE {$this->mapper->getTable()}
                SET updated_at = :updatedAt
                WHERE {$this->mapper->getTable()}.{$identifierColumn} = :{$identifierColumn}
            ";

            $statement = $this->mapper->getDatabase()->prepare($query);
            $statement->execute($params);

            $this->logDebug(sprintf(
                '** Crafted update occurred for %s.%s %s - changed updated_at only',
                $this->mapper->getTable(),
                $identifierColumn,
                $newObject->getId()
            ));

            return;
        }

        /* Some part of $newObject is different to $oldObject somehow
         *
         * Avoid nuking every column via mapper save by piecing it together manually
         * just like we used to do in the previous millenium
         */
        $pieces = [];

        $params = [
            $identifierColumn =>$this->getIdentifierValue($newObject),
            'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
        ];

        /* Find the column/mapping relationship
         *
         * This allows us to map properties that were different via $diff
         * into a custom crafted sql query that only updates columns that
         * had somehow changed, rather than the whole row
         */
        $schema = [];
        $mapping = $this->mapper->getMap();

        foreach ($mapping as $property => $data) {
            $schema[$property] = $data['column'];
        }

        /* Individually craft updates for differing columns only
         */
        foreach ($diff as $property => $values) {
            $column = $schema[$property];
            $getter = $this->mapper->getGetMethodName($property);

            if ($newObject->$getter() !== $oldObject->$getter()) {
                $pieces[] = sprintf(
                    '%s = :%s',
                    $column,
                    $property,
                );

                /* Accommodate date to scalar conversions
                 * Special case for base user profiles
                 */
                if ('baseProfile' === $property) {
                    $params[$property] = $identifierValue;
                } else if ($mapping[$property]['hydrator'] instanceof Date) {
                    $params[$property] = $newObject->$getter()->getDatabase();
                } else {
                    $params[$property] = $newObject->$getter();
                }
            }
        }

        /* Manufacture query
         */
        $query = "UPDATE {$this->mapper->getTable()}";

        if (\count($pieces)) {
            $query .= ' SET ';
            $query .= implode(', ', $pieces);
            $query .= ', updated_at = :updatedAt';
        } else {
            $query .= ' SET updated_at = :updatedAt';
        }

        $query .= " WHERE {$this->mapper->getTable()}.{$identifierColumn} = :{$identifierColumn}";

        /* Slap it into prepared statement
         */
        $statement = $this->mapper->getDatabase()->prepare($query);

        foreach ($params as $key => $value) {
            $statement->bindParam(":{$key}", $value);
        }

        /* Use the id
         */
        $id = $this->getIdentifierValue($newObject);
        $statement->bindParam(":{$identifierColumn}", $id, \PDO::PARAM_INT);
        $statement->execute($params);

        $this->logDebug(sprintf(
            '** Crafted update occurred for %s.%s %s - subset diff',
            $this->mapper->getTable(),
            $identifierColumn,
            $identifierValue
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
