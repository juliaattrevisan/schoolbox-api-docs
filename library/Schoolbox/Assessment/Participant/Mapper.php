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
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Participant\Plan;
use Alaress_Date_NullDate;

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
     * Soft deletes all Teacher Returns related to the specified participant
     *
     * @param Model $participant
     */
    public function softDeleteResponsesAndFeedbacks(Model $participant): void
    {
        $assessment = $participant->getPlan()->getAssessment();

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
                ]
            );
            $taskCompletionMapper->restoreNotification();

        } elseif ($assessment->getQuiz() instanceof Quiz) {

            // delete attempt
            $attemptMapper = \Schoolbox\Learning\Quiz\Attempt\Mapper::getInstance();
            $attemptMapper->suppressNotification();
            $attemptMapper->deleteByColumnList([
                    'assess_id' => $assessment->getQuiz()->getId(),
                    'user_id'   => $participant->getUser()->getId(),
                ]
            );
            $attemptMapper->restoreNotification();
        }
    }


    /**
     * Restore soft-deleted returns and feedback
     * @param Model $participant
     */
    public function restoreReturnsAndFeedback(Model $participant): void
    {
        $assessment = $participant->getPlan()->getAssessment();
        $excludedAt = $participant->getExcludedAt() instanceof Alaress_Date_NullDate ? null : $participant->getExcludedAt();

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
        }
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
