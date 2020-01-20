<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\IModel;
use Schoolbox\Learning\Course\DataModel\AbstractUnitMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use \Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Resource\HomepageConfig;

/**
 * Mapper for the data model for Unit Specifications.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findOneByPredicates(Builder $pb, array $options = [])
 */
class Mapper extends AbstractUnitMapper
{
    /**
     * Setup data source, model and data mappings.
     */
    protected function mapping()
    {
        $this->hasTable('course_unit_specification');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('course_specification_id', CourseSpecificationMapper::class, 'id', null, null, 'course', 'course');
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, null, 'folder');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // SoftDelete/Restore Assessments in UnitSpecification
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteAssessments'));

        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreFolder'));
    }

    /**
     * Soft deletes all Assessments in the specified UnitSpecification
     *
     * @param Model $unitSpec
     * @return bool
     */
    public function softDeleteAssessments(Model $unitSpec)
    {
        $assessmentMapper = AssessmentMapper::getInstance();
        $assessments = $assessmentMapper->findByColumn('v_us.course_unit_specification_id', $unitSpec->getId(), ['deleted' => true]);
        $assessmentMapper->delete($assessments);
        return true;
    }


    /**
     * Ensures that if we restore a UnitSpec, the folder it is linked to is restored also
     *
     * Intentionally no corresponding post-delete callback. Deleting a UnitSpec should not automatically
     * cascade to the folder. The Controller should explicitly do that if desired.
     *
     * @param Model $unitSpec
     * @return bool
     */
    public function restoreFolder(Model $unitSpec)
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder');
        $folder = $folderMapper->findByColumn('id', $unitSpec->getFolder()->getId(), ['deleted' => true]);
        $folderMapper->restoreSoftDeleted($folder);
        return true;
    }


    /**
     * Folders and course spec are hydrated by default.
     *
     * @return string
     */
    public function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('folder', 'f')},
                {$this->getJoinColumnString('folder.homepages', 'fh')},
                {$this->getJoinColumnString('course', 'c')},
                {$this->getJoinColumnString('course.folder', 'cf')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('folder', '', 'f', true)}
            LEFT  JOIN {$this->getJoinSql('folder.homepages', 'f', 'fh')}
            INNER JOIN {$this->getJoinSql('course', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('course.folder', 'c', 'cf', true)}
        ";
    }

    /**
     * Hydrate joined folders
     *
     * @param PDOStatement $statement
     * @return Collection|Model[]
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $modelList = $this->createCollection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($row, 'folder', 'f');
            /** @var HomepageConfig $homepageConfig */
            $homepageConfig = $this->hydrateJoin($row, 'folder.homepages', 'fh');
            $folder->setHomepage($homepageConfig);
            $model->setFolder($folder);

            /** @var CourseSpecificationModel $course */
            $course = $this->hydrateJoin($row, 'course', 'c');
            /** @var FolderModel $courseFolder */
            $courseFolder = $this->hydrateJoin($row, 'course.folder', 'cf');
            $course->setFolder($courseFolder);
            $model->setCourse($course);
            $course->getUnits()[$model->getId()] = $model;

            // Add to list
            $modelList->setOffset($model->getId(), $model);
        }
        return $modelList;
    }


    /**
     * Finds the unit spec for this folder.
     *
     * @param int   $folderId
     * @param array $options
     * @return Model
     * @throws BaseMapper\Exception
     * @throws BaseMapper\Exception\Deleted
     */
    public function findByFolderId($folderId, $options = [])
    {
        return $this->findOneByColumn('folder_id', $folderId, $options);
    }

    /**
     * Finds all unit specs for this course spec.
     *
     * @param int|array $courseId
     * @param array     $options
     * @return Collection|Model[]
     */
    public function findAllByCourseId($courseId, array $options = [])
    {
        return $this->findByColumn('course_specification_id', $courseId, $options);
    }

    /**
     * Finds all unit specs for the course spec that is linked to this folder.
     *
     * @param int|array $courseFolderId
     * @param array     $options
     * @return Collection|Model[]
     */
    public function findAllByCourseFolderId($courseFolderId, $options = [])
    {
        return $this->findByColumn('c.folder_id', $courseFolderId, $options);
    }
}
