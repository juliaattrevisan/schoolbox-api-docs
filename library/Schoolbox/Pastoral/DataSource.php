<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\User;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use PDO;
use function array_merge;
use function count;

/**
 * Data source for pastoral care records.
 *
 * Web request access to pastoral care records must be through this object, not
 * the mapper. This is because pastoral information is sensitive in nature, and
 * therefore it MUST be appropriately filtered and censored before it can be
 * viewed by a user.
 */
class DataSource
{
    /**
     * @var Mapper
     */
    private $mapper;

    /** @var AccessLevelCensor */
    private $accessLevelCensor;

    /** @var Audit\Mapper */
    private $pastoralAuditMapper;


    /**
     * Constructor
     *
     * @param Mapper              $mapper
     * @param AccessLevelCensor   $accessLevelCensor
     * @param PastoralAuditMapper $pastoralAuditMapper
     */
    public function __construct(Mapper $mapper, AccessLevelCensor $accessLevelCensor, PastoralAuditMapper $pastoralAuditMapper)
    {
        $this->mapper              = $mapper;
        $this->accessLevelCensor   = $accessLevelCensor;
        $this->pastoralAuditMapper = $pastoralAuditMapper;
    }


    /**
     * Wrapper for `Mapper::createModel`.
     *
     * Not user-sensitive, but must be exposed to web request code.
     *
     * @param int $id
     *
     * @return Model
     * @throws MapperException
     */
    public function createModel($id = null): Model
    {
        return $this->mapper->createModel($id);
    }


    /**
     * User-sensitive wrapper for `Mapper::findById`.
     *
     * @param User $currentUser
     * @param int  $id
     *
     * @return Model
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws MapperException\InsufficientHydrationException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function findById(User $currentUser, $id): Model
    {
        $model = $this->mapper->findById($id);

        // Add attachments, then filter
        $model->setAttachments(
            $this->mapper->getJoinMapper('attachments')
                         ->findByColumn('pastoral_id', $model->getId())
        );

        $model = $this->accessLevelCensor->filterRecordsForUser(Model::createCollection()->add($model), $currentUser)->first();

        if (!$model instanceof Model) {
            throw new MapperException\NotFound();
        }

        return $model;
    }


    /**
     * Gets number of records for particular student visible to the given viewer
     * @param User     $viewer
     * @param int      $studentId
     * @param int|null $typeId
     * @return int
     */
    public function countRecords(User $viewer, int $studentId, ?int $typeId): int
    {
        $where = $this->getPermissionClause($viewer, $studentId, $typeId);
        $sql   = "
        SELECT count(*)
                FROM pastoral p
        WHERE {$where['where']}
        ";

        $stmt = $this->mapper->getDatabase()->query($sql, $where['params']);

        return (int)$stmt->fetchColumn();
    }


