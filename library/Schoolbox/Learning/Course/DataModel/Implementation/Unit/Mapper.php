<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation\Unit;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Column\Date;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Course\DataModel\AbstractUnitMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementationModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\HomepageConfig;

/**
 * Mapper for the data model for Unit Implementations.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByPredicates(Builder $pb, array $options = [])
 * @method GenericCollection|Model[] createCollection($contents = [])
 */
class Mapper extends AbstractUnitMapper
{
    /**
     * Setup data source, model and data mappings.
     */
    protected function mapping()
    {
        $this->hasTable('course_unit_implementation');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('course_implementation_id', CourseImplementationMapper::class, 'id', null, null, 'course', 'course');
        $this->joins('course_unit_specification_id', UnitSpecificationMapper::class, 'id', null, null, 'specification', 'specification');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('open_date', Date::startOfDay());
        $this->uses('due_date', Date::endOfDay());
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // SoftDelete/Restore Assessments in UnitImplementation
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteAssessments'));
    }

    /**
     * Soft deletes all Assessments for the specified UnitImplementation
     *
     * @param Model $unitImpl
     */
    public function softDeleteAssessments(Model $unitImpl)
    {
        $assessmentMapper = AssessmentMapper::getInstance();
        $assessments = $assessmentMapper->findByColumn('v_ui.course_unit_implementation_id', $unitImpl->getId(), ['deleted' => true]);
        $assessmentMapper->delete($assessments);
    }


    /**
     * Finds all unit implementations for this course implementation.
     *
     * @param int|array $courseId
     * @param array     $options
     * @return Collection|Model[]
     */
    public function findAllByCourseId($courseId, array $options = [])
    {
        return $this->findByColumn('course_implementation_id', $courseId, $options);
    }

    /**
     * Finds all unit implementations for the specified Unit Specification[s].
     *
     * @param int|array $unitSpecId
     * @param array     $options
     * @return Collection|Model[]
     */
    public function findAllByUnitSpecificationId($unitSpecId, $options = [])
    {
        return $this->findByColumn('course_unit_specification_id', $unitSpecId, $options);
    }

    /**
     * @inheritdoc
     */
    public function getSelectString()
    {
        // Ensure course + its folder, and specification + its folder are selected.
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('course', 'c')},
                {$this->getJoinColumnString('course.folder', 'cf')},
                {$this->getJoinColumnString('course.folder.codes', 'cfc')},
                {$this->getJoinColumnString('course.folder.homepages', 'cfh')},
                {$this->getJoinColumnString('specification', 's')},
                {$this->getJoinColumnString('specification.folder', 'sf')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('course', '', 'c', true)}
            INNER JOIN {$this->getJoinSql('course.folder', 'c', 'cf', true)}
            LEFT  JOIN {$this->getJoinSql('course.folder.codes', 'cf', 'cfc')}
            LEFT  JOIN {$this->getJoinSql('course.folder.homepages', 'cf', 'cfh')}
            INNER JOIN {$this->getJoinSql('specification', '', 's', true)}
            INNER JOIN {$this->getJoinSql('specification.folder', 's', 'sf', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $modelList = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var CourseImplementationModel $course */
            $course = $this->hydrateJoin($row, 'course', 'c');
            /** @var Folder $courseFolder */
            $courseFolder = $this->hydrateJoin($row, 'course.folder', 'cf');
            /** @var HomepageConfig $homepageConfig */
            $homepageConfig = $this->hydrateJoin($row, 'course.folder.homepages', 'cfh');

            // Folder Codes: Ensure we de-duplicate (as we have joined multiple 1:N relationships)
            if ($row['cfc.code']) {
                $folderCode = $this->hydrateJoin($row, 'course.folder.codes', 'cfc');
                $courseFolder->getCodes()[$folderCode->getCode()] = $folderCode;
            }

            $courseFolder->setHomepage($homepageConfig);
            $course->setFolder($courseFolder);
            $model->setCourse($course);

            /** @var UnitSpecificationModel $specification */
            $specification = $this->hydrateJoin($row, 'specification', 's');
            /** @var Folder $specFolder */
            $specFolder = $this->hydrateJoin($row, 'specification.folder', 'sf');
            $specification->setFolder($specFolder);
            $model->setSpecification($specification);
            $specification->getCourse()->getUnits()[$specification->getId()] = $specification;
            $specification->getUnitImplementations()[$model->getId()] = $model;

            // Add
            $modelList[$model->getId()] = $model;
        }

        return $modelList;
    }
}
