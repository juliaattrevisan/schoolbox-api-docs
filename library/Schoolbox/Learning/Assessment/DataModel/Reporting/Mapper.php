<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection<Model> findAll($options = [])
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
        $this->hasTable('reporting');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        /* Grading, Mark Types, Grade Groups
         */
        $this->joins('instructor_mark_type', SubmissionBox\MarkTypeMapper::class, 'mark_type_id');
        $this->joins('mark_type', SubmissionBox\MarkTypeMapper::class, 'mark_type_id');
        $this->joins('instructor_grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'instructorGradeGroup', 'instructor_grade_group');
        $this->joins('grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'gradeGroup', 'grade_group');
        $this->uses('instructor_max_mark', self::TYPE_DECIMAL);
        $this->uses('max_mark', self::TYPE_DECIMAL);
        $this->uses('mark_visibility', self::TYPE_INTEGER);
        $this->uses('feedback_comments_enable', self::TYPE_BOOLEAN);
        $this->uses('supporting_data_enable', self::TYPE_BOOLEAN);
        $this->uses('supporting_data_range', self::TYPE_STRING);
        $this->uses('supporting_data_folders', self::TYPE_STRING);
        $this->uses('supporting_data_markbook_enable', self::TYPE_BOOLEAN);

        /* Auditor fields
         */
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('created_by', self::TYPE_INTEGER, null, 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('updated_by', self::TYPE_INTEGER, null, 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('deleted_by', self::TYPE_INTEGER, null, 'deletedBy');

        $this->joins(
            'assessment_id',
            AssessmentMapper::class,
            'id',
            null,
            null,
            'assessmentModel',
            'assessmentModel',
        );

        $this->joinsVia(
            'reporting_curriculum_node',
            \Schoolbox\Curriculum\Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'reporting_id',
            null,
            null,
            null,
            'supportingDataCurriculumNodes'
        );


        /* TODO Add join for Feedbacks
         */

        /* Created/Updated/Deleted By fields need special treatment due to swampy reasons
         */
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'auditorFields']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'deleterFields']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreDeleterFields']);

        /* Recursive Undeleter callbacks: deleting a dialog agent also deletes all students' responses and feedbacks
         */
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteResponses']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteFeedbacks']);
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
                {$this->getJoinColumnString('assessmentModel', 'assessmentModel')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'assessmentModel', true)}
        ";

        return $selectString;
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param \PDOStatement $statement
     * @return GenericCollection|Model
     * @throws Exception
     * @throws DefinitionException
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): Model|GenericCollection
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $subobject */
            $subobject = $this->hydrate($row);

            /* Bolt on the IAssessable instance
             */
            /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessment */
            $assessment = $this->hydrateJoin($row, 'assessmentModel', 'assessmentModel');
            $subobject->setAssessmentModel($assessment);

            if (null === $stack->getBy('id', $subobject->getId())) {
                /* Initialise collections
                 */
                $subobject->setSupportingDataCurriculumNodes($this->getJoinmapper('reporting_curriculum_node')->createcollection());

                $stack[$subobject->getId()] = $subobject;
            }

        }

        if ($this->getConfigLoader()->get('curriculum_tagging_enabled')) {
            /* Supporting Data nodes (sry, there isn't a shorter name...)
             */
            $stack = $this->hydrateSupportingDataCurriculumNodesIntoCollection($stack);
        }


        return $stack;
    }


    /**
     * Helper method for hydrating curriculum nodes into the collection of
     * Reporting activity's supporting data, retrieved by `hydrateSelect`.
     *
     * @param GenericCollection $collection
     * @return GenericCollection
     */
    private function hydrateSupportingDataCurriculumNodesIntoCollection(GenericCollection $collection): GenericCollection
    {
        $reportingIds = $collection->keys();

        $pb = $this->getPredicateBuilder()->in('id', $reportingIds);
        $query = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('reporting_curriculum_node', 'cn')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN reporting_curriculum_node rcn ON reporting.id = rcn.reporting_id
            INNER JOIN curriculum_node cn ON rcn.curriculum_node_id = cn.id AND cn.deleted_at IS NULL
            WHERE {$pb->getWhereIntersect()}
        ";

        $curriculumNodeStatement = $this->database->query($query, $pb->getParams());

        foreach ($curriculumNodeStatement as $row) {
            /** @var \Schoolbox\Curriculum\Node\Model $curriculumNode */
            $curriculumNode = $this->hydrateJoin($row, 'reporting_curriculum_node', 'cn');
            $collection[$row['id']]->getSupportingDataCurriculumNodes()->setOffset($curriculumNode->getId(), $curriculumNode);
        }

        return $collection;
    }


    /**
     * Prefill auditor fields (the Swamp shouldn't need to know about this)
     */
    protected function auditorFields(Model $subobject): void
    {
        /* TODO Technical Uplift Candidate
         *  The traits are wrong and should have accepted a user instance rather than just an id
         *  All usages of the trait require you to ->getId() anyway, which means you have the user instance already.
         *  WHy not just supply that instead?
         */
        if (null === $subobject->getCreatedBy()) {
            $subobject->setCreatedBy($this->getSessionUser()->getId());
        }

        if (null === $subobject->getUpdatedBy()) {
            $subobject->setUpdatedBy($this->getSessionUser()->getId());
        }
    }


    /**
     * Prefill deleter fields (the Swamp shouldn't need to know about this)
     *
     * @param Model $subobject
     * @return void
     * @throws Exception
     */
    protected function deleterFields(Model $subobject): void
    {
        /* Remember who deleted this thing, in a dirty doubletap
         */
        $subobject->setDeletedBy($this->getSessionUser()->getId());
        $this->save($subobject);
    }


    /**
     * Forget who deleted this. Used when undeleting
     *
     * @param Model $subobject
     * @return void
     * @throws Exception
     */
    public function restoreDeleterFields(Model $subobject): void
    {
        /* Forget who deleted this thing, in a dirty doubletap
         */
        $subobject->setDeletedBy(null);
        $this->save($subobject);
    }


//    /**
//     * Soft deletes all responses
//     *
//     * @param Model $subobject
//     * @return void
//     * @throws Exception
//     */
//    public function softDeleteResponses(Model $subobject): void
//    {
//        $mapper = $this->getJoinMapper('responses');
//
//        $mapper->suppressNotification();
//        $mapper->deleteByColumn('reporting_id', $subobject->getId());
//        $mapper->restoreNotification();
//    }


    /**
     * Soft deletes all feedbacks
     *
     * @param Model $subobject
     * @return void
     * @throws Exception
     */
    public function softDeleteFeedbacks(Model $subobject): void
    {
        /* TODO this can probably be obtained via getJoinMapper somehow
         */
        $mapper = \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper::getInstance();

        $mapper->suppressNotification();
        $mapper->deleteByColumn('reporting_id', $subobject->getId());
        $mapper->restoreNotification();
    }
}
