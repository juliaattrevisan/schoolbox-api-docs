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
     * @param User     $student
     * @param int|null $typeId
     * @param array    $pastoralGroupIds
     * @return int
     */
    public function countRecords(User $viewer, User $student, ?int $typeId, array $pastoralGroupIds): int
    {
        $where = $this->getWhere($viewer, $student->getId(), $typeId, $pastoralGroupIds, null);
        $sql   = "
        SELECT count(*)
                FROM pastoral p
        WHERE {$where['sql']}
        ";

        $stmt = $this->mapper->getDatabase()->query($sql, $where['params']);

        return (int)$stmt->fetchColumn();
    }


    /**
     * Gets records for particular student visible to the given viewer
     * @param User     $viewer
     * @param User     $student
     * @param int|null $typeId
     * @param array    $pastoralGroupIds
     * @param int      $offset
     * @param int      $limit
     * @return GenericCollection|Model[]
     */
    public function findRecords(User $viewer, User $student, ?int $typeId, array $pastoralGroupIds, int $offset, int $limit): GenericCollection
    {
        $rawRecords = $this->findRawRecords($viewer, $typeId, $pastoralGroupIds, null, $student->getId(), $offset, $limit);
        $userMapper = $this->mapper->getJoinMapper('owner');

        // hydrate the parent records first
        $parentRecords = Model::createCollection();
        foreach ($rawRecords as $rawRecord) {
            try {
                $record = $this->mapper->hydrate($rawRecord);
                /** @var User $owner */
                $owner = $userMapper->hydrate($rawRecord, 'o');
                $record->setOwner($owner);
                /** @var User $modifiedBy */
                $modifiedBy = $userMapper->hydrate($rawRecord, 'u');
                $record->setModifiedBy($modifiedBy);
            } catch (MapperException $e) {
                // noop
                continue;
            }

            // keep key as record id
            $parentRecords->offsetSet($record->getId(), $record);
        }

        // hydrate related records
        $parentIds        = $parentRecords->getPropertyValues('id');
        $linkedRecordRows = $this->findRawRecords($viewer, $typeId, $pastoralGroupIds, $parentIds, null, null, null);

        foreach ($linkedRecordRows as $linkedRecordRow) {
            if (!$this->accessLevelCensor->isPastoralModerator($viewer)
                && (int)$linkedRecordRow['owner_id'] !== $viewer->getId()
                && empty($linkedRecordRow['pastoral_access'])) {
                continue;
            }
            try {
                $linkedRecord = $this->mapper->hydrate($linkedRecordRow);
                /** @var User $owner */
                $owner = $userMapper->hydrate($linkedRecordRow, 'o');
                $linkedRecord->setOwner($owner);
                /** @var User $student */
                $student = $userMapper->hydrate($linkedRecordRow, 'u');
                $linkedRecord->setStudent($student);
            } catch (MapperException $e) {
                // noop
                continue;
            }

            /** @var Model $parentRecord */
            $parentRecord = $parentRecords->offsetGet($linkedRecordRow['parent_id']);
            $parentRecord->setRelatedRecords($parentRecord->getRelatedRecords()->add($linkedRecord));
            $linkedRecord->setParent($parentRecord);
        }
        return $parentRecords;
    }


    /**
     * Gets raw result records
     * @param User       $viewer
     * @param int|null   $typeId
     * @param array      $pastoralGroupIds
     * @param array|null $parentRecordIds
     * @param int|null   $studentId
     * @param int|null   $offset
     * @param int|null   $limit
     * @return array
     */
    private function findRawRecords(User $viewer, ?int $typeId, array $pastoralGroupIds, ?array $parentRecordIds, ?int $studentId, ?int $offset, ?int $limit): array
    {
        $where = $this->getWhere($viewer, $studentId, $typeId, $pastoralGroupIds, $parentRecordIds);

        if (!empty($parentRecordIds)) {
            $userColumn = 'p.student_id';
        } else {
            if (null !== $studentId) {
                $userColumn = 'p.modified_by_user_id';
            } else {
                return [];
            }
        }

        $sql = "
        SELECT p.id,
               p.owner_id,
               p.parent_id,
               p.heading,
               p.modified_by_user_id,
               p.student_id,
               p.created_at,
               p.updated_at,
               o.id AS `o.id`,
               o.firstname AS `o.firstname`,
               o.lastname AS `o.lastname`,
               o.deleted_at AS `o.deleted_at`,
               u.id AS `u.id`,
               u.firstname AS `u.firstname`,
               u.lastname AS `u.lastname`,
               u.deleted_at AS `u.deleted_at`
        FROM pastoral p
        INNER JOIN `user` o ON p.owner_id = o.id
        INNER JOIN `user` u ON {$userColumn} = u.id
        WHERE {$where['sql']}
        GROUP BY p.id
        ORDER BY p.updated_at DESC
        ";

        if ($limit) {
            $sql .= " LIMIT {$limit}" . (empty($offset) ? '' : " OFFSET {$offset}");
        }

        $stmt = $this->mapper->getDatabase()->query($sql, $where['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generates where clause with parameters to find visible pastoral records
     * @param User       $viewer
     * @param int|null   $studentId
     * @param int|null   $typeId
     * @param array      $pastoralGroupIds
     * @param array|null $parentRecordIds
     * @return array
     */
    private function getWhere(User $viewer, ?int $studentId, ?int $typeId, array $pastoralGroupIds, ?array $parentRecordIds): array
    {
        // Pastoral records that we are interested in
        $validPb = (new Builder($this->mapper->getDatabase(), 'p'))->setParamPrefix('pv');

        // This query expects either parent record ids or student id is set.
        if (!empty($parentRecordIds)) {
            $validPb->in('p.parent_id', $parentRecordIds);
        } else {
            if (null !== $studentId) {
                $validPb->equals('p.student_id', $studentId);
            } else {
                return [];
            }
        }
        $validPb->isNull('p.deleted_at');
        if (null !== $typeId) {
            $validPb->equals('p.type_id', $typeId);
        }
        $where  = implode(' AND ', $validPb->getWhere());
        $params = $validPb->getParams();

        // Check access for the current user
        if (!$this->accessLevelCensor->isPastoralModerator($viewer)) {
            // owner
            $ownerPb = (new Builder($this->mapper->getDatabase(), 'p'))
                ->setParamPrefix('po')
                ->equals('p.owner_id', $viewer->getId());

            // allowed by pastoral_access
            $paInnerPb1 = (new Builder($this->mapper->getDatabase(), 'pa1'))
                ->setParamPrefix('pa1')
                ->equals('user_id', $viewer->getId())
                ->equals('role_id', $viewer->getRole()->getId());
            if (!empty($pastoralGroupIds)) {
                $paInnerPb1->in('group_id', $pastoralGroupIds);
            }
            $hasAccessPb    = (new Builder($this->mapper->getDatabase(), 'pa1'))
                ->setParamPrefix('pa1')
                ->any($paInnerPb1)
                ->isNull('deleted_at')
                ->greaterThan('access', 0);
            $whereHasAccess = implode(' AND ', $hasAccessPb->getWhere());

            // rejected by pastoral_access
            $paInnerPb2 = (new Builder($this->mapper->getDatabase(), 'pa2'))
                ->setParamPrefix('pa2')
                ->equals('user_id', $viewer->getId())
                ->equals('role_id', $viewer->getRole()->getId());
            if (!empty($pastoralGroupIds)) {
                $paInnerPb2->in('group_id', $pastoralGroupIds);
            }
            $noAccessPb    = (new Builder($this->mapper->getDatabase(), 'pa2'))
                ->setParamPrefix('pa2')
                ->any($paInnerPb2)
                ->isNull('deleted_at')
                ->equals('access', 0);
            $whereNoAccess = implode(' AND ', $noAccessPb->getWhere());

            // Generate where string using the results
            $where  .= "
                AND
                (
                    (
                      p.id IN (SELECT pastoral_id FROM pastoral_access pa1 WHERE {$whereHasAccess})
                      AND
                      p.id NOT IN (SELECT pastoral_id FROM pastoral_access pa2 WHERE {$whereNoAccess})
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
            'sql'    => $where,
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
    ): Collection
    {
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
     * Wrapper for `Mapper::getLinkedStudents`.
     *
     * This method isn't user-sensitive, but must be exposed to web request code.
     *
     * @param User $student
     *
     * @return array
     */
    public function getLinkedStudents(User $student): array
    {
        return $this->mapper->getLinkedStudents($student);
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
        if (\count($searchResults)) {
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
