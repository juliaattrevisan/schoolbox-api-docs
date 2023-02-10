<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile;

use DebugBar\DebugBarException;
use Psr\Log\LoggerInterface;
use Schoolbox\Column\Date;
use Schoolbox\Mapper\Exception;
use \Schoolbox\External\Profile\Model as BaseProfile;
use \Schoolbox\External\Profile\Mapper as BaseProfileMapper;
use \Schoolbox\External\Profile\Student\Model as StudentProfile;
use \Schoolbox\External\Profile\Student\Mapper as StudentProfileMapper;
use \Schoolbox\External\Profile\Parent\Model as ParentProfile;
use \Schoolbox\External\Profile\Parent\Mapper as ParentProfileMapper;
use \Schoolbox\External\Profile\Staff\Model as StaffProfile;
use \Schoolbox\External\Profile\Staff\Mapper as StaffProfileMapper;

/**
 * Prototype for specially handlerising data going into sis_user_*
 *
 * There's a hypothesis that changing an indexed field causes
 * some kind of corruption to occur after a transaction is committed
 * leading to substantial performance impacts
 *
 * To avoid this we can use a raw query to only update sis_user_*.updated_at
 * if none of its other fields have changed, for example, thus avoiding
 * any alleged reindexing woes.
 *
 * Mappers always save every column regardless if they've been changed or
 * not so you can't just "do a diff" but you have to go back to the old
 * Handler ways from Handler days
 */
class ProfileHandler
{
    /**
     * Constructor, with optional logging
     *
     * @param BaseProfileMapper    $baseProfileMapper
     * @param StudentProfileMapper $studentProfileMapper
     * @param ParentProfileMapper  $parentProfileMapper
     * @param StaffProfileMapper   $staffProfileMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        private BaseProfileMapper $baseProfileMapper,
        private StudentProfileMapper $studentProfileMapper,
        private ParentProfileMapper $parentProfileMapper,
        private StaffProfileMapper $staffProfileMapper,
        private ?LoggerInterface $log = null
    ) {
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param StudentProfile $newProfile
     * @param StudentProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveStudentProfile(
        StudentProfile $newProfile,
        StudentProfile $oldProfile
    ): void {
        /* Compare BaseProfile differences first
         */
        $this->chandle($newProfile->getBaseProfile(), $oldProfile->getBaseProfile(), $this->baseProfileMapper);

        /* ...then the roletype-specific variant
         */
        $this->chandle($newProfile, $oldProfile, $this->studentProfileMapper);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param ParentProfile $newProfile
     * @param ParentProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveParentProfile(
        ParentProfile $newProfile,
        ParentProfile $oldProfile
    ): void {
        /* Compare BaseProfile differences first
         */
        $this->chandle($newProfile->getBaseProfile(), $oldProfile->getBaseProfile(), $this->baseProfileMapper);

        /* ...then the roletype-specific variant
         */
        $this->chandle($newProfile, $oldProfile, $this->parentProfileMapper);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param StaffProfile $newProfile
     * @param StaffProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function saveStaffProfile(
        StaffProfile $newProfile,
        StaffProfile $oldProfile
    ): void {
        /* Compare BaseProfile differences first
         */
        $this->chandle($newProfile->getBaseProfile(), $oldProfile->getBaseProfile(), $this->baseProfileMapper);

        /* ...then the roletype-specific variant
         */
        $this->chandle($newProfile, $oldProfile, $this->staffProfileMapper);
    }


    /**
     * A somewhat wonky pseudo-polymorphic rendition
     * All these profiles and mappers just happen to behave the same way
     *
     * @param BaseProfile|StudentProfile|ParentProfile|StaffProfile                         $newObject
     * @param BaseProfile|StudentProfile|ParentProfile|StaffProfile                         $oldObject
     * @param BaseProfileMapper|StudentProfileMapper|ParentProfileMapper|StaffProfileMapper $mapper
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function chandle(
        BaseProfile|StudentProfile|ParentProfile|StaffProfile $newObject,
        BaseProfile|StudentProfile|ParentProfile|StaffProfile $oldObject,
        BaseProfileMapper|StudentProfileMapper|ParentProfileMapper|StaffProfileMapper $mapper,
    ): void {
        /* Insert a whole new row just like we always did
         */
        if (!$newObject->getId()) {
            $mapper->save($newObject);

            return;
        }

        /* Diff. If this is empty, the objects are the same
         * and we don't need to persist it again to the db
         */
        $diff = $mapper->diff($newObject, $oldObject);

        /* Objects' fields are the same; just set the updated_at field
         * so we know this record is current, though unchanged
         */
        if (empty($diff)) {
            $params = [
                'id' => (int)$newObject->getId(),
                'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
            ];

            /* Otherwise, craft the differences into a chandler update
             * and do that instead
             */
            $query = "
                UPDATE {$mapper->getTable()}
                SET updated_at = :updatedAt
                WHERE {$mapper->getTable()}.id = :id
            ";

            $statement = $mapper->getDatabase()->prepare($query);
            $statement->execute($params);

            $this->logDebug(sprintf(
                '** Crafted update occurred for %s.id %s - changed updated_at only',
                $mapper->getTable(),
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
            'id' => (int)$newObject->getId(),
            'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
        ];

        /* Find the column/mapping relationship
         *
         * This allows us to map properties that were different via $diff
         * into a custom crafted sql query that only updates columns that
         * had somehow changed, rather than the whole row
         */
        $schema = [];
        $mapping = $mapper->getMap();

        foreach ($mapping as $property => $data) {
            $schema[$property] = $data['column'];
        }

        /* Individually craft updates for differing columns only
         */
        foreach ($diff as $property => $values) {
            $column = $schema[$property];
            $getter = $mapper->getGetMethodName($property);

            if ($newObject->$getter() !== $oldObject->$getter()) {
                $pieces[] = sprintf(
                    '%s = :%s',
                    $column,
                    $property,
                );

                /* Accommodate date to scalar conversions
                 */
                if ($mapping[$property]['hydrator'] instanceof Date) {
                    $params[$property] = $newObject->$getter()->getDatabase();
                } else {
                    $params[$property] = $newObject->$getter();
                }
            }
        }

        /* Manufacture query
         */
        $query = "UPDATE {$mapper->getTable()}";

        if (\count($pieces)) {
            $query .= ' SET ';
            $query .= implode(', ', $pieces);
            $query .= ', updated_at = :updatedAt';
        } else {
            $query .= ' SET updated_at = :updatedAt';
        }

        $query .= " WHERE {$mapper->getTable()}.id = :id";

        /* Slap it into prepared statement
         */
        $statement = $mapper->getDatabase()->prepare($query);

        foreach ($params as $key => $value) {
            $statement->bindParam(":{$key}", $value);
        }

        /* Use the id
         */
        $id = (int)$newObject->getId();
        $statement->bindParam(':id', $id, \PDO::PARAM_INT);
        $statement->execute($params);

        $this->logDebug(sprintf(
            '** Crafted update occurred for %s.id %s - subset diff',
            $mapper->getTable(),
            $newObject->getId()
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
