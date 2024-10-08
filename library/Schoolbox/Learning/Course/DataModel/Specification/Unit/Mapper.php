<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Ramsey\Uuid\Uuid;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\AbstractUnitMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
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
        $this->uses('uuid', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);


        // SoftDelete/Restore Assessments in UnitSpecification
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteAssessments'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreFolder'));

        /* Assign a uuid if it doesn't already exist
         */
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'assignUuid']);

        $this->registerPersistSearchIndexCallbacks();
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
            FROM {$this->database->getQuoter()->quoteIdentifier($this->table)}
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
        // record of folder ids to hydrate curriculum codes from
        $folderIds = [];

        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder');

        /** @var NodeMapper $curriculumNodeMapper */
        $curriculumNodeMapper = $folderMapper->getJoinMapper('curriculum_node_for_category');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var FolderModel $folder */
            $folder = $folderMapper->hydrate($row, 'f');
            $folderIds[] = $row['f.id'];
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

        // fetch curric for joined folders
        $folderCurricNodes = $curriculumNodeMapper->findForOtherMapper(
            $folderMapper,
            'curriculum_node_for_category',
            $folderIds
        );

        foreach ($modelList as $model) {
            if (isset($folderCurricNodes[$model->getFolder()->getId()])) {
                // if joined folder has codes, then inject them back onto the folder
                $model->getFolder()->setCurriculumNodes($folderCurricNodes[$model->getFolder()->getId()]);
            } else {
                // folder has no curric codes, set to empty collection
                $model->getFolder()->setCurriculumNodes($curriculumNodeMapper->createCollection());
            }
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
     * Asserts that every $unitSpec has a uuid prior to saving
     *
     * If it didn't already have one, it will be assigned one
     * Otherwise it'll maintain what it previously knew
     *
     * @param Model $unitSpec
     * @return bool
     */
    public function assignUuid(Model $unitSpec): bool
    {
        if ('' === trim((string)$unitSpec->getUuid())) {
            $unitSpec->setUuid((string)Uuid::uuid4());
        }

        return true;
    }
}
