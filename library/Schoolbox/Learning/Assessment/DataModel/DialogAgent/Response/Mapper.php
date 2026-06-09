<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response;

use Schoolbox\Assessment\Participant;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

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
     * @var Participant\Plan\InteractionIntegrityChecker
     */
    private Participant\Plan\InteractionIntegrityChecker $interactionChecker;


    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('dialog_agent_response');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('learner_id', self::TYPE_INTEGER, null, 'learner');
        $this->uses('authoritative', self::TYPE_BOOLEAN, null, 'authoritative');
        $this->uses('ai_session_id', self::TYPE_STRING, null, 'sessionId');
        $this->uses('assistance_requested_at', self::TYPE_DATETIME, null, 'assistanceRequestedAt');
        $this->uses('started_at', self::TYPE_DATETIME, null, 'startedAt');
        $this->uses('locked_at', self::TYPE_DATETIME, null, 'lockedAt');
        $this->uses('submitted_at', self::TYPE_DATETIME, null, 'submittedAt');
        $this->uses('submitted_by', self::TYPE_INTEGER, null, 'submittedBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('created_by', self::TYPE_INTEGER);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('deleted_by', self::TYPE_INTEGER);

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
            'learner_id',
            \Schoolbox\UserMapper::class,
            'id',
            null,
            null,
            'learner',
            'learner',
        );

        /* I tried exploring the possibility that this could be an OpenAI\Log\Model instance
         * but I ended up walking around in the base mapper and collection implementation
         * trying to fix this:
         *
         * "mb_convert_encoding(): Argument #1 ($string) must be of type array|string, Schoolbox\Editor\OpenAI\Log\Model given"
         *
         * I eventually had to conclude that because session_id isn't the primary key, there must be
         * some special requirement to deal with this manually. The identifier column presumably has
         * a special treatment, but in the openai log table, session_id is not the primary identifier.
         *
         * For that reason you'll have to live with $session->getSessionId() usage instead, for now,
         * and manually obtain the session's contents separately.
         *
//        $this->joins(
//            'session_id',
//            \Schoolbox\Editor\OpenAI\Log\Mapper::class,
//            'session_id',
//            null,
//            null,
//            'session',
//            'session',
//        );
         */

        $this->joins(
            'submitted_by',
            \Schoolbox\UserMapper::class,
            'id',
            null,
            null,
            'submittedBy',
            'submittedBy',
        );

