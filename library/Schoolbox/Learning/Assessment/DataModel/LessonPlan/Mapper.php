<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\CallbackException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('lesson_plan');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('lesson_plan_template_id', self::TYPE_INTEGER, null, 'lessonPlanTemplate');
        $this->uses('visibility', self::TYPE_STRING);
        $this->uses('duration', self::TYPE_STRING, null, 'lessonPlanDuration');
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->joins('deleted_by', UserMapper::class, 'id', null, null, 'deletedBy', 'deletedBy');

        // This column is duplicate of assessment.deleted_at, but is required to ensure soft-deletion on this table.
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin(
            'lessonPlanField',
            [
                'mapper'    => Field\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'lesson_plan_id',
                'multiple'  => true,
            ]
        );

        $this->joins(
            'assessment_id',
            AssessmentMapper::class,
            'id',
            null,
            null,
            'assessmentModel',
            'assessmentModel',
        );

        $this->hasJoin(
            'files',
            [
                'multiple'  => true,
                'mapper'    => File\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'lesson_plan_id',
            ]
        );

        /* Linked Assessments
         */
        $this->hasJoin(
            'linkedAssessments',
            [
                'multiple'  => true,
                'mapper'    => LinkedAssessments\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'lesson_plan_id',
            ]
        );
//        $this->joinsVia(
//            'lesson_plan_assessment',
//            AssessmentMapper::class,
//            'id',
//            'lesson_plan_id',
//            'assessment_id',
//            null,
//            null,
//            null,
//            'linkedAssessments'
//        );


        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'auditorFields']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateContext']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteFields']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteAttachments']);
    }


    /**
     * {@inheritdoc}
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        $selectString = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('createdBy', 'createdBy')},
                {$this->getJoinColumnString('updatedBy', 'updatedBy')},
                {$this->getJoinColumnString('deletedBy', 'deletedBy')},
                {$this->getJoinColumnString('lessonPlanField', 'lessonPlanField')},
                {$this->getJoinColumnString('assessmentModel', 'assessmentModel')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'assessmentModel', true)}
            INNER JOIN {$this->getJoinSql('createdBy', '', 'createdBy', true)}
            INNER JOIN {$this->getJoinSql('updatedBy', '', 'updatedBy', true)}
            LEFT JOIN {$this->getJoinSql('deletedBy', '', 'deletedBy', true)}
            LEFT JOIN {$this->getJoinSql('lessonPlanField', '', 'lessonPlanField')}
        ";

        return $selectString;
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model
     * @throws Exception
     * @throws DefinitionException
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(PDOStatement $statement): Model|GenericCollection
    {
        $fieldMapper = $this->getJoinMapper('lessonPlanField');

        /** @var Model[] $lessonPlans */
        $lessonPlans = [];

        foreach ($statement as $row) {
            if (!isset($lessonPlans[$row['id']])) {
                /** @var Model|null $lessonPlan */
                $lessonPlan = $this->hydrate($row);
                $lessonPlan->setFields($fieldMapper->createCollection());

                /* Bolt on the IAssessable instance
                 */
                $assessment =  $this->hydrateJoin($row, 'assessmentModel', 'assessmentModel');
                $lessonPlan->setAssessmentModel($assessment);

                /* Stack it up
                 */
                $lessonPlans[$row['id']] = $lessonPlan;
            }

            /* Various effectors
             *
             * Not sure how deletedBy gets used yet; may be superfluous
             * in this contextual usage
             */
            /** @var User|null $createdBy */
            $createdBy = $this->hydrateJoin($row, 'createdBy', 'createdBy');

            /** @var User|null $updatedBy */
            $updatedBy = $this->hydrateJoin($row, 'updatedBy', 'updatedBy');

            /** @var User|null $deletedBy */
            $deletedBy = $this->hydrateJoin($row, 'deletedBy', 'deletedBy');

            if ($createdBy) {
                $lessonPlans[$row['id']]->setCreatedBy($createdBy);
            }

            if ($updatedBy) {
                $lessonPlans[$row['id']]->setUpdatedBy($updatedBy);
            }

            if ($deletedBy) {
                $lessonPlans[$row['id']]->setDeletedBy($deletedBy);
            }

            /* Stack up all the adjoining fields
             */
            $lessonPlanField = $fieldMapper->hydrate($row, 'lessonPlanField');

            if ($lessonPlanField instanceof Field\Model) {
                $lessonPlans[$row['id']]->addField($lessonPlanField);
            }
        }

        /* Sort sequentially
         */
        foreach ($lessonPlans as $lessonPlan) {
            $lessonPlan->getFields()->sortBy('sequence', 'asc');
        }

        return $this->createCollection(array_values($lessonPlans));
    }


    /**
     * Persist Lesson Plan attachments/files.
     *
     * @param Model $lessonPlan
     */
    public function saveFiles(Model $lessonPlan): void
    {
        $this->cascadeJoin('files', ['lesson_plan_id' => $lessonPlan->getId()], $lessonPlan->getAttachments());
    }


    /**
     * Persist Lesson Plan Fields.
     *
     * @param Model $lessonPlan
     */
    public function saveFields(Model $lessonPlan): void
    {
        /* TODO This might not work once we delink joins
         */
        $this->cascadeJoin('lessonPlanField', ['lesson_plan_id' => $lessonPlan->getId()], $lessonPlan->getFields());
    }

    /**
     * Delete the fields that were associated with $lessonPlanTemplate
     *
     * @param Model $lessonPlan
     * @return void
     * @throws DefinitionException
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    protected function softDeleteFields(Model $lessonPlan): void
    {
        /* Remember who deleted this thing, in a dirty doubletap
         */
        $lessonPlan->setDeletedBy($this->getSessionUser());
        $this->save($lessonPlan);

        /* Dump away any associated Fields too
         */
        $this->getJoinMapper('lessonPlanField')->delete($lessonPlan->getFields());
    }


    /**
     * Delete the files (attachments) associated with a Lesson Plan
     *
     * @param Model $lessonPlan
     * @return void
     * @throws DefinitionException
     */
    protected function softDeleteAttachments(Model $lessonPlan): void
    {
        $this->getJoinMapper('files')->deleteByColumn('lesson_plan_id', $lessonPlan->getId());
    }


    /**
     * Prefill auditor fields (the Swamp shouldn't need to know about this)
     */
    protected function auditorFields(Model $lessonPlan): void
    {
        if (null === $lessonPlan->getCreatedBy()) {
            $lessonPlan->setCreatedBy($this->getSessionUser());
        }

        if (null === $lessonPlan->getUpdatedBy()) {
            $lessonPlan->setUpdatedBy($this->getSessionUser());
        }
    }

    /**
     * Validates context is a valid option (Unit / Project)
     *
     * @param Model $model
     * @return bool
     * @throws CallbackException
     */
    protected function validateContext(Model $model): bool
    {
        if ($model->getAssessmentModel()->getAssessmentContext() instanceof \Schoolbox\Resource\Folder\Model) {
            throw new CallbackException("Lesson Plans are unable to created inside a folder");
        }

        return true;
    }


    /**
     * Get all lesson plans from a list of folders
     * between specified dates
     * and ensuring we are only returning lesson plans that are visible to the user
     *
     * @param array $folderIDs list of folders to gather all lesson plans from within
     * @param null|\Alaress_Date_Format $startDate
     * @param null|\Alaress_Date_Format $endDate
     * @param null|\Alaress_Date_Format $publishDate
     * @param bool $allowStaffOnly should we return only student visible plans? or all plans?
     * @return array<int> array of the assessment ID's for the lesson plans
     */
    public function findForFolder(array $folderIDs, ?\Alaress_Date_Format $startDate, ?\Alaress_Date_Format $endDate, ?\Alaress_Date_Format $publishDate, bool $allowStaffOnly = false): array
    {
        $pb = $this->getDatabase()
            ->buildPredicateBuilder('')
            ->in('mv_assessment_relation.folder_id', $folderIDs);

        /**
         * if we have dates then we should use them, if not ignore limiting to dates
         */
        if (null !== $startDate && null !== $endDate) {
            $pb->betweenDates('assessment.due_date', $startDate, $endDate);
        } elseif (null !== $endDate) {
            // only has an end date use that as an upper limit
            $pb->lessThanOrEqualTo('assessment.due_date', $endDate->getDatabase());
        } else {
            // only has an end date use that as an upper limit
//            $pb->lessThanOrEqualTo('assessment.due_date', $endDate->getDatabase());
        }
        // If we have a published date (student view), ensure we only show lessonplans that have been published
        if (null !== $publishDate) {
            $pb->lessThanOrEqualTo('assessment.publish_date', $publishDate->getDatabase());
        }

        // Are we limiting to lesson plans visible to students? or staff only?
        if (!$allowStaffOnly) {
            $pb->equals('lesson_plan.visibility', 'student');
        }

        $whereClause = implode(' AND ', $pb->getWhere());
        $query       = "
            SELECT * FROM assessment
            JOIN lesson_plan ON (assessment.id = lesson_plan.assessment_id)
            JOIN mv_assessment_relation on assessment.id = mv_assessment_relation.assessment_id
            WHERE {$whereClause}
            ";
        $statement   = $this->getDatabase()->executeQuery($query, $pb->getParams());

        // Return only the assessment ID's
        return $statement->fetchColumnArray('assessment_id');
    }
}
