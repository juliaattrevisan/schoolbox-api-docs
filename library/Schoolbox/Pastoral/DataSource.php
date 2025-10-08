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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
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
     * Wrapper for `Mapper::createCollection`.
     *
     * @param array $contents
     * @return GenericCollection<Model>
     */
    public function createCollection(array $contents = []): GenericCollection
    {
        return $this->mapper->createCollection($contents);
    }


    /**
     * Wrapper for `Mapper::getPredicateBuilder`.
     *
     * @return Builder
     */
    public function getPredicateBuilder(): Builder
    {
        return $this->mapper->getPredicateBuilder();
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
     * User-sensitive wrapper for `Mapper::countByColumnList`.
     *
     * @param User $viewer
     * @param User $student
     * @param array $columnList
     * @return int
     */
    public function countByColumnList(User $viewer, User $student, array $columnList): int
    {
        $where = $this->getPermissionClause($viewer, $student, null, null);
        $pb    = $this->mapper->getPredicateBuilder();
        foreach ($columnList as $column => $value) {
            $pb = is_iterable($value)
                ? $pb->in('p.' . $column, $value)
                : $pb->equals('p.' . $column, $value);
        }

        $pbWhere = $pb->getWhereIntersect();
        if ($pbWhere !== '') {
            $where['where'] .= ' AND ' . $pbWhere;
        }
        $where['params'] = array_merge($where['params'], $pb->getParams());
        return $this->countRecordsFromWhere($where['where'], $where['params']);
    }


    /**
     * Finds a count of records for a particular student visible to the given viewer
     * @param User $viewer Who is viewing this student's records
     * @param User $student Student being viewed
     * @param int|null $typeId Filter the records with a pastoral type, if provided
     * @param int|null $subtypeId Filter the records with a pastoral subtype, if provided
     * @return int
     */
    public function countRecords(User $viewer, User $student, ?int $typeId, ?int $subtypeId, ?bool $showArchived): int
    {
        $where = $this->getPermissionClause($viewer, $student, $typeId, $subtypeId);
        return $this->countRecordsFromWhere($where['where'], $where['params'], $showArchived);
    }

    /**
     * Helper function for counting matching records.
     *
     * @param string $where
     * @param array  $params
     * @return int
     */
    private function countRecordsFromWhere(string $where, array $params, ?bool $showArchived = true): int
    {
        $sql  = "SELECT count(*) FROM pastoral p WHERE {$where}";

        if (!$showArchived) {
            $sql .= " AND (p.expires_at >= CURDATE() OR p.expires_at IS NULL)";
        }
        $stmt = $this->mapper->getDatabase()->query($sql, $params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * count and details of pastoral events of a single student that a specified `viewer` is allowed to see
     *
     * @param User $viewer
     * @param User $student
     * @param array $typeIds
     * @param array $subtypeIds
     * @return array
     */
    public function countRecordsByType(User $viewer, User $student, array $typeIds, array $subtypeIds): array
    {
        if (empty($typeIds) && empty($subtypeIds)) {
            return [];
        }

        $typeWheres     = [];
        $typeParams     = [];
        $archiveWhere = " AND (expires_at >= CURDATE() OR expires_at IS NULL) ";

        if (!empty($typeIds)) {
            $typeBindings = Builder::getBindings($typeIds, 'typeId');
            $typeWheres[] = "p.type_id IN ({$typeBindings->getPlaceholder()})";
            $typeParams   = $typeBindings->getParams();
        }
        if (!empty($subtypeIds)) {
            $subtypeBindings = Builder::getBindings($subtypeIds, 'subtypeId');
            $typeWheres[]    = "p.subtype_id IN ({$subtypeBindings->getPlaceholder()})";
            $typeParams      = array_merge($subtypeBindings->getParams(), $typeParams);
        }


        $where     = $this->getPermissionClause($viewer, $student, null, null);
        $typeWhere = implode(' OR ', $typeWheres);

        $sql    = "
        SELECT count(*) as count, p.type_id as typeId, p.subtype_id as subtypeId
                FROM pastoral p
        WHERE ({$where['where']})
        AND ({$typeWhere})
        {$archiveWhere}
        GROUP BY p.type_id, p.subtype_id
        ";

        $params = array_merge(
            $typeParams,
            $where['params']
        );

        $stmt = $this->mapper->getDatabase()->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * count pastoral records and group the results by month
     *
     * @param User $viewer
     * @param User $student
     * @param Alaress_Date_Format|null $startDate
     * @return array
     */
    public function countRecordsByTypeGroupByMonth(User $viewer, User $student, ?Alaress_Date_Format $startDate): array
    {
        $where          = $this->getPermissionClause($viewer, $student, null, null);
        $startDateWhere = $startDate ? ' AND COALESCE(`p`.`occurred_on`, `p`.`created_at`) >= :startDate ' : '';

        $sql = "
        SELECT count(*) as count, p.type_id as typeId, p.subtype_id as subtypeId, month(COALESCE(`p`.`occurred_on`, `p`.`created_at`)) as partition_month, year(COALESCE(`p`.`occurred_on`, `p`.`created_at`)) as partition_year
                FROM pastoral p
        WHERE ({$where['where']})
        {$startDateWhere}
        GROUP BY partition_year, partition_month, p.type_id, p.subtype_id
        ";

        $params = $startDate
            ? array_merge($where['params'], ['startDate' => $startDate->getDatabaseDate()])
            : $where['params'];

        $stmt = $this->mapper->getDatabase()->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gets records for a particular student visible to the given viewer
     * @param User $viewer Who is viewing this student's records
     * @param User $student Student being viewed
     * @param int|null $typeId Filter the records with a pastoral type, if not null
     * @param int|null $subtypeId
     * @param int $offset
     * @param int $limit
     * @param User|null $linkedStudent Returns the records linked to this student only, if provided
     * @return GenericCollection
     */
    public function findRecords(User $viewer, User $student, ?int $typeId, ?int $subtypeId, int $offset, int $limit, ?User $linkedStudent, ?bool $showArchived = true): GenericCollection
    {
        return $this->findRawRecords($viewer, $student, $typeId, $subtypeId, $offset, $limit, $linkedStudent, null, $showArchived);
    }


    /**
     * Find pastoral records which match the given predicate builder, and are
     * viewable by `$viewer`.
     *
     * @param User    $viewer
     * @param Builder $pb
     * @param array   $options
     * @return GenericCollection<Model>
     */
    public function findRecordsByPredicates(
        User $viewer,
        User $student,
        Builder $pb,
        array $options = [],
    ): GenericCollection {
        $permissionClause = $this->getPermissionClause($viewer, $student, null, null);

        $records = $this->findRawRecordsByPredicates(
            $pb->rawWhere($permissionClause['where'], $permissionClause['params']),
            $options['order'] ?? [],
            $options['offset'] ?? null,
            $options['limit'] ?? null,
        );

        // Each pastoral record we collected belongs to the student passed to this function.
        foreach ($records as $record) {
            $record->setStudent($student);
        }

        return $records;
    }


    /**
     * Gets records with least hydration. This will not populate any linked records to the returning records.
     * @param User $viewer Who is viewing this student's records
     * @param User|null $student
     * @param int|null $typeId Filter the records with a pastoral type, if not null
     * @param int|null $subtypeId
     * @param int|null $offset
     * @param int|null $limit
     * @param User|null $linkedStudent Find records linked to this student only, if provided
     * @param array|null $linkedRecordIds Find records linked to these records only, if provided
     * @return GenericCollection<Model>
     */
    private function findRawRecords(User $viewer, ?User $student, ?int $typeId, ?int $subtypeId, ?int $offset, ?int $limit, ?User $linkedStudent, ?array $linkedRecordIds, ?bool $showArchived = true): GenericCollection
    {
        $permissionClause = $this->getPermissionClause($viewer, $student, $typeId, $subtypeId);
        $pb = $this->getPredicateBuilder()->rawWhere($permissionClause['where'], $permissionClause['params']);
        if (null !== $linkedRecordIds || null !== $linkedStudent) {
            $linkedClause = $this->getLinkedClause($viewer, $linkedStudent, $linkedRecordIds);
            $pb = $pb->rawWhere($linkedClause['where'], $linkedClause['params']);
        }

        if (!$showArchived) {
            $pb->any(
                $this->getPredicateBuilder()
                     ->any($this->getPredicateBuilder()->greaterThanOrEqualTo('p.expires_at', \Alaress_Date_Format::now()->getDatabaseDate()))
                     ->any($this->getPredicateBuilder()->isNull('p.expires_at'))
            );
        }

        $records = $this->findRawRecordsByPredicates($pb, [], $offset, $limit);

        // Each pastoral record we collected belongs to the student passed to this function.
        foreach ($records as $record) {
            $record->setStudent($student);
        }

        return $records;
    }


    /**
     * Fetches records from a predicate with minimal hydration.
     *
     * @param Builder  $pb
     * @param array    $order
     * @param int|null $offset
     * @param int|null $limit
     * @return GenericCollection<Model>
     */
    private function findRawRecordsByPredicates(
        Builder $pb,
        array $order,
        ?int $offset,
        ?int $limit,
    ): GenericCollection {
        $where = $pb->getWhereIntersect();

        $severityEnabled = $this->mapper->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED);

        $sql   = "
            SELECT
                {$this->mapper->getColumnString('p')},
                {$this->mapper->getJoinColumnString('owner', 'o')},
                {$this->mapper->getJoinColumnString('modifiedBy', 'm')},
                {$this->mapper->getJoinColumnString('severity', 'sev')},
                {$this->mapper->getJoinColumnString('type', 't')},
                {$this->mapper->getJoinColumnString('subtype', 'c')}
            FROM       {$this->mapper->getTable()} p
            INNER JOIN {$this->mapper->getJoinSql('student', 'p', 's')}
            INNER JOIN {$this->mapper->getJoinSql('owner', 'p', 'o', true)}
            LEFT JOIN  {$this->mapper->getJoinSql('modifiedBy', 'p', 'm', true)}
            LEFT JOIN  {$this->mapper->getJoinSql('severity', 'p', 'sev', true)}
            LEFT JOIN  {$this->mapper->getJoinSql('type', 'p', 't', true)}
            LEFT JOIN  {$this->mapper->getJoinSql('subtype', 'p', 'c', true)}
            WHERE s.deleted_at IS NULL
                AND {$where}
            GROUP BY p.id
        ";

        $orderParts = [];
        if (count($order) > 0) {
            foreach ($order as $column => $direction) {
                $orderParts[] = "$column $direction";
            }
        } else {
            $direction = \Schoolbox\Mapper::SORT_ORDER_DESC;
            $orderParts = [
                "p.updated_at $direction",
                "p.id $direction",
            ];
        }
        $sql .= ' ORDER BY ' . implode(', ', $orderParts);

        if ($limit) {
            $sql .= " LIMIT {$limit}" . (empty($offset) ? '' : " OFFSET {$offset}");
        }

        $stmt = $this->mapper->getDatabase()->query($sql, $pb->getParams());
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // hydrate
        $userMapper = $this->mapper->getJoinMapper('owner');
        $severityMapper = $this->mapper->getJoinMapper('severity');
        /** @var \Schoolbox\Pastoral\Type\Mapper $typeMapper */
        $typeMapper = $this->mapper->getJoinMapper('type');
        /** @var \Schoolbox\Pastoral\SubType\Mapper $subtypeMapper */
        $subtypeMapper = $this->mapper->getJoinMapper('subtype');

        $records = $this->mapper->createCollection();
        foreach ($rows as $row) {
            try {
                $record = $this->mapper->hydrate($row, 'p');
                /** @var User $owner */
                $owner = $userMapper->hydrate($row, 'o');
                $record->setOwner($owner);
                /** @var User $modifiedBy */
                $modifiedBy = $userMapper->hydrate($row, 'm');
                $record->setModifiedBy($modifiedBy);
                if ($severityEnabled) {
                    /** @var PastoralSeverity $severity */
                    $severity = $severityMapper->hydrate($row, 'sev');
                    $record->setSeverity($severity);
                }
                $type = $typeMapper->hydrate($row, 't');
                $record->setType($type);
                $subtype = $subtypeMapper->hydrate($row, 'c');
                $record->setSubType($subtype);
            } catch (MapperException $e) {
                // noop
                continue;
            }
            $record->setRelatedRecords($this->mapper->createCollection());
            $records->offsetSet($record->getId(), $record);
        }

        // Load record access
        $recordIds        = $records->getPropertyValues('id');
        $recordAccessById = $this->mapper->getJoinMapper('access')
            ->findByColumn('pastoral_id', $recordIds)
            ->groupByCallback(fn (PastoralAccess $v) => $v->getPastoral()->getId());
        foreach ($records as $record) {
            if (isset($recordAccessById[$record->getId()])) {
                $record->setAccess($recordAccessById[$record->getId()]);
            }
        }

        return $records;
    }


    /**
     * Gets "where" string to find linked records
     * @param User       $viewer
     * @param User|null   $linkedStudent Find records linked to this student only, if provided
     * @param array|null $recordIds       Find records linked to these records only, if provided
     * @return array
     */
    private function getLinkedClause(User $viewer, ?User $linkedStudent, ?array $recordIds): array
    {
        $whereClauses = [];
        $params       = [];
        for ($i = 0; $i < 3; $i++) {
            $permissionClause = $this->getPermissionClause($viewer, $linkedStudent, null, null, "pl{$i}");
            $whereClauses[$i] = $permissionClause['where'];
            $params[$i]       = $permissionClause['params'];
            if (is_array($recordIds)) {
                // should not happen, but just in case
                if (empty($recordIds)) {
                    $recordIds = [0];
                }
                $limitPb          = (new Builder($this->mapper->getDatabase()->getQuoter(), "pl{$i}"))
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
     * @param User $viewer
     * @param User|null $student
     * @param int|null $typeId
     * @param int|null $subtypeId
     * @param string $alias
     * @return array
     */
    private function getPermissionClause(User $viewer, ?User $student, ?int $typeId, ?int $subtypeId, string $alias = 'p'): array
    {
        // Hydrate pastoral group membership, if not yet set
        if ($viewer->getPastoralGroupIds() === null) {
            $viewer->setPastoralGroupIds(
                $this->accessLevelCensor->getPastoralGroupMemberMapper()->findGroupIdsForUser($viewer)
            );
        }

        /**
         * Pastoral records that we are interested in */
        $validPb = (new Builder($this->mapper->getDatabase()->getQuoter(), $alias))->setParamPrefix("{$alias}v");
        $inSameCampus = true;
        if (null !== $student) {
            $validPb->equals("{$alias}.student_id", $student->getId());
            $inSameCampus = $viewer->getRole()->isStudent() || $viewer->getRole()->isParent() || AccessLevelCensor::inSameCampus($student, $viewer);
        }
        $validPb->isNull("{$alias}.deleted_at");
        if (null !== $typeId) {
            $validPb->equals("{$alias}.type_id", $typeId);
        }
        if (null !== $subtypeId) {
            $validPb->equals("{$alias}.subtype_id", $subtypeId);
        }
        $where  = implode(' AND ', $validPb->getWhere());
        $params = $validPb->getParams();

        /**
         * Check pastoral access, if the viewer is not a moderator */
        if (!$this->accessLevelCensor->isPastoralModerator($viewer)) {
            /**
             * Owner */
            $ownerPb = (new Builder($this->mapper->getDatabase()->getQuoter(), $alias))
                ->setParamPrefix("{$alias}o")
                ->equals("{$alias}.owner_id", $viewer->getId());

            /**
             * Allowed by pastoral access */
            $paInnerPb1 = (new Builder($this->mapper->getDatabase()->getQuoter(), "{$alias}a1"))
                ->setParamPrefix("{$alias}a1")
                ->equals('user_id', $viewer->getId())
                ->equals('role_id', $viewer->getRole()->getId())
                ->equals('role_type', $viewer->getRole()->getType());
            if (!empty($viewer->getPastoralGroupIds())) {
                $paInnerPb1->in('group_id', $viewer->getPastoralGroupIds());
            }
            $hasAccessPb    = (new Builder($this->mapper->getDatabase()->getQuoter(), "{$alias}a1"))
                ->setParamPrefix("{$alias}a1")
                ->any($paInnerPb1)
                ->isNull('deleted_at')
                ->greaterThan('access', 0);
            if (!$inSameCampus) {
                // Returns only permissions not limiting campus
                $hasAccessPb->isFalse('match_campus');
            }
            $whereHasAccess = implode(' AND ', $hasAccessPb->getWhere());

            /**
             * Rejected by pastoral access */
            $paInnerPb2 = (new Builder($this->mapper->getDatabase()->getQuoter(), "{$alias}a2"))
                ->setParamPrefix("{$alias}a2")
                ->equals('user_id', $viewer->getId())
                ->equals('role_id', $viewer->getRole()->getId())
                ->equals('role_type', $viewer->getRole()->getType());
            if (!empty($viewer->getPastoralGroupIds())) {
                $paInnerPb2->in('group_id', $viewer->getPastoralGroupIds());
            }
            $noAccessPb    = (new Builder($this->mapper->getDatabase()->getQuoter(), "{$alias}a2"))
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
     * @param User                     $currentUser
     * @param Alaress_Date_Format|null $before_date
     * @param Alaress_Date_Format|null $after_date
     * @param PastoralSeverity|null    $severity
     * @param Collection               $types
     * @param Collection               $subtypes
     * @param Collection               $authors
     * @param Collection               $students
     * @param Collection               $tags
     * @param Collection               $order
     * @param bool                     $involved
     * @param Collection               $years
     * @param Collection               $houses
     * @param Collection               $campuses
     * @param Collection               $actions
     * @param bool                     $includeStudentsWithoutYear
     * @param bool                     $includeStudentsWithoutHouse
     * @param bool                     $includeReportsWithoutAction
     * @param ?string                  $actionStatus
     *
     * @return Collection
     * @throws MapperException\InsufficientHydrationException
     */
    public function getReportData(
        User $currentUser,
        ?Alaress_Date_Format $before_date,
        ?Alaress_Date_Format $after_date,
        ?PastoralSeverity $severity,
        Collection $types,
        Collection $subtypes,
        Collection $authors,
        Collection $students,
        Collection $tags,
        Collection $order,
        bool $involved,
        Collection $years,
        Collection $houses,
        Collection $campuses,
        Collection $actions,
        bool $includeStudentsWithoutYear,
        bool $includeStudentsWithoutHouse,
        bool $includeReportsWithoutAction,
        bool $includeArchivedRecords,
        ?string $actionStatus
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
                $houses,
                $campuses,
                $actions,
                $includeStudentsWithoutYear,
                $includeStudentsWithoutHouse,
                $includeReportsWithoutAction,
                $includeArchivedRecords,
                $actionStatus
            ),
            $currentUser
        );
    }


    /**
     * Finds a count of linked records grouped by linked student names for a particular student visible to the given viewer
     * @param User     $viewer    Who is viewing this student's records
     * @param User     $student   Student being viewed
     * @param int|null $typeId
     * @param int|null $subtypeId
     * @return array
     */
    public function getLinkedStudentRecordCount(User $viewer, User $student, ?int $typeId, ?int $subtypeId): array
    {
        $permissionClause = $this->getPermissionClause($viewer, null, $typeId, $subtypeId, 'p');
        $linkedClause     = $this->getLinkedClause($viewer, $student, null);

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

        /* Remember everyone's search terms, but not the records that matched
         */
        if ($filteredRecords->count()) {
            $auditRecord = $this->pastoralAuditMapper->createModel()
                                                     ->setUser($currentUser)
                                                     ->setSearch(mb_substr($search, 0, 255));
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
        $this->mapper->suppressNotification();
        $this->mapper->save($model);
        $this->mapper->restoreNotification();
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

    /**
     * Unsets pastoral mapper's event propagator
     */
    public function resetPropagator(): void
    {
        $this->mapper->setEventPropagator();
    }

    /**
     * Match signature of Schoolbox\Mapper and emulate an Event notification
     *
     * @param Model      $object
     * @param string     $verb
     * @param Model|null $previous
     * @return bool
     */
    public function raiseNotification($object, $verb, $previous = null): bool
    {
        $sessionUser = $this->mapper->getSessionUser();
        if (!$sessionUser) {
            return false;
        }

        try {
            $diff = $previous && ($verb != \Schoolbox\Mapper::METHOD_DELETE) ? $this->mapper->diff($object, $previous) : null;
        } catch (MapperException $e) {
            return false;
        }

        if ($previous) {
            /** @var \Schoolbox\Pastoral\Attachment\Mapper $pastoralAttachmentMapper */
            $pastoralAttachmentMapper = $this->mapper->getJoinMapper('attachments');
            [
                $created,
                $deleted,
            ] = $pastoralAttachmentMapper->diffAttachments($object->getAttachments(), $previous->getAttachments());
            /** @var \Schoolbox\Pastoral\Attachment\Model $attachment */
            foreach ($created->merge($deleted) as $attachment) {
                if ($attachment->isPublic() && !isset($diff['attachmentsPublic'])) {
                    $diff['attachmentsPublic'] = [1, 0]; // value does not matter for now
                } elseif (!$attachment->isPublic() && !isset($diff['attachmentsPrivate'])) {
                    $diff['attachmentsPrivate'] = [1, 0]; // value does not matter for now
                }
            }
        }

        $this->mapper->getEventPropagator()->propagate($sessionUser, $verb, $object, $this->mapper, $diff);
        return true;
    }
}