//        $this->joins(
//            'created_by',
//            \Schoolbox\UserMapper::class,
//            'id',
//            null,
//            null,
//            'createdBy',
//            'createdBy',
//        );
//
//        $this->joins(
//            'updated_by',
//            \Schoolbox\UserMapper::class,
//            'id',
//            null,
//            null,
//            'updatedBy',
//            'updatedBy',
//        );
//
//        $this->joins(
//            'deleted_by',
//            \Schoolbox\UserMapper::class,
//            'id',
//            null,
//            null,
//            'deletedBy',
//            'deletedBy',
//        );

        $this->joinsReverseMulti('dialog_agent_response_id', Message\Mapper::class, 'messages', 'responseMessages');

        /* Created/Updated/Deleted By fields need special treatment due to swampy reasons
         */
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'deleterFields']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreDeleterFields']);

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

        /* Soft-delete response messages if a response is deleted
         */
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteResponseMessages']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreResponseMessages']);
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
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('dialogAgent', 'dialogAgent')},
                {$this->getJoinColumnString('learner', 'learner')},
                {$this->getJoinColumnString('submittedBy', 'submittedBy')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('dialogAgent', '', 'dialogAgent', true)}
            INNER JOIN {$this->getJoinSql('learner', '', 'learner', true)}
            LEFT JOIN {$this->getJoinSql('submittedBy', '', 'submittedBy', true)}
        ";
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
            $response = $this->hydrate($row);

            /* The dialog agent needs an assessmentModel's id to associate responses with an assessment.
             * Eventually this gets hydrated into Participant, which gives you access to each student's
             * IResponse collections in the business logic as a convenience.
             */
            /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessmentModel */
            $assessmentModel = $this->createJoinModel('dialogAgent.assessmentModel', $row['dialogAgent.assessment_id']);

            /** @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent */
            $dialogAgent = $this->createJoinModel('dialogAgent', $row['dialogAgent.id']);
            $dialogAgent->setAssessmentModel($assessmentModel);

            $response->setDialogAgent($dialogAgent)
                ->setLearner($this->hydrateJoin($row, 'learner', 'learner'));

            /* A response might not yet be submitted; in this case, a user is null
             */
            $submittedBy = $this->hydrateJoin($row, 'submittedBy', 'submittedBy');

            if (null !== $submittedBy?->getId()) {
                $response->setSubmittedBy($submittedBy);
            }

            $stack->add($response);
        }

        return $stack;
    }


    /**
     * Find then quantity of non-deleted Responses for a given student doing a particular discussion activity
     * This custom query skips unneeded hydration
     *
     * @param int $dialogAgentId
     * @param int $learnerId
     * @return int
     */
    public function findResponseCount(int $dialogAgentId, int $learnerId): int
    {
        $query = "
            SELECT COUNT(*) AS numResponses
            FROM {$this->getTable()}
            WHERE dialog_agent_id = :dialogAgentId
            AND learner_id = :learnerId
            AND deleted_at IS NULL
        ";

        $statement = $this->database->prepare($query);

        /* You could have used ->bindValue(..., \PDO::PARAM_INT) but I don't think it really matters for ids
         * It only matters when you're using LIMIT, OFFSET, etc.
         */
        $statement->execute([
            'dialogAgentId' => $dialogAgentId,
            'learnerId' => $learnerId,
        ]);

        /* At least one row existed
         */
        if ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            return (int)$row['numResponses'];
        }

        /* Found nothing
         */
        return 0;
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
     * @throws Deleted
     */
    public function insertParticipation(Model $object, ?Model $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the participation record already exists
            return true;
        }

        $assessment = $this->getJoinMapper('dialogAgent')
            ->findById($object->getDialogAgent()->getId())
            ->getAssessmentModel();

        return $this->interactionChecker->insertParticipationOnFirstInteraction($object->getLearner(), $assessment);
    }


    /**
     * Remove the $learner's participant row if $learner should no longer be a participant in $assessment.
     *
     * @param Model $object
     * @return bool - Return true on success - whether or not a participation was removed.
     *                This is the expected behaviour for callbacks registered with Schoolbox\Mapper.
     * @throws Deleted
     * @throws Exception
     */
    public function removeParticipationIfNecessary(Model $object): bool
    {
        if ($this->findResponseCount($object->getDialogAgent()->getId(), $object->getLearner()->getId())) {
            $assessment = $this->getJoinMapper('dialogAgent')
                ->findById($object->getDialogAgent()->getId())
                ->getAssessmentModel();

            $this->interactionChecker->removeParticipationFromDynamicPlan($object->getLearner(), $assessment);
        }

        return true;
    }


    /**
     * Prefill deleter fields (the Swamp shouldn't need to know about this)
     *
     * @param Model $response
     * @return void
     * @throws Exception
     */
    protected function deleterFields(Model $response): void
    {
        /* Remember who deleted this thing, in a dirty doubletap
         */
        $response->setDeletedBy($this->getSessionUser()->getId());
        $this->save($response);
    }


    /**
     * Forget who deleted this. Used when undeleting
     *
     * @param Model $response
     * @return void
     * @throws Exception
     */
    public function restoreDeleterFields(Model $response): void
    {
        /* Forget who deleted this thing, in a dirty doubletap
         */
        $response->setDeletedBy(null);
        $this->save($response);
    }


    /**
     * Soft deletes all response messages associated with this response
     *
     * @param Model $model
     * @return void
     */
    public function softDeleteResponseMessages(Model $model): void
    {
        $mapper = $this->getJoinMapper('responseMessages');

        $mapper->softDelete($mapper->findByColumn('dialog_agent_response_id', $model->getId()));
    }


    /**
     * Restore all the response messages associated with this response
     *
     * @param Model $model
     * @return void
     */
    public function restoreResponseMessages(Model $model): void
    {
        $mapper = $this->getJoinMapper('responseMessages');

        $models = $mapper->findByColumn('dialog_agent_response_id', $model->getId(), ['deleted' => true]);

        $mapper->restoreSoftDeleted($models);
    }
}
