<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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

    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Wrapper for `Mapper::createModel`.
     *
     * Not user-sensitive, but must be exposed to web request code.
     *
     * @param int $id
     * @return Model
     */
    public function createModel($id = null): Model
    {
        return $this->mapper->createModel($id);
    }

    /**
     * User-sensitive wrapper for `Mapper::findById`.
     *
     * @param User $user
     * @param int  $id
     * @return Model
     * @throws MapperException
     */
    public function findById(User $user, $id): Model
    {
        $model = $this->mapper->filterRecordForUser($this->mapper->findById($id), $user);
        if (!$model instanceof Model) {
            throw new MapperException('Instance of object not found');
        }
        return $model;
    }

    /**
     * User-sensitive wrapper for `Mapper::findByColumn`.
     *
     * @param User   $user
     * @param string $columnName
     * @param string $columnValue
     * @param array  $options
     * @return Collection
     */
    public function findByColumn(User $user, $columnName, $columnValue, $options = []): Collection
    {
        return $this->mapper->filterRecordsForUser(
            $this->mapper->findByColumn($columnName, $columnValue, $options),
            $user
        );
    }

    /**
     * User-sensitive wrapper for `Mapper::findByColumnList`.
     *
     * @param User  $user
     * @param array $columnList
     * @param array $options
     * @return Collection|Model[]
     */
    public function findByColumnList(User $user, $columnList, $options = []): Collection
    {
        return $this->mapper->filterRecordsForUser($this->mapper->findByColumnList($columnList, $options), $user);
    }

    /**
     * User-sensitive wrapper for `Mapper::findByTag`.
     *
     * @param User        $user
     * @param PastoralTag $tag
     *
     * @return Collection|Model[]
     */
    public function findByTag(User $user, PastoralTag $tag): Collection
    {
        return $this->mapper->filterRecordsForUser($this->mapper->findByTag($tag), $user);
    }

    /**
     * User-sensitive wrapper for `Mapper::getReportData`.
     *
     * @param User                  $user
     * @param Alaress_Date_Format   $before_date
     * @param Alaress_Date_Format   $after_date
     * @param null|PastoralSeverity $severity
     * @param Collection            $types
     * @param Collection            $authors
     * @param Collection            $students
     * @param Collection            $tags
     * @param Collection            $order
     * @param                       $involved
     * @param Collection            $years
     * @return Collection
     */
    public function getReportData(
        User $user,
        Alaress_Date_Format $before_date,
        Alaress_Date_Format $after_date,
        ?PastoralSeverity $severity,
        Collection $types,
        Collection $authors,
        Collection $students,
        Collection $tags,
        Collection $order,
        $involved,
        Collection $years
    ): Collection {
        // The mapper method is user-sensitive,
        // but filter anyway as it's better to be explicit than implicit.
        return $this->mapper->filterRecordsForUser(
            $this->mapper->getReportData(
                $user,
                $before_date,
                $after_date,
                $severity,
                $types,
                $authors,
                $students,
                $tags,
                $order,
                $involved,
                $years
            ),
            $user
        );
    }

    /**
     * Wrapper for `Mapper::getLinkedStudents`.
     *
     * This method isn't user-sensitive, but must be exposed to web request code.
     *
     * @param User $student
     * @return array
     */
    public function getLinkedStudents(User $student): array
    {
        return $this->mapper->getLinkedStudents($student);
    }

    /**
     * User-sensitive wrapper for `Mapper::findLinked`.
     *
     * @param User $user
     * @param User $student
     * @param User $linkedStudent
     * @param int $typeId
     *
     * @return Collection|Model[]
     */
    public function findLinked(User $user, User $student, User $linkedStudent, $typeId = null): Collection
    {
        return $this->mapper->filterRecordsForUser($this->mapper->findLinked($student, $linkedStudent, $typeId), $user);
    }

    /**
     * User-sensitive wrapper for `Mapper::getSearchResults`.
     *
     * @param User $user
     * @param string $search
     * @return Collection
     */
    public function getSearchResults(User $user, $search): Collection
    {
        return $this->mapper->filterRecordsForUser($this->mapper->getSearchResults($search, $user), $user);
    }

    /**
     * User-sensitive wrapper for `Mapper::findRecentDashboard`.
     *
     * @param User $user
     * @param int $count
     *
     * @return Collection|Model[]
     */
    public function findRecentDashboard(User $user, $count = 5): Collection
    {
        // The mapper method is user-sensitive,
        // but filter anyway as it's better to be explicit than implicit.
        return $this->mapper->filterRecordsForUser($this->mapper->findRecentDashboard($user, $count), $user);
    }

    /**
     * Wrapper for `Mapper::save`.
     *
     * Not user-sensitive, but must be exposed to web request code.
     *
     * @param Model $model
     */
    public function save(Model $model)
    {
        $this->mapper->save($model);
    }

    /**
     * Wrapper for `Mapper::delete`.
     *
     * Not user-sensitive, but must be exposed to web request code.
     *
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool
    {
        return $this->mapper->delete($model);
    }
}
