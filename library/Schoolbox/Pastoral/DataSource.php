<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\User;

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
     * @param Mapper            $mapper
     * @param AccessLevelCensor $accessLevelCensor
     */
    public function __construct(Mapper $mapper, AccessLevelCensor $accessLevelCensor)
    {
        $this->mapper              = $mapper;
        $this->accessLevelCensor   = $accessLevelCensor;
        $this->pastoralAuditMapper = Audit\Mapper::getInstance(); //TODO inject
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

        $model = $this->accessLevelCensor->filterRecordsForUser($this->mapper->createCollection([$model]), $currentUser)->first();

        if (!$model instanceof Model) {
            throw new MapperException\NotFound();
        }

        return $model;
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

        $searchResults = [];
        foreach ($filteredRecords as $filteredRecord) {
            $searchResults[] = $filteredRecord->getId();
        }

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
