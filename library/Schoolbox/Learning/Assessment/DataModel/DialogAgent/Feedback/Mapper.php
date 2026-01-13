<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;

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
        $this->hasTable('dialog_agent_feedback');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('response_id', self::TYPE_INTEGER, null, 'response');
        $this->uses('learner_id', self::TYPE_INTEGER, null, 'learner');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins(
            'dialog_agent_id',
            \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper::class,
            'id',
            null,
            null,
            'dialogAgent',
            'dialogAgent',
        );

        $this->joins(
            'response_id',
            \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Mapper::class,
            'id',
            null,
            null,
            'response',
            'response',
        );

        $this->joins(
            'learner_id',
            \Schoolbox\UserMapper::class,
            'id',
            null,
            null,
            'learner',
            'learner',
        );

        /* A set of many criterion (criteria) for a feedback.
         * These are shown as content for individual cells in the status screen
         */
        $this->joinsReverseMulti('feedback_id', Criterion\Mapper::class, 'criteria', 'feedbackCriterion');

        /* Soft-delete feedback criteria if a feedback is deleted
         */
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteFeedbackCriteria']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreFeedbackCriteria']);
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    protected function getSelectString()
    {
        $select = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('dialogAgent', 'dialogAgent')},
                {$this->getJoinColumnString('response', 'response')},
                {$this->getJoinColumnString('response.learner', 'response.learner')},
                {$this->getJoinColumnString('feedbackCriterion', 'feedbackCriterion')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('feedbackCriterion', '', 'feedbackCriterion', true)}
            INNER JOIN {$this->getJoinSql('dialogAgent', '', 'dialogAgent', true)}
            INNER JOIN {$this->getJoinSql('response', '', 'response', true)}
            INNER JOIN {$this->getJoinSql('response.learner', 'response', 'response.learner', true)}
        ";

        return $select;
    }


    /**
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws Exception
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $stack = $this->createCollection();
        $userMapper = $this->getJoinMapper('response.learner');
        $responseMapper = $this->getJoinMapper('response');
        $feedbackCriterionMapper = $this->getJoinMapper('feedbackCriterion');

        foreach ($statement as $row) {
            /** @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model $feedback */
            $feedback = $stack->offsetGet($row['id']);

            /* First time we've encountered this $feedback; init an instance that we'll reuse later
             */
            if (null === $feedback) {
                /** @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model $feedback */
                $feedback = $this->hydrate($row);

                /* The dialog agent needs an assessmentModel's id to associate responses with an assessment.
                 * Eventually this gets hydrated into Participant, which gives you access to each student's
                 * IResponse collections in the business logic as a convenience.
                 */
                /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessmentModel */
                $assessmentModel = $this->createJoinModel('dialogAgent.assessmentModel', $row['dialogAgent.assessment_id']);

                /** @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent */
                $dialogAgent = $this->createJoinModel('dialogAgent', $row['dialogAgent.id']);
                $dialogAgent->setAssessmentModel($assessmentModel);

                $feedback->setDialogAgent($dialogAgent);

                /* Hydrate a corresponding Response instance
                 */
                /** @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model|null $response */
                $response = $responseMapper->hydrate($row, 'response');

                if ($response) {
                    /* Assign learner instance (wild! I don't think we have ever done it this way before)
                     */
                    /** @var \Schoolbox\User|null $learner */
                    $learner = $userMapper->hydrate($row, 'response.learner');

                    if ($learner) {
                        $response->setLearner($learner);
                        $feedback->setLearner($learner);
                    }

                    $feedback->setResponse($response);
                }

                /* Init a collection of Feedback Criteria
                 */
                $feedback->setCriteria($feedbackCriterionMapper->createCollection());

                $stack->setOffset($feedback->getId(), $feedback);
            }

            /* Accumulate all the feedback criteria into a collection
             */
            $feedbackCriterion = $feedbackCriterionMapper->hydrate($row, 'feedbackCriterion');

            $feedback->getCriteria()->add($feedbackCriterion);
        }

        return $stack;
    }

    /* Callbacks
     */
    /**
     * Soft deletes all feedback criterions/criteria associated with this feedback
     *
     * @param Model $model
     * @return void
     */
    public function softDeleteFeedbackCriteria(Model $model): void
    {
        $mapper = $this->getJoinMapper('feedbackCriterion');

        $mapper->softDelete($mapper->findByColumn('feedback_id', $model->getId()));
    }


    /**
     * Restore all the criteria used to spawn feedbacks. Wild
     *
     * @param Model $model
     * @return void
     */
    public function restoreFeedbackCriteria(Model $model): void
    {
        $mapper = $this->getJoinMapper('feedbackCriterion');

        $models = $mapper->findByColumn('feedback_id', $model->getId(), ['deleted' => true]);

        $mapper->restoreSoftDeleted($models);
    }
}