    /**
     * Gets records for a particular student visible to the given viewer
     * @param User     $viewer
     * @param int      $studentId
     * @param int|null $typeId          filter the records with a pastoral type, if not null
     * @param int      $offset
     * @param int      $limit
     * @param int|null $linkedStudentId if not null, returns only records linked with this student
     * @return GenericCollection|Model[]
     */
    public function findRecords(User $viewer, int $studentId, ?int $typeId, int $offset, int $limit, ?int $linkedStudentId): GenericCollection
    {

        // get records for the student
        $studentRecords = $this->findRawRecords($viewer, $studentId, $typeId, $offset, $limit, $linkedStudentId, null);

        if (!$studentRecords->count()) {
            return $studentRecords;
        }

        // create parent -> record map to find siblings
        $parentChildMap = [];
        /** @var Model $record */
        foreach ($studentRecords as $record) {
            // get ids to find linked records
            if ($record->getParent()) {
                $parentChildMap[$record->getParent()->getId()] = $record->getId();
            }
        }

        // get linked records to the student records found
        $linkedRecords = $this->findRawRecords($viewer, null, $typeId, null, null, null, $studentRecords->keys());

        /** @var Model $linkedRecord */
        foreach ($linkedRecords as $linkedRecord) {

            // attach current linked record to a proper student record
            if ($linkedRecord->getParent() && $studentRecords->keyExists($linkedRecord->getParent()->getId())) {
                // parent of the current linked record is a student record
                $targetRecord = $studentRecords->offsetGet($linkedRecord->getParent()->getId());
                $targetRecord->setRelatedRecords($targetRecord->getRelatedRecords()->add($linkedRecord));
                $linkedRecord->setParent($targetRecord);
            } elseif ($linkedRecord->getParent() && array_key_exists($linkedRecord->getParent()->getId(), $parentChildMap)) {
                // parent of the current linked parent is a parent of a student record
                $siblingRecordId = $parentChildMap[$linkedRecord->getParent()->getId()];
                $targetRecord    = $studentRecords->offsetGet($siblingRecordId);
                $targetRecord->setRelatedRecords($targetRecord->getRelatedRecords()->add($linkedRecord));
            } elseif (array_key_exists($linkedRecord->getId(), $parentChildMap)) {
                // the current linked record is the parent of a student record
                $siblingRecordId = $parentChildMap[$linkedRecord->getId()];
                $targetRecord    = $studentRecords->offsetGet($siblingRecordId);
                $targetRecord->setRelatedRecords($targetRecord->getRelatedRecords()->add($linkedRecord));
                $targetRecord->setParent($linkedRecord);
            }
        }

        return $studentRecords;
    }


