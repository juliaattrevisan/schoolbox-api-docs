<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

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
        $this->joins('instructor_mark_type', SubmissionBox\MarkTypeMapper::class, 'mark_type_id', null, null, 'instructorMarkType', 'instructorMarkType');
        $this->joins('mark_type', SubmissionBox\MarkTypeMapper::class, 'mark_type_id', null, null, 'markType', 'markType');
        $this->joins('instructor_grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'instructorGradeGroup', 'instructorGradeGroup');
        $this->joins('grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'gradeGroup', 'gradeGroup');
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

        $this->joinsVia(
            'reporting_work_type',
            \Schoolbox\Learning\Assessment\WorkType\Mapper::class,
            'id',
            'work_type_id',
            'reporting_id',
            null,
            null,
            null,
            'supportingDataWorkTypes'
        );

        /* Created/Updated/Deleted By fields need special treatment due to swampy reasons
         */
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'auditorFields']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'deleterFields']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreDeleterFields']);

        /* Clear supporting data curric nodes and work types selections
         */
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'clearRelations']);

        /* Recursive Undeleter callbacks: deleting a dialog agent also deletes all students' responses and feedbacks
         */
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
                {$this->getJoinColumnString('assessmentModel', 'assessmentModel')},
                {$this->getJoinColumnString('instructorMarkType', 'instructorMarkType')},
                {$this->getJoinColumnString('markType', 'markType')},
                {$this->getJoinColumnString('instructorGradeGroup', 'instructorGradeGroup')},
                {$this->getJoinColumnString('gradeGroup', 'gradeGroup')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'assessmentModel', true)}
            INNER JOIN {$this->getJoinSql('instructorMarkType', '', 'instructorMarkType', true)}
            INNER JOIN {$this->getJoinSql('markType', '', 'markType', true)}
            LEFT JOIN {$this->getJoinSql('instructorGradeGroup', '', 'instructorGradeGroup', true)}
            LEFT JOIN {$this->getJoinSql('gradeGroup', '', 'gradeGroup', true)}
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
                /* Assign mark type and instructor mark type: Letter Grade thing is hydrated after the fact only for
                 * instances that need it, saving us an enormous left join for all the levels that might exist in a
                 * Grade Group (some schools have A LOT of grade levels)
                 */
                $instructorMarkType = $this->hydrateJoin($row, 'instructorMarkType', 'instructorMarkType');
                $subobject->setInstructorMarkType($instructorMarkType);

                $markType = $this->hydrateJoin($row, 'markType', 'markType');
                $subobject->setMarkType($markType);

                $instructorGradeGroup = $this->hydrateJoin($row, 'instructorGradeGroup', 'instructorGradeGroup');
                $subobject->setInstructorGradeGroup($instructorGradeGroup);

                $gradeGroup = $this->hydrateJoin($row, 'gradeGroup', 'gradeGroup');
                $subobject->setGradeGroup($gradeGroup);

                /* Initialise collections
                 */
                $subobject->setSupportingDataCurriculumNodes($this->getJoinmapper('reporting_curriculum_node')->createcollection());
                $subobject->setSupportingDataWorkTypes($this->getJoinmapper('reporting_work_type')->createcollection());

                $stack[$subobject->getId()] = $subobject;
            }
        }

        /* Hydrate Grade Groups with Grade Levels, if applicable
         */
        $stack = $this->hydrateGradeGroups($stack);

        /* Supporting Data nodes (sry, there isn't a shorter name...)
         */
        if ($this->getConfigLoader()->get('curriculum_tagging_enabled')) {
            $stack = $this->hydrateSupportingDataCurriculumNodesIntoCollection($stack);
        }

        return $this->hydrateSupportingDataWorkTypesIntoCollection($stack);
    }


    /**
     * Hydrates Grade Groups with Grade Levels into $collection instances of Reporting Models
     * I don't really know how Due Works did this, other than an ants-related comment, which is a bit mystical to me.
     * Anyway, this gets all the mark types, grade groups and grade levels used by the $collection, and then assigns
     * them accordingly.
     *
     * It relies on the mapper cache's behaviour; I wanted to ensure that each assessment got its own instance of
     * Mark Types and Grade Groups, despite them being the same contents. This should prevent you modifying the settings
     * in one Reporting instance and having it quietly affect another since objects pass by reference.
     *
     * @param GenericCollection $collection
     * @return GenericCollection
     * @throws Exception
     * @throws Deleted
     */
    private function hydrateGradeGroups(GenericCollection $collection): GenericCollection
    {
        $instructorGradeGroupMapper = $this->getJoinMapper('instructorGradeGroup');
        $gradeGroupMapper = $this->getJoinMapper('gradeGroup');

        foreach ($collection as $reporting) {
            /* Yes I know, Mark Types are in the SubmissionBox area, despite being used pretty much everywhere
             * TODO Amri, we should refactor this into a common area along with the others currently in SubmissionBox
             */
            if (SubmissionBox\MarkType::SLUG_LETTER === $reporting->getInstructorMarkType()->getSlug()) {
                $instructorGradeGroup = $instructorGradeGroupMapper->findById($reporting->getInstructorGradeGroup()->getId());

                $reporting->setInstructorGradeGroup($instructorGradeGroup);
            }

            if (SubmissionBox\MarkType::SLUG_LETTER === $reporting->getMarkType()->getSlug()) {
                $gradeGroup = $gradeGroupMapper->findById($reporting->getGradeGroup()->getId());

                $reporting->setGradeGroup($gradeGroup);
            }
        }

        return $collection;
    }


    /**
     * Clear out supporting data curric nodes and work types prior to saving a new set, allowing you to "clear the lists"
     * for the maybe-rare circumstance that you want zero curric nodes and zero work types selected...not sure if those
     * scenarios actually occur at the moment.
     *
     * @param Model $model
     * @param Model|null $previous
     * @return void
     * @throws \Throwable
     */
    public function clearRelations(Model $model, ?Model $previous = null): void
    {
        $this->updateSupportingDataCurriculumNodeRelations($model, $previous);
        $this->updateSupportingDataWorkTypeRelations($model, $previous);
    }


    /**
     * Updates supporting data's curriculum nodes using a diff to avoid an exotic transaction issue: see SA-21417
     *
     * @param Model $model
     * @param Model|null $previous
     * @return void
     * @throws \Throwable
     */
    private function updateSupportingDataCurriculumNodeRelations(Model $model, ?Model $previous = null): void
    {
        /* Set up a container for new curric node instances
         */
        $newSelectionIds = [];

        /* Pile in selections that are supposed to exist after this mutation
         * The null coalesce dodges around a swampy behaviour where a collection isn't instantiated in $model for the
         * particular case where you removed all the nodes via the ui (ie. you literally wanted to select nothing)
         */
        foreach ($model->getSupportingDataCurriculumNodes() ?? [] as $curriculumNode) {
            $newSelectionIds[] = $curriculumNode->getId();
        }

        /* Load nodes that already exist in the db: we maybe need to remove some of these
         */
        $oldSelections = $this->database->executeQuery("
            SELECT curriculum_node_id
            FROM reporting_curriculum_node
            WHERE reporting_id = :reportingId
        ", [
            'reportingId' => $model->getId()
        ]);

        $oldSelectionIds = $oldSelections->fetchColumnArray('curriculum_node_id');

        /* Calculate the diff because we need to delete those that are no longer in $newSelections; since we're
         * interrogating the join table, there's no exact mapper/model for it: we also don't really need to hydrate
         * anything just to delete it, so we can fetch the ids by themselves first.
         */
        $diff = array_diff($oldSelectionIds, $newSelectionIds);

        /* Old selections == new selections (you didn't change anything)
         * Nothing to do: exit earlyish
         */
        if (!count($diff)) {
            return;
        }

        /* If there's something to remove, do it
         *
         * Make all the removal changes...or none of the removal changes - preventing a partial update where one query
         * succeeds but others fail (for whatever reason)
         */
        $this->database->transact(function() use ($model, $diff) {
            $placeholders = Builder::getArrayPlaceholders($diff, 'cnodeId');
            $params = Builder::getArrayParameters($diff, 'cnodeId');

            $this->database->executeQuery("
                DELETE FROM reporting_curriculum_node
                WHERE reporting_id = :reportingId
                AND curriculum_node_id IN {$placeholders}
            ", array_merge([
                'reportingId' => $model->getId(),
            ], $params));
        });
    }


    /**
     * Updates supporting data's work types using a diff to avoid an exotic transaction issue: see SA-21417
     *
     * @param Model $model
     * @param Model|null $previous
     * @return void
     * @throws \Throwable
     */
    private function updateSupportingDataWorkTypeRelations(Model $model, ?Model $previous = null): void
    {
        /* Set up a container for new curric node instances
         */
        $newSelectionIds = [];

        /* Pile in selections that are supposed to exist after this mutation
         * The null coalesce dodges around a swampy behaviour where a collection isn't instantiated in $model for the
         * particular case where you removed all the work types via the ui (ie. you literally wanted to select nothing)
         */
        foreach ($model->getSupportingDataWorkTypes() ?? [] as $workType) {
            $newSelectionIds[] = $workType->getId();
        }

        /* Load nodes that already exist in the db: we maybe need to remove some of these
         */
        $oldSelections = $this->database->executeQuery("
            SELECT work_type_id
            FROM reporting_work_type
            WHERE reporting_id = :reportingId
        ", [
            'reportingId' => $model->getId()
        ]);

        $oldSelectionIds = $oldSelections->fetchColumnArray('work_type_id');

        /* Calculate the diff because we need to delete those that are no longer in $newSelections; since we're
         * interrogating the join table, there's no exact mapper/model for it: we also don't really need to hydrate
         * anything just to delete it, so we can fetch the ids by themselves first.
         */
        $diff = array_diff($oldSelectionIds, $newSelectionIds);

        /* Old selections == new selections (you didn't change anything)
         * Nothing to do: exit earlyish
         */
        if (!count($diff)) {
            return;
        }

        /* If there's something to remove, do it
         *
         * Make all the removal changes...or none of the removal changes - preventing a partial update where one query
         * succeeds but others fail (for whatever reason)
         */
        $this->database->transact(function() use ($model, $diff) {
            $placeholders = Builder::getArrayPlaceholders($diff, 'worktypeId');
            $params = Builder::getArrayParameters($diff, 'worktypeId');

            $this->database->executeQuery("
                DELETE FROM reporting_work_type
                WHERE reporting_id = :reportingId
                AND work_type_id IN {$placeholders}
            ", array_merge([
                'reportingId' => $model->getId(),
            ], $params));
        });
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

        /* Reinsert the ones we chose. Surely there was a more elegant way of doing this, but I can't remember/find one
         * specifically using joinsVia
         */
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

        /* Remove (key, value) relationship that was used for deduping, returning this as a stack
         */
        foreach ($collection as $reporting) {
            $reporting->setSupportingDataCurriculumNodes($reporting->getSupportingDataCurriculumNodes()->reindex());
        }

        return $collection;
    }


    /**
     * Helper method for hydrating work types into the collection of
     * Reporting activity's supporting data, retrieved by `hydrateSelect`.
     *
     * @param GenericCollection $collection
     * @return GenericCollection
     */
    private function hydrateSupportingDataWorkTypesIntoCollection(GenericCollection $collection): GenericCollection
    {
        $reportingIds = $collection->keys();

        $pb = $this->getPredicateBuilder()->in('id', $reportingIds);

        $query = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('reporting_work_type', 'lwt')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN reporting_work_type rwt ON reporting.id = rwt.reporting_id
            INNER JOIN lu_work_type lwt ON rwt.work_type_id = lwt.id AND lwt.deleted_at IS NULL
            WHERE {$pb->getWhereIntersect()}
        ";

        $workTypeStatement = $this->database->query($query, $pb->getParams());

        foreach ($workTypeStatement as $row) {
            /** @var \Schoolbox\Learning\Assessment\WorkType\Model $workType */
            $workType = $this->hydrateJoin($row, 'reporting_work_type', 'lwt');
            $collection[$row['id']]->getSupportingDataWorkTypes()->setOffset($workType->getId(), $workType);
        }

        /* Remove (key, value) relationship that was used for deduping, returning this as a stack
         */
        foreach ($collection as $reporting) {
            $reporting->setSupportingDataWorkTypes($reporting->getSupportingDataWorkTypes()->reindex());
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
