<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback;

use Schoolbox\Assessment\Participant;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

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
     * @var Participant\Plan\InteractionIntegrityChecker
     */
    private Participant\Plan\InteractionIntegrityChecker $interactionChecker;


    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('reporting_feedback');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('norm_mark', self::TYPE_DECIMAL, null, 'normalisedMark');
        $this->uses('mark', self::TYPE_STRING, null, 'humanReadableMark');
        $this->uses('instructor_mark', self::TYPE_STRING, null, 'humanReadableInstructorMark');
        $this->uses('comment', self::TYPE_STRING);

        $this->joins(
            'reporting_id',
            \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper::class,
            'id',
            null,
            null,
            'reporting',
            'reporting'
        );

        $this->joins(
            'learner_id',
            UserMapper::class,
            'id',
            null,
            null,
            'learner',
            'learner'
        );

        $this->joins(
            'instructor_id',
            UserMapper::class,
            'id',
            null,
            null,
            'instructor',
            'instructor'
        );


        /* Auditor fields
         */
        $this->uses('published_at', self::TYPE_DATETIME);
        $this->uses('published_by', self::TYPE_INTEGER, null, 'publishedBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        /* Add participation records whenever someone creates a new object via this mapper
         *
         * Note this method typically assumes participation to exist when updating a record; this might have implications
         * for multiplayer schoolbox but we haven't encountered that yet.
         */
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'insertParticipation']);

        /* Un-inset a participation whenever all the responses made by a student are deleted; this makes them no longer
         * an official participant of this assessment. When (if) they ever make a Response, insertParticipation will
         * once again restore their participation status.
         */
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeParticipationIfNecessary']);
    }


    /**
     * Adds some dependencies arbitrarily
     */
    public function __construct()
    {
        parent::__construct();

        $this->interactionChecker = new Participant\Plan\InteractionIntegrityChecker(
            Participant\Plan\Mapper::getInstance(),
            Participant\Mapper::getInstance()
        );
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
                {$this->getJoinColumnString('reporting', 'reporting')},
                {$this->getJoinColumnString('learner', 'learner')},
                {$this->getJoinColumnString('instructor', 'instructor')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('reporting', '', 'reporting', true)}
            INNER JOIN {$this->getJoinSql('learner', '', 'learner', true)}
            INNER JOIN {$this->getJoinSql('instructor', '', 'instructor', true)}
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

        foreach ($statement as $row) {
            /** @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Model $feedback */
            $feedback = $stack->offsetGet($row['id']);

            /* First time we've encountered this $feedback; init an instance that we'll reuse later
             */
            if (null === $feedback) {
                /** @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Model $feedback */
                $feedback = $this->hydrate($row);

                /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessmentModel */
                $assessmentModel = $this->createJoinModel('reporting.assessmentModel', $row['reporting.assessment_id']);

                /** @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Model $reporting */
                $reporting = $this->createJoinModel('reporting', $row['reporting.id']);
                $reporting->setAssessmentModel($assessmentModel);

                $feedback
                    ->setReporting($reporting)
                    ->setLearner($this->hydrateJoin($row, 'learner', 'learner'))
                    ->setInstructor($this->hydrateJoin($row, 'instructor', 'instructor'));

                $stack->setOffset($feedback->getId(), $feedback);
            }
        }

        return $stack;
    }


    /* Callback implementations
     */

    /**
     * Add a participation record for a given $learner if a response is being created
     *
     * Updates to existing responses _presume_ a participation record already existed (created earlier) which maybe has
     * some implications for multiplayer schoolbox in a timing scenario
     *
     * @param Model $object
     * @param Model|null $previous
     * @return bool
     * @throws Exception
     */
    public function insertParticipation(Model $object, ?Model $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the participation record already exists
            return true;
        }

        $assessment = $this->getJoinMapper('reporting')
            ->findById($object->getReporting()->getId())
            ->getAssessmentModel();

        return $this->interactionChecker->insertParticipationOnFirstInteraction($object->getLearner(), $assessment);
    }


    /**
     * Remove the $learner's participant row if $learner should no longer be a participant in $assessment.
     *
     * @param Model $object
     * @return bool - Return true on success - whether or not a participation was removed.
     *                This is the expected behaviour for callbacks registered with Schoolbox\Mapper.
     */
    public function removeParticipationIfNecessary(Model $object): bool
    {
        /* See how many living feedbacks exist for this learner; if they have none remaining then we can remove their
         * participation record entirely
         */
        $query = "
            SELECT COUNT(*) AS num
            FROM {$this->getTable()}
            WHERE learner_id = :learner_id
            AND deleted_at IS NULL
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'learner_id' => $object->getLearner()->getId()
        ]);

        $numFeedbacks = (int)$statement->fetchColumn();

        /* No living feedbacks remain: student is "no longer participating" for the time being
         */
        if (!$numFeedbacks) {
            $this->interactionChecker->removeParticipationFromDynamicPlan($object->getLearner(), $object->getReporting()->getAssessmentModel());
        }

        return true;
    }
}
