<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\Column\Date;
use Schoolbox\External\Profile\Mapper as BaseProfileMapper;
use Schoolbox\External\Profile\Model as BaseProfile;
use Schoolbox\External\Profile\Parent\Mapper as ParentProfileMapper;
use Schoolbox\External\Profile\Parent\Model as ParentProfile;
use Schoolbox\External\Profile\Staff\Mapper as StaffProfileMapper;
use Schoolbox\External\Profile\Staff\Model as StaffProfile;
use Schoolbox\External\Profile\Student\Mapper as StudentProfileMapper;
use Schoolbox\External\Profile\Student\Model as StudentProfile;
use Schoolbox\Mapper\Exception;

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
     * Used for converting a baseProfile instance into a scalar
     */
    private const BASE_PROFILE_PROPERTY = 'baseProfile';


    /**
     * A list of (sql, prepared statement) tuples that we will have
     * manufactured as we progress through the strats
     *
     * @var array
     */
    private array $existingQueries = [];


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
     * @param BaseProfile $newProfile
     * @param BaseProfile $oldProfile
     * @return void
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function saveBaseProfile(
        BaseProfile $newProfile,
        BaseProfile $oldProfile
    ): void {
        /* The student/parent/staff profiles need to know a base profile's id
         * so we can't chandler that; we'd never know the autoincrementing id
         * that was given to the base profile. Use the regular mapper instead,
         * and incur the cost; we'll make some savings in the subprofiles such
         * as student/parent/staff specific profiles
         *
         * (new Chandler($this->baseProfileMapper, $this->log))
         *     ->save($newProfile, $oldProfile);
         */
        $this->baseProfileMapper->save($newProfile);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param StudentProfile $newProfile
     * @param StudentProfile $oldProfile
     * @return void
     */
    public function saveStudentProfile(
        StudentProfile $newProfile,
        StudentProfile $oldProfile
    ): void {
        $this->save($this->studentProfileMapper, $newProfile, $oldProfile);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param ParentProfile $newProfile
     * @param ParentProfile $oldProfile
     * @return void
     */
    public function saveParentProfile(
        ParentProfile $newProfile,
        ParentProfile $oldProfile
    ): void {
        $this->save($this->parentProfileMapper, $newProfile, $oldProfile);
    }


    /**
     * Specially craft updates for the given profiles and mappers
     *
     * @param StaffProfile $newProfile
     * @param StaffProfile $oldProfile
     * @return void
     */
    public function saveStaffProfile(
        StaffProfile $newProfile,
        StaffProfile $oldProfile
    ): void {
        $this->save($this->staffProfileMapper, $newProfile, $oldProfile);
    }


    /**
     * A somewhat wonky pseudo-polymorphic rendition
     * All these profiles and mappers just happen to behave the same way
     *
     * TODO probably add to phpstan baseline to ignore mixed definitions
     *  as this could be used for TimetableCalendar imports too
     *
     * @param                                                       $mapper
     * @param BaseProfile|StudentProfile|ParentProfile|StaffProfile $newObject
     * @param BaseProfile|StudentProfile|ParentProfile|StaffProfile $oldObject
     * @return void
     */
    public function save(
        $mapper,
        BaseProfile|StudentProfile|ParentProfile|StaffProfile $newObject,
        BaseProfile|StudentProfile|ParentProfile|StaffProfile $oldObject
    ): void {
        /* Shortcuts to some facts
         */
        $identifierColumn = $mapper->getIdentifierColumn();
        $identifierValue = $newObject->getBaseProfile()->getId();

        /* Due to sequential dependencies, a base profile should always already exist;
         * this part shouldn't happen at all
         */
        if (!$identifierValue) {
            $this->log->debug(sprintf(
                '!! Standard mapper save for %s.%s %s',
                $mapper->getTable(),
                $identifierColumn,
                $identifierValue
            ));

            $mapper->save($newObject);

            return;
        }

        /* If a subprofile does not already exist with this sis_user_profile id,
         * we can assume the subprofile can be mapper->save()'d for its first time
         *
         * Otherwise, if this object already exists in the db, we can update it
         *
         * In any case, build a custom query to decide if something exists without
         * having to hydrate the answer. We can recycle prepared statements so if
         * we made one earlier, we can reuse it; otherwise make a new one. You can't
         * tell when such a query exists.
         */
        $existingQuery = "
            SELECT COUNT(*) AS num
            FROM {$mapper->getTable()}
            WHERE {$identifierColumn} = :{$identifierColumn}
        ";

        if (!isset($this->existingQueries[$existingQuery])) {
            $this->existingQueries[$existingQuery] = $mapper->getDatabase()->prepare($existingQuery);
        }

        $existingStatement = $this->existingQueries[$existingQuery];

        $existingStatement->execute([
            $identifierColumn => $identifierValue
        ]);

        /* This subprofile hasn't existed yet, but its base profile does exist
         * Create it for the first time with a regular mapper save
         */
        if (!$existingStatement->fetch(\PDO::FETCH_ASSOC)['num']) {
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
                $identifierColumn => $identifierValue,
                'updatedAt' => \Alaress_Date_Format::now()->getDatabase()
            ];

            /* Otherwise, craft the differences into a chandler update
             * and do that instead
             */
            $query = "
                UPDATE {$mapper->getTable()}
                SET updated_at = :updatedAt
                WHERE {$mapper->getTable()}.{$identifierColumn} = :{$identifierColumn}
            ";

            if (!isset($this->existingQueries[$query])) {
                $this->existingQueries[$query] = $mapper->getDatabase()->prepare($query);
            }

            $statement = $this->existingQueries[$query];

            $statement->execute($params);

            $this->logDebug(sprintf(
                '** Crafted update occurred for %s.%s %s - changed updated_at only',
                $mapper->getTable(),
                $identifierColumn,
                $identifierValue
            ));

            return;
        }

        /* Some part of $newObject is different to $oldObject somehow
         *
         * Avoid nuking every column via mapper save by piecing it together manually
         * just like we used to do in the previous millennium
         */
        $pieces = [];

        $params = [
            $identifierColumn => $identifierValue,
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
                 * Special case for base user profiles
                 */
                if (self::BASE_PROFILE_PROPERTY === $property) {
                    $params[$property] = $identifierValue;
                } else if ($mapping[$property]['hydrator'] instanceof Date) {
                    /* A date field might be null, esp. when those dates are optional
                     * Prime examples are enrolment dates, leaving dates, employment dates etc.
                     */
                    $params[$property] = $newObject->$getter()?->getDatabase();
                } else {
                    $val = $newObject->$getter();

                    /* Co-erce bools to ints universally: our orm uses bools,
                     * but we persist them as int 1 or int 0 in the db schema.
                     */
                    if (is_bool($val)) {
                        $val = $val ? 1 : 0;
                    }

                    $params[$property] = $val;
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

        $query .= " WHERE {$mapper->getTable()}.{$identifierColumn} = :{$identifierColumn}";

        /* Slap it into prepared statement
         */
        if (!isset($this->existingQueries[$query])) {
            $this->existingQueries[$query] = $mapper->getDatabase()->prepare($query);
        }

        $statement = $this->existingQueries[$query];

        foreach ($params as $key => $value) {
            $statement->bindParam(":{$key}", $value);
        }

        /* Use the id
         */
        $statement->bindParam(":{$identifierColumn}", $identifierValue, \PDO::PARAM_INT);
        $statement->execute($params);

        $this->logDebug(sprintf(
            '** Crafted update occurred for %s.%s %s - subset diff',
            $mapper->getTable(),
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
