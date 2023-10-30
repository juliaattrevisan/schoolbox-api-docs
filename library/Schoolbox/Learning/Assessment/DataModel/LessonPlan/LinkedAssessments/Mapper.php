<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Model as LessonPlanModel;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan as LessonPlanData;
use Schoolbox\Learning\Assessment\DataModel\Model as DataModelModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * Upon attempting to link a project to a lesson plan: an exception with
     * this message will be raised.
     *
     * This prohibition is in place due to the possibility of creating a cyclic
     * reference. Otherwise:
     * * projects could contain assessments, including lesson plans
     * * lesson plans have linked assessments, which could be the project above
     *
     * Attempting to load this reference currently leads to an infinite loop:
     * this might be fixable, but for now it's easier to simply prohibit the
     * behavior which causes the loop.
     */
    private const EXCEPTION_MESSAGE_ON_LINKING_PROJECT = 'Projects may not be linked to lesson plans';

    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('lesson_plan_assessment');
        $this->hasModel(Model::class);
        $this->joins(
            'lesson_plan_id',
            \Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper::class,
            'id',
            null,
            null,
            'lessonPlan',
            'lessonPlan'
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
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'assertAssessmentNotProject']);
    }

    protected function getSelectString()
    {
        return
            "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('assessmentModel', 'assessmentModel')},
                {$this->getJoinColumnString('lessonPlan', 'lessonPlan')}
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'assessmentModel', true)}
            INNER JOIN {$this->getJoinSql('lessonPlan', '', 'lessonPlan', true)}
        ";
    }

    /**
     * Assert that the linked assessment is not a project.
     *
     * @param Model $model
     * @return bool
     * @throws Exception
     */
    protected function assertAssessmentNotProject(Model $model): bool
    {
        if ($model->getAssessmentModel()->getProject()) {
            throw new Exception(self::EXCEPTION_MESSAGE_ON_LINKING_PROJECT);
        }
        return true;
    }

    /**
     * Save the links between the lesson plan and linked assessments, when using the model and appending new assessments.
     * For API @see \Schoolbox\Learning\Assessment\LessonPlan\Controller::postActivity
     *
     * @param LessonPlanModel $lessonPlan
     * @param DataModelModel $assessment
     * @return bool|int[]
     * @throws Exception
     * @throws Deleted
     */
    public function persistLink(LessonPlanData\Model $lessonPlan, DataModelModel $assessment)
    {
        // find the ID of the lesson plan
        try {
            $planLink = $this->findOneByColumnList(
                [
                    'lesson_plan_id' => $lessonPlan->getId(),
                    'assessment_id' => $assessment->getId()
                ]
            );
            return false;
        } catch (NotFound $e) {
            $planLink = $this->createModel()
                ->setLessonPlan($lessonPlan)
                ->setAssessmentModel($assessment);
            // save if not already exists in the mapper
            return $this->save($planLink);
        }
    }

    /**
     * Creates a new link between a lesson plan and an assessment.
     *
     * @param \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan
     * @param IAssessable $assessment
     * @return bool true if the link did not already exist, false if the link already exists
     * @throws Exception
     * @throws Deleted
     */
    public function saveLink(\Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan, IAssessable $assessment)
    {
        // Assert that the linked assessment is not a project
        if ($assessment instanceof Project) {
            throw new Exception(self::EXCEPTION_MESSAGE_ON_LINKING_PROJECT);
        }

        /**
         * Ensure we are creating a link between assessments within the same course
         */
        $confirmQuery = "
        SELECT  * from mv_assessment_relation lessonPlan
        LEFT JOIN mv_assessment_relation newLink on
            lessonPlan.folder_id = newLink.folder_id
        WHERE lessonPlan.assessment_id = :lessonPlanId and newLink.assessment_id = :newLinkId";
        if (0 === $this->getDatabase()->executeQuery($confirmQuery, ['lessonPlanId' => $lessonPlan->getId(), 'newLinkId' => $assessment->getId()])->rowCount()) {
            throw new NotFound("Lesson plan and Assessment are not part of the same course");
        }

        // find the ID of the lesson plan
        $query = $this->getDatabase()->executeQuery("SELECT id FROM lesson_plan where assessment_id = :id", ['id' => $lessonPlan->getId()]);
        $lessonPlanId = $query->fetchColumn();
        try {
            $planLink = $this->findOneByColumnList(
                [
                    'lesson_plan_id' => $lessonPlanId,
                    'assessment_id' => $assessment->getId()
                ]
            );
            return false;
        } catch (NotFound $e) {
            // save if not already exists in the mapper
            $this->getDatabase()->executeQuery(
                "INSERT INTO lesson_plan_assessment (lesson_plan_id, assessment_id) VALUES (:lessonPlanId, :assessmentId)",
                [
                    'lessonPlanId' => $lessonPlanId,
                    'assessmentId' => $assessment->getId()
                ]
            );
            return true;
        }
    }

    /**
     * Deletes a link between a lesson plan and an assessment
     *
     * @param \Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan
     * @param IAssessable                                     $assessment
     * @return int Returns the number of records deleted
     */
    public function deleteLink(\Schoolbox\Learning\Assessment\LessonPlan\Model $lessonPlan, IAssessable $assessment): int
    {
        // find the ID of the lesson plan
        $query = $this->getDatabase()->executeQuery("SELECT id FROM lesson_plan where assessment_id = :id", ['id' => $lessonPlan->getId()]);
        $lessonPlanId = $query->fetchColumn();
        return $this->deleteByColumnList(
            [
                'lesson_plan_id' => $lessonPlanId,
                'assessment_id' => $assessment->getId()
            ]
        );
    }
}
