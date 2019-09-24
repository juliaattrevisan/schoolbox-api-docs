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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;

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
     * Gets records for particular student visible to the given viewer
     * @param User     $viewer
     * @param User     $student
     * @param int|null $typeId
     * @param array    $pastoralGroupIds
     * @return GenericCollection|Model[]
     */
    public function findForStudent(User $viewer, User $student, ?int $typeId, array $pastoralGroupIds): GenericCollection
    {
        $rows    = $this->findForList($viewer, $typeId, $pastoralGroupIds, null, $student->getId());
        $records = Model::createCollection();
        foreach ($rows as $row) {
            if (empty($row['pastoral_access'])) {
                continue;
            }
            $record = new Model();
            $record->setId($row['pastoral_id'])
                   ->setOwner((new User())->setId($row['pastoral_owner_id']))
                   ->setHeading($row['pastoral_heading'])
                   ->setOwner((new User())->setId($row['pastoral_owner_id'])->setFirstname($row['user1_firstname'])->setLastname($row['user1_lastname']))
                   ->setModifiedBy((new User())->setId($row['pastoral_modified_by_id'])->setFirstname($row['user2_firstname'])->setLastname($row['user2_lastname']))
                   ->setCreatedAt((new Alaress_Date_Format($row['pastoral_created_at'], Alaress_Date_Format::DATABASE_FORMAT)))
                   ->setUpdatedAt((new Alaress_Date_Format($row['pastoral_updated_at'], Alaress_Date_Format::DATABASE_FORMAT)));
            // keep key as record id
            $records->offsetSet($record->getId(), $record);
        }

        return $records;
    }


    /**
     * Adds related records to the given records
     * @param GenericCollection $parentRecords
     * @param User              $viewer
     * @param int|null          $typeId
     * @param array             $pastoralGroupIds
     * @return GenericCollection
     */
    public function addLinkedInfo(GenericCollection $parentRecords, User $viewer, ?int $typeId, array $pastoralGroupIds): GenericCollection
    {
        $parentIds        = $parentRecords->getPropertyValues('id');
        $linkedRecordRows = $this->findForList($viewer, $typeId, $pastoralGroupIds, $parentIds, null);

        foreach ($linkedRecordRows as $linkedRecordRow) {
            if (empty($linkedRecordRow['pastoral_access'])) {
                continue;
            }
            $linkedRecord = new Model();
            $linkedRecord->setId($linkedRecordRow['pastoral_id'])
                         ->setHeading($linkedRecordRow['pastoral_heading'])
                         ->setOwner((new User())->setId($linkedRecordRow['pastoral_owner_id'])->setFirstname($linkedRecordRow['user1_firstname'])->setLastname($linkedRecordRow['user1_lastname']))
                         ->setStudent((new User())->setId($linkedRecordRow['student_id'])->setFirstname($linkedRecordRow['user2_firstname'])->setLastname($linkedRecordRow['user2_lastname']))
                         ->setCreatedAt((new Alaress_Date_Format($linkedRecordRow['pastoral_created_at'], Alaress_Date_Format::DATABASE_FORMAT)))
                         ->setUpdatedAt((new Alaress_Date_Format($linkedRecordRow['pastoral_updated_at'], Alaress_Date_Format::DATABASE_FORMAT)));

            /** @var Model $parentRecord */
            $parentRecord = $parentRecords->offsetGet($linkedRecordRow['parent_record_id']);
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
     * @return array
     */
    private function findForList(User $viewer, ?int $typeId, array $pastoralGroupIds, ?array $parentRecordIds, ?int $studentId): array
    {
        // pastoral moderators
        $pastoralService = $viewer->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL);

        $outerPb = (new Builder($this->mapper->getDatabase(), ''));

        $params = [];
        if (!$pastoralService) {
            $innerPb = (new Builder($this->mapper->getDatabase(), ''));
            $innerPb->equals('p.owner_id', $viewer->getId());
            $innerPb->equals('pa.user_id', $viewer->getId());
            $innerPb->equals('pa.role_id', $viewer->getRole()->getId());
            if (!empty($pastoralGroupIds)) {
                $innerPb->in('pa.group_id', $pastoralGroupIds);
            }
            $outerPb->any($innerPb);
            $params = $innerPb->getParams();
        }

        // This query expects either parent record ids or student id is set.
        if (!empty($parentRecordIds)) {
            $outerPb->in('p.parent_id', $parentRecordIds);
            $user2Column = 'p.student_id';
        } else {
            if (null !== $studentId) {
                $outerPb->equals('p.student_id', $studentId);
                $user2Column = 'p.modified_by_user_id';
            } else {
                return [];
            }
        }

        $outerPb->isNull('p.deleted_at');
        if (null !== $typeId) {
            $outerPb->equals('p.type_id', $typeId);
        }

        $where = implode(' AND ', $outerPb->getWhere());

        $sql = "
        SELECT p.id                  AS pastoral_id,
               p.owner_id            AS pastoral_owner_id,
               p.parent_id           AS parent_record_id,
               p.heading             AS pastoral_heading,
               p.modified_by_user_id AS pastoral_modified_by_id,
               p.student_id          AS student_id,
               p.created_at          AS pastoral_created_at,
               p.updated_at          AS pastoral_updated_at,
               min(pa.access)        AS pastoral_access,
               u1.firstname          AS user1_firstname,
               u1.lastname           AS user1_lastname,
               u2.firstname          AS user2_firstname,
               u2.lastname           AS user2_lastname
        FROM pastoral p
        LEFT JOIN pastoral_access pa ON p.id = pa.pastoral_id
        LEFT JOIN `user` u1 ON p.owner_id = u1.id
        LEFT JOIN `user` u2 ON {$user2Column} = u2.id
        WHERE {$where}
        GROUP BY p.id
        ORDER BY p.updated_at DESC
        ";

        $stmt = $this->mapper->getDatabase()->query($sql, array_merge($params, $outerPb->getParams()));
        return $stmt->fetchAll();
    }


    /**
     * User-sensitive wrapper for `Mapper::findByColumn`.
     *
     * @param User   $currentUser
     * @param string $columnName
     * @param string $columnValue
     * @param array  $options
     *
     * @return Collection
     * @throws MapperException\InsufficientHydrationException
     */
    public function findByColumn(User $currentUser, $columnName, $columnValue, array $options = []): Collection
    {
        return $this->accessLevelCensor->filterRecordsForUser(
            $this->mapper->findByColumn($columnName, $columnValue, $options),
            $currentUser
        );
    }


    /**
     * User-sensitive wrapper for `Mapper::findByColumnList`.
     *
     * @param User  $currentUser
     * @param       $columnList
     * @param array $options
     *
     * @return Collection
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     */
    public function findByColumnList(User $currentUser, $columnList, array $options = []): Collection
    {
        return $this->accessLevelCensor->filterRecordsForUser($this->mapper->findByColumnList($columnList, $options),
            $currentUser);
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
