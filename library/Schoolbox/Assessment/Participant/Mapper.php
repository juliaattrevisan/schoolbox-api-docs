<?php

/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Participant;

use PDO;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\Task\CompletionMapper;
use Schoolbox\Assessment\Participant\Plan\Mapper as PlanMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\LTI\Assessment\Model as LTIAssessment;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Participant\Plan;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($id = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends BaseMapper
{
    public function mapping(): void
    {
        $this->hasTable('participant');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('participant_plan_id', Plan\Mapper::class, 'id', null, null, 'plan', 'plan');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('excluded_at', self::TYPE_DATETIME);
    }


    /**
     * Returns the participant model for the specified assessment and user, NULL if not exists
     *
     * @param int $assessmentId
     * @param int $userId
     * @return Model|null
     * @throws BaseMapper\Exception
     */
    public function findForAssessmentAndUser(int $assessmentId, int $userId): ?Model
    {
        return $this->findByColumnList([
            'pp.assessment_id' => $assessmentId,
            'user_id'          => $userId,
        ])->first();
    }


    /**
     * By default, hydrate the participants' plans too.
     *
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('plan', 'pp')}
            FROM   {$this->getTable()}
            JOIN   {$this->getJoinSql('plan', '', 'pp')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Model[]|GenericCollection
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var Plan\Mapper $planMapper */
        $planMapper = $this->getJoinMapper('plan');

        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $participant = $this->hydrate($row);
            $participant->setPlan($planMapper->hydrate($row, 'pp'));
            $collection[$participant->getId()] = $participant;
        }

        return $collection;
    }


    /**
     * Soft deletes all responses and feedbacks for the specified participant
     *
     * @param Model                                          $participant
     * @param \Schoolbox\Learning\Assessment\DataModel\Model $assessment
     */
    public function softDeleteResponsesAndFeedbacks(Model $participant, \Schoolbox\Learning\Assessment\DataModel\Model $assessment): void
    {
        if ($participant->getPlan()->getAssessment()->getId() !== $assessment->getId()) {
            // should not happen
            return;
        }
        if ($assessment->getDueWork() instanceof SubmissionBox) {
            // delete submission return
            $returnMapper = SubmissionBox\ReturnedWorkMapper::getInstance();
            $returnMapper->suppressNotification();
            $returnMapper->deleteByColumnList([
                'box_id' => $assessment->getDueWork()->getId(),
                'owner'  => $participant->getUser()->getId(),
            ]);
            $returnMapper->restoreNotification();

            // delete submission
            $submissionMapper = SubmissionBox\Submission\SubmissionMapper::getInstance();
            $submissionMapper->suppressNotification();
            $submissionMapper->deleteByColumnList([
                'box_id' => $assessment->getDueWork()->getId(),
                'owner'  => $participant->getUser()->getId(),
            ]);
            $submissionMapper->restoreNotification();
        } elseif ($assessment->getTask() instanceof Task) {
            // delete completion
            $taskCompletionMapper = CompletionMapper::getInstance();
            $taskCompletionMapper->suppressNotification();
            $taskCompletionMapper->deleteByColumnList([
                    'task_id'    => $assessment->getTask()->getId(),
                    'student_id' => $participant->getUser()->getId(),
                ]);
            $taskCompletionMapper->restoreNotification();
        } elseif ($assessment->getQuiz() instanceof Quiz) {
            // delete attempt
            $attemptMapper = \Schoolbox\Learning\Quiz\Attempt\Mapper::getInstance();
            $attemptMapper->suppressNotification();
            $attemptMapper->deleteByColumnList([
                    'assess_id' => $assessment->getQuiz()->getId(),
                    'user_id'   => $participant->getUser()->getId(),
                ]);
            $attemptMapper->restoreNotification();
        } elseif ($assessment->getLtiAssessment() instanceof LTIAssessment) {
            // delete score
            $ltiScoreMapper = \Schoolbox\LTI\Assessment\Score\Mapper::getInstance();
            $ltiScoreMapper->deleteByColumnList([
                    'lti_assessment_id' => $assessment->getLtiAssessment()->getId(),
                    'student_id'        => $participant->getUser()->getId(),
                ]);

            /* TODO SURELY you're meant to suppress notifs, do an action, then restore notifs...
             *  what's the point of suppressing then immediately restoring?
             */
            $ltiScoreMapper->suppressNotification();
            $ltiScoreMapper->restoreNotification();
        } elseif ($assessment->getDialogAgent() instanceof \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model) {
            /* Soft-delete all responses made by a student
             */
            $dialogAgentResponseMapper = \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Mapper::getInstance();

            /* Suppress and re-enable notifications so people aren't bombarded during the deletion
             */
            $dialogAgentResponseMapper->suppressNotification();

            $dialogAgentResponseMapper->deleteByColumnList([
                'dialog_agent_id' => $assessment->getDialogAgent()->getId(),
                'learner_id' => $participant->getUser()->getId(),
            ]);

            $dialogAgentResponseMapper->restoreNotification();

            /* Soft-delete all feedbacks given to a student
             */
            $dialogAgentFeedbackMapper = \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper::getInstance();

            /* Suppress and re-enable notifications so people aren't bombarded during the deletion
             */
            $dialogAgentFeedbackMapper->suppressNotification();

            $dialogAgentFeedbackMapper->deleteByColumnList([
                'dialog_agent_id' => $assessment->getDialogAgent()->getId(),
                'learner_id' => $participant->getUser()->getId(),
            ]);

            $dialogAgentFeedbackMapper->restoreNotification();
        }
    }


    /**
     * Restore soft-deleted responses and feedbacks
     * @param Model                                          $participant
     * @param \Schoolbox\Learning\Assessment\DataModel\Model $assessment
     * @return int a count of restored data
     */
    public function restoreResponsesAndFeedbacks(Model $participant, \Schoolbox\Learning\Assessment\DataModel\Model $assessment): int
    {
        if ($participant->getPlan()->getAssessment()->getId() !== $assessment->getId()) {
            // should not happen
            return 0;
        }
        $excludedAt = $participant->getExcludedAt();

        if ($assessment->getDueWork() instanceof SubmissionBox) {
            // restore submission
            $submissionMapper = SubmissionBox\Submission\SubmissionMapper::getInstance();
            $submissionMapper->suppressNotification();
            $params = [
                'box_id' => $assessment->getDueWork()->getId(),
                'owner'  => $participant->getUser()->getId(),
            ];
            if ($excludedAt) {
                $params['deleted_at'] = $excludedAt;
            }
            $submissions = $submissionMapper->findByColumnList($params, ['deleted' => true]);
            $submissionMapper->restoreSoftDeleted($submissions);
            $submissionMapper->restoreNotification();

            // restore submission return
            $returnMapper = SubmissionBox\ReturnedWorkMapper::getInstance();
            $returnMapper->suppressNotification();
            $returns = $returnMapper->findByColumnList($params, ['deleted' => true]);
            $returnMapper->restoreSoftDeleted($returns);
            $returnMapper->restoreNotification();

            return $submissions->count() + $returns->count();
        } elseif ($assessment->getTask() instanceof Task) {
            // restore completion
            $taskCompletionMapper = CompletionMapper::getInstance();
            $taskCompletionMapper->suppressNotification();
            $params = [
                'task_id'    => $assessment->getTask()->getId(),
                'student_id' => $participant->getUser()->getId(),
            ];
            if ($excludedAt) {
                $params['deleted_at'] = $excludedAt;
            }
            $completion = $taskCompletionMapper->findByColumnList($params, ['deleted' => true]);
            $taskCompletionMapper->restoreSoftDeleted($completion);
            $taskCompletionMapper->restoreNotification();

            return $completion->count();
        } elseif ($assessment->getQuiz() instanceof Quiz) {
            $attemptMapper = \Schoolbox\Learning\Quiz\Attempt\Mapper::getInstance();
            $attemptMapper->suppressNotification();
            $params = [
                'assess_id' => $assessment->getQuiz()->getId(),
                'user_id'   => $participant->getUser()->getId(),
            ];
            if ($excludedAt) {
                $params['deleted_at'] = $excludedAt;
            }
            $attempts = $attemptMapper->findByColumnList($params, ['deleted' => true]);
            $attemptMapper->restoreSoftDeleted($attempts);
            $attemptMapper->restoreNotification();

            return $attempts->count();
        } elseif ($assessment->getLtiAssessment() instanceof LTIAssessment) {
            // delete score
            $ltiScoreMapper = \Schoolbox\LTI\Assessment\Score\Mapper::getInstance();
            $ltiScoreMapper->suppressNotification();
            $params = [
                'lti_assessment_id' => $assessment->getLtiAssessment()->getId(),
                'student_id'        => $participant->getUser()->getId(),
            ];
            $scores = $ltiScoreMapper->findByColumnList($params, ['deleted' => true]);
            $ltiScoreMapper->restoreSoftDeleted($scores);
            $ltiScoreMapper->restoreNotification();

            /* TODO SURELY this was meant to return a value here? That would have enforced "should not get here, but"
             *  but there must have been a reason for its omission.
             *
             *      return $ltiScoreMapper->count();
             */
        } elseif ($assessment->getDialogAgent() instanceof \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model) {
            /* If this assessment's subobject is a Dialog Agent, restore responses and feedbacks with corresponding
             * data model mappers via their restoreSoftDeleted() methods which implicitly executes all PRE and POSTRESTORE
             * callback methods by default via the base mapper.
             */

            /* Find all soft deleted responses and restore them to an active state
             * However, this won't change any authoritative response settings, and you'll still have to micromanage
             * all the stuff you intentionally deleted. You'll have to delete those all over again anyways.
             */
            $dialogAgentResponseMapper = \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Mapper::getInstance();

            $responses = $dialogAgentResponseMapper->findByColumnList([
                'dialog_agent_id' => $assessment->getDialogAgent()->getId(),
                'learner_id' => $participant->getUser()->getId(),
            ], [
                /* Obviously need to include soft-deleted items
                 */
                'deleted' => true
            ]);

            /* Suppress and re-enable notifications so people aren't bombarded during the Restoration
             */
            $dialogAgentResponseMapper->suppressNotification();
            $dialogAgentResponseMapper->restoreSoftDeleted($responses);
            $dialogAgentResponseMapper->restoreNotification();

            /* Find all soft deleted feedbacks and restore them to an active state
             */
            $dialogAgentFeedbackMapper = \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper::getInstance();

            $feedbacks = $dialogAgentFeedbackMapper->findByColumnList([
                'dialog_agent_id' => $assessment->getDialogAgent()->getId(),
                'learner_id' => $participant->getUser()->getId(),
            ], [
                /* Obviously need to include soft-deleted items
                 */
                'deleted' => true
            ]);

            /* Suppress and re-enable notifications so people aren't bombarded during the Restoration
             */
            $dialogAgentFeedbackMapper->suppressNotification();
            $dialogAgentFeedbackMapper->restoreSoftDeleted($feedbacks);
            $dialogAgentFeedbackMapper->restoreNotification();

            /* Return a count of total affected rows from both tables, assuming everything went according to plan
             */
            return $responses->count() + $feedbacks->count();
        }

        // should not get here, but.
        return 0;
    }


    /**
     * Find a list of participant_plan_id that were assigned for the given assessment
     *
     * Users are only meant to have one participation plan, at most.
     * (Having multiple plans is an error state)
     *
     * @param int $assessmentId
     * @return array
     * @throws \PDOException
     */
    public function findAssignedPlans(int $assessmentId): array
    {
        /* Find Plans associated with an assessment
         *
         * Multiplayer schoolbox allows $plans to be different upon saving
         * so you can't use it as a source of truth - $plans ids in one tab
         * is different to $plans ids in another tab, allowing a misunderstanding
         * to occur that inadvertently lets a person have multiple plans
         */
        $query = "
            SELECT participant_plan_id
            FROM {$this->getTable()} p
            INNER JOIN participant_plan pp ON p.participant_plan_id = pp.id
            WHERE assessment_id = :assessmentId
            AND pp.deleted_at IS NULL
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'assessmentId' => $assessmentId,
        ]);

        $planIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $planIds[] = $row['participant_plan_id'];
        }

        return $planIds;
    }

    /**
     * Finds all customisation records (participants with static or custom participation plans)
     *
     * @return GenericCollection
     * @throws Exception
     */
    public function findAllCustomisationRecords(): GenericCollection
    {
        $query = "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('plan', 'pp')},
                   {$this->getJoinColumnString('user', 'u')},
                   {$this->getJoinMapper('plan')->getJoinColumnString('assessment', 'a')},
                   {$this->getJoinMapper('plan')->getJoinColumnString('assessment', 'oa')},
                   {$this->getJoinMapper('plan.assessment')->getJoinColumnString('assessment_course_unit_implementation', 'cui')},
                   {$this->getJoinMapper('plan.assessment.assessment_course_unit_implementation')->getJoinColumnString('course', 'ci')},
                   {$this->getJoinMapper('plan.assessment.assessment_course_unit_implementation')->getJoinMapper('course')->getJoinColumnString('folder', 'f')}
            FROM   {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('plan', '', 'pp')}
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN assessment a ON pp.assessment_id = a.id AND a.deleted_at IS NULL
            LEFT JOIN assessment_course_unit_implementation ui ON a.id = ui.assessment_id
            LEFT JOIN course_unit_implementation cui ON ui.course_unit_implementation_id = cui.id
            LEFT JOIN course_implementation ci ON cui.course_implementation_id = ci.id
            LEFT JOIN folder f ON ci.folder_id = f.id
            LEFT JOIN assessment oa ON a.original_assessment_id = oa.id
            WHERE (pp.is_dynamic = :static_plan OR pp.is_dynamic = :custom_participation)
              AND pp.deleted_at IS NULL
        ";


        $statement = $this->getDatabase()->executeQuery($query, [
            'static_plan' => PlanMapper::PLAN_TYPE_STATIC,
            'custom_participation' => PlanMapper::PLAN_TYPE_CUSTOM_PARTICIPATION,
        ]);
        $collection = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $participant = $this->hydrate($row);
            $planMapper = $this->getJoinMapper('plan');
            $assessmentMapper = $planMapper->getJoinMapper('assessment');
            /* Hydrate plan with the assessment details too
             */
            $plan = $planMapper->hydrate($row, 'pp');
            /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessment */
            $assessment = $assessmentMapper->hydrate($row, 'a');
            if ($assessment->getOriginalAssessmentId() !== null) {
                $assessment->setOriginalAssessment(
                    $assessmentMapper->hydrate($row, 'oa')
                );
            }
            /* Hydrating the unit implementation and course implementation for the assessment
             */
            /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model $assessmentContext */
            $assessmentContext = $assessmentMapper->hydrateJoin($row, 'assessment_course_unit_implementation', 'cui');
            /** @var \Schoolbox\Learning\Course\DataModel\Implementation\Model $courseImplementation */
            $courseImplementation = $assessmentMapper->hydrateJoin($row, 'assessment_course_unit_implementation.course', 'ci');
            if ($courseImplementation !== null) {
                $folder = $assessmentMapper->hydrateJoin($row, 'assessment_course_unit_implementation.course.folder', 'f');
                /* Hydrating folder for class details
                 */
                $courseImplementation->setFolder($folder);
                $assessmentContext->setCourse($courseImplementation);
            }
            $assessment->setAssessmentContext($assessmentContext);
            $plan->setAssessment($assessment);
            /* Hydrate user
             */
            $user = $this->getJoinMapper('user')->hydrate($row, 'u');

            $participant->setPlan($plan);
            $participant->setUser($user);
            $collection[$participant->getId()] = $participant;
        }

        return $collection;
    }

    /**
     * Any notifications related to assessment participations must be deferred
     * until all participations are saved.
     *
     * @param object      $object
     * @param string      $verb
     * @param object|null $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
