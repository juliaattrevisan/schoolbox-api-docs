<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use \Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;

/**
 * Mapper for the data model for Unit Specifications.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
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
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreAssessments'));
    }

    /**
     * Soft deletes all Assessments for the specified UnitSpecification
     *
     * @param Model $unitImpl
     */
    public function softDeleteAssessments(Model $unitImpl)
    {
        $assessmentMapper = AssessmentMapper::getInstance();
        $assessments = $assessmentMapper->findByColumn('v_us.course_unit_specification_id', $unitImpl->getId(), ['deleted' => true]);
        $assessmentMapper->delete($assessments);
    }

    /**
     * Soft deletes all Assessments for the specified UnitSpecification
     *
     * @param Model $unitImpl
     */
    public function restoreAssessments(Model $unitImpl)
    {
        $assessmentMapper = AssessmentMapper::getInstance();
        $assessments = $assessmentMapper->findByColumn('v_us.course_unit_specification_id', $unitImpl->getId(), ['deleted' => true]);
        $assessmentMapper->restoreSoftDeleted($assessments);
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
            INNER JOIN {$this->getJoinSql('course', '', 'c')}
            INNER JOIN {$this->getJoinSql('course.folder', 'c', 'cf')}
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
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($row, 'folder', 'f');
            $folder->setHomepage($this->hydrateJoin($row, 'folder.homepages', 'fh'));
            $model->setFolder($folder);

            /** @var CourseSpecificationModel $course */
            $course = $this->hydrateJoin($row, 'course', 'c');
            $course->setFolder($this->hydrateJoin($row, 'course.folder', 'cf'));
            $model->setCourse($course);

            // Add to list
            $modelList->add($model);
        }
        return $modelList;
    }

    /**
     * Finds the unit spec for this folder.
     *
     * @param int   $folderId
     * @param array $options
     * @return Model
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
    public function findAllByCourseId($courseId, $options = [])
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