    /**
     * Gets records without linked records
     * @param User       $viewer
     * @param int|null   $studentId
     * @param int|null   $typeId
     * @param int|null   $offset
     * @param int|null   $limit
     * @param int|null   $linkedStudentId find records only linked to this student, if provided
     * @param array|null $linkedRecordIds find records only linked to these records, if provided
     * @return GenericCollection
     */
    private function findRawRecords(User $viewer, ?int $studentId, ?int $typeId, ?int $offset, ?int $limit, ?int $linkedStudentId, ?array $linkedRecordIds): GenericCollection
    {
        $permissionClause = $this->getPermissionClause($viewer, $studentId, $typeId);
        $where            = $permissionClause['where'];
        $params           = $permissionClause['params'];
        if (null !== $linkedRecordIds || null != $linkedStudentId) {
            $linkedClause = $this->getLinkedClause($viewer, $linkedStudentId, $linkedRecordIds);
            $where        .= " AND {$linkedClause['where']}";
            $params       = array_merge($params, $linkedClause['params']);
        }

        $sql = "
            SELECT
                {$this->mapper->getColumnString('p')},
                {$this->mapper->getJoinColumnString('owner', 'o')},
                {$this->mapper->getJoinColumnString('student', 's')},
                {$this->mapper->getJoinColumnString('modifiedBy', 'm')}
            FROM       {$this->mapper->getTable()} p
            INNER JOIN {$this->mapper->getJoinSql('student', 'p', 's')}
            INNER JOIN {$this->mapper->getJoinSql('owner', 'p', 'o', true)}
            LEFT JOIN  {$this->mapper->getJoinSql('modifiedBy', 'p', 'm', true)}
            WHERE s.deleted_at IS NULL
                AND {$where}
            GROUP BY p.id
            ORDER BY p.updated_at DESC
        ";

        if ($limit) {
            $sql .= " LIMIT {$limit}" . (empty($offset) ? '' : " OFFSET {$offset}");
        }

        $stmt = $this->mapper->getDatabase()->query($sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // hydrate
        $userMapper = $this->mapper->getJoinMapper('owner');
        $records    = Model::createCollection();
        foreach ($rows as $row) {
            try {
                $record = $this->mapper->hydrate($row, 'p');
                /** @var User $owner */
                $owner = $userMapper->hydrate($row, 'o');
                $record->setOwner($owner);
                /** @var User $modifiedBy */
                $modifiedBy = $userMapper->hydrate($row, 'm');
                $record->setModifiedBy($modifiedBy);
                /** @var User $student */
                $student = $userMapper->hydrate($row, 's');
                $record->setStudent($student);
            } catch (MapperException $e) {
                // noop
                continue;
            }
            $record->setRelatedRecords(Model::createCollection());
            $records->offsetSet($record->getId(), $record);
        }
        return $records;
    }

    /**
     * Gets "where" string to find linked records
     * @param User       $viewer
     * @param int|null   $linkedStudentId limit linked records to be only for the student
     * @param array|null $recordIds       limit linked records in this ids
     * @return array
     */
    private function getLinkedClause(User $viewer, ?int $linkedStudentId, ?array $recordIds): array
    {
        $whereClauses = [];
        $params       = [];
        for ($i = 0; $i < 3; $i++) {
            $permissionClause = $this->getPermissionClause($viewer, $linkedStudentId, null, "pl{$i}");
            $whereClauses[$i] = $permissionClause['where'];
            $params[$i]       = $permissionClause['params'];
            if (is_array($recordIds)) {
                // should not happen, but just in case
                if (empty($recordIds)) {
                    $recordIds = [0];
                }
                $limitPb          = (new Builder($this->mapper->getDatabase(), "pl{$i}"))
                    ->setParamPrefix("ln{$i}")
                    ->in('id', $recordIds);
                $whereClauses[$i] .= " AND {$limitPb->getWhere()[0]} ";
                $params[$i]       += $limitPb->getParams();
            }
        }

        $sql = "
                        (
                                    `p`.`parent_id` IN (
                                        SELECT    `id`
                                        FROM      `pastoral` as pl0
                                        WHERE     `deleted_at` IS NULL 
                                          AND     {$whereClauses[0]}
                                    ) OR p.`parent_id` IN (
                                        SELECT    `parent_id`
                                        FROM      `pastoral` as pl1
                                        WHERE     `deleted_at` IS NULL
                                          AND     {$whereClauses[1]}
                                          AND     pl1.student_id <> p.student_id
                                    ) OR p.`id` IN (
                                        SELECT    `parent_id`
                                        FROM      `pastoral` as pl2
                                        WHERE     `deleted_at` IS NULL 
                                          AND     {$whereClauses[2]}
                                    )
                        )
        ";


        return [
            'where'  => $sql,
            'params' => array_merge($params[0], $params[1], $params[2]),
        ];
    }

    /**
     * Generates where clause with parameters to find visible pastoral records
     * @param User     $viewer
     * @param int|null $studentId
     * @param int|null $typeId
     * @param string   $alias
     * @return array
     */
    private function getPermissionClause(User $viewer, ?int $studentId, ?int $typeId, string $alias = 'p'): array
    {
        // Hydrate pastoral group membership, if not yet set
        if ($viewer->getPastoralGroupsMemberships() === null) {
            $viewer->setPastoralGroupsMemberships(
                $this->accessLevelCensor->getPastoralGroupMemberMapper()->findByColumn('user_id', $viewer->getId())
            );
        }
        $pastoralGroupIds = $viewer->getPastoralGroupsMemberships()->getPropertyValues('id');

        /**
         * Pastoral records that we are interested in */
        $validPb = (new Builder($this->mapper->getDatabase(), $alias))->setParamPrefix("{$alias}v");
        if (null !== $studentId) {
            $validPb->equals("{$alias}.student_id", $studentId);
        }
        $validPb->isNull("{$alias}.deleted_at");
        if (null !== $typeId) {
            $validPb->equals("{$alias}.type_id", $typeId);
        }
        $where  = implode(' AND ', $validPb->getWhere());
        $params = $validPb->getParams();

        /**
         * Check pastoral access, if the viewer is not a moderator */
        if (!$this->accessLevelCensor->isPastoralModerator($viewer)) {
            /**
             * Owner */
            $ownerPb = (new Builder($this->mapper->getDatabase(), $alias))
                ->setParamPrefix("{$alias}o")
                ->equals("{$alias}.owner_id", $viewer->getId());

            /**
             * Allowed by pastoral access */
            $paInnerPb1 = (new Builder($this->mapper->getDatabase(), "{$alias}a1"))
                ->setParamPrefix("{$alias}a1")
                ->equals('user_id', $viewer->getId())
                ->equals('role_id', $viewer->getRole()->getId());
            if (!empty($pastoralGroupIds)) {
                $paInnerPb1->in('group_id', $pastoralGroupIds);
            }
            $hasAccessPb    = (new Builder($this->mapper->getDatabase(), "{$alias}a1"))
                ->setParamPrefix("{$alias}a1")
                ->any($paInnerPb1)
                ->isNull('deleted_at')
                ->greaterThan('access', 0);
            $whereHasAccess = implode(' AND ', $hasAccessPb->getWhere());

            /**
             * Rejected by pastoral access */
            $paInnerPb2 = (new Builder($this->mapper->getDatabase(), "{$alias}a2"))
                ->setParamPrefix("{$alias}a2")
                ->equals('user_id', $viewer->getId())
                ->equals('role_id', $viewer->getRole()->getId());
            if (!empty($pastoralGroupIds)) {
                $paInnerPb2->in('group_id', $pastoralGroupIds);
            }
            $noAccessPb    = (new Builder($this->mapper->getDatabase(), "{$alias}a2"))
                ->setParamPrefix("{$alias}a2")
                ->any($paInnerPb2)
                ->isNull('deleted_at')
                ->equals('access', 0);
            $whereNoAccess = implode(' AND ', $noAccessPb->getWhere());

            /**
             * Combine where */
            $where .= "
                AND
                (
                    (
                      {$alias}.id IN (SELECT pastoral_id FROM pastoral_access {$alias}a1 WHERE {$whereHasAccess})
                      AND
                      {$alias}.id NOT IN (SELECT pastoral_id FROM pastoral_access {$alias}a2 WHERE {$whereNoAccess})
                    ) OR (
                      {$ownerPb->getWhere()[0]}
                    )
                )
            ";

            $params = array_merge(
                $ownerPb->getParams(),
                $paInnerPb1->getParams(),
                $hasAccessPb->getParams(),
                $paInnerPb2->getParams(),
                $noAccessPb->getParams(),
                $params
            );
        }

        return [
            'where'  => $where,
            'params' => $params,
        ];
    }

    /**
     * User-sensitive wrapper for `Mapper::findByTag`.
     *
     * @param User        $currentUser
     * @param PastoralTag $tag
     *
     * @return Collection|Model[]
     * @throws MapperException\InsufficientHydrationException
     */
    public function findByTag(User $currentUser, PastoralTag $tag): Collection
    {
        return $this->accessLevelCensor->filterRecordsForUser($this->mapper->findByTag($tag), $currentUser);
    }

    /** @noinspection MoreThanThreeArgumentsInspection */
    /**
     * User-sensitive wrapper for `Mapper::getReportData`.
     *
     * @param User                  $currentUser
     * @param Alaress_Date_Format   $before_date
     * @param Alaress_Date_Format   $after_date
     * @param null|PastoralSeverity $severity
     * @param Collection            $types
     * @param Collection            $subtypes
     * @param Collection            $authors
     * @param Collection            $students
     * @param Collection            $tags
     * @param Collection            $order
     * @param                       $involved
     * @param Collection            $years
     * @param Collection            $houses
     *
     * @return Collection
     * @throws MapperException\InsufficientHydrationException
     */
    public function getReportData(
        User $currentUser,
        Alaress_Date_Format $before_date,
        Alaress_Date_Format $after_date,
        ?PastoralSeverity $severity,
        Collection $types,
        Collection $subtypes,
        Collection $authors,
        Collection $students,
        Collection $tags,
        Collection $order,
        $involved,
        Collection $years,
        Collection $houses
    ): Collection {
        // The mapper method is user-sensitive,
        // but filter anyway as it's better to be explicit than implicit.
        return $this->accessLevelCensor->filterRecordsForUser(
            $this->mapper->getReportData(
                $currentUser,
                $before_date,
                $after_date,
                $severity,
                $types,
                $subtypes,
                $authors,
                $students,
                $tags,
                $order,
                $involved,
                $years,
                $houses
            ),
            $currentUser
        );
    }


    /**
     * @param User $viewer
     * @param int  $studentId
     * @return array
     */
    public function getLinkedStudentRecordCount(User $viewer, int $studentId): array
    {
        $permissionClause = $this->getPermissionClause($viewer, null, null, 'p');
        $linkedClause     = $this->getLinkedClause($viewer, $studentId, null);

        $sql = "
                SELECT  p.student_id,
                        COUNT(*)            AS `count`,
                        `user`.`lastname`   AS `last_name`,
                        `user`.`firstname`  AS `first_name`
                FROM    pastoral p JOIN `user` ON p.student_id = `user`.id AND `user`.deleted_at IS NULL
                WHERE   {$permissionClause['where']}
                        AND
                        {$linkedClause['where']}
                GROUP BY p.student_id
        ";

        $stmt = $this->mapper->getDatabase()->query($sql, array_merge($permissionClause['params'], $linkedClause['params']));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * User-sensitive wrapper for `Mapper::findLinked`.
     *
     * @param User $currentUser
     * @param User $student
     * @param User $linkedStudent
     * @param int  $typeId
     *
     * @return Collection|Model[]
     * @throws MapperException\InsufficientHydrationException
     */
    public function findLinked(User $currentUser, User $student, User $linkedStudent, $typeId = null): Collection
    {
        return $this->accessLevelCensor->filterRecordsForUser(
            $this->mapper->findLinked($student, $linkedStudent, $typeId),
            $currentUser
        );
    }


    /**
     * User-sensitive wrapper for `Mapper::getSearchResults`.
     *
     * @param User   $currentUser
     * @param string $search
     *
     * @return Collection
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function getSearchResults(User $currentUser, $search): Collection
    {
        $filteredRecords = $this->accessLevelCensor->filterRecordsForUser(
            $this->mapper->getSearchResults($search, $currentUser),
            $currentUser
        );

        // --------------------------------------------------------------------------------------------------------
        // Keep a record of all searches performed by users, and what results were displayed
        // --------------------------------------------------------------------------------------------------------

        $searchResults = $filteredRecords->indexBy('id')->keys();

        // Record the search results that were returned by the search by imploding their IDs down into CSV
        if (count($searchResults)) {
            $auditRecord = $this->pastoralAuditMapper->createModel()
                                                     ->setUser($currentUser)
                                                     ->setSearch($search)
                                                     ->setSearchResult(implode(',', $searchResults));
            $this->pastoralAuditMapper->save($auditRecord);
        }
        return $filteredRecords;
    }


    /**
     * User-sensitive wrapper for `Mapper::findRecentDashboard`.
     *
     * @param User $currentUser
     * @param int  $count
     *
     * @return Collection|Model[]
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     */
    public function findRecentDashboard(User $currentUser, $count = 5): Collection
    {
        // The mapper method is user-sensitive,
        // but filter anyway as it's better to be explicit than implicit.

        return $this->accessLevelCensor->filterRecordsForUser($this->mapper->findRecentDashboard($count), $currentUser)
                                       ->sortBy('updatedAtDisplay', 'desc');
    }


    /**
     * Wrapper for `Mapper::save`.
     *
     * Not user-sensitive, but must be exposed to web request code.
     *
     * @param Model $model
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function save(Model $model): void
    {
        $this->mapper->save($model);
    }


    /**
     * Wrapper for `Mapper::delete`.
     *
     * Not user-sensitive, but must be exposed to web request code.
     *
     * @param Model $model
     *
     * @return bool
     * @throws MapperException
     */
    public function delete(Model $model): bool
    {
        return $this->mapper->delete($model);
    }
}
