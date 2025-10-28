<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response;

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
        $this->hasTable('dialog_agent_response');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('dialog_agent_id', self::TYPE_INTEGER, null, 'dialogAgent');
        $this->uses('learner_id', self::TYPE_INTEGER, null, 'learner');
        $this->uses('authoritative', self::TYPE_BOOLEAN, null, 'authoritative');
        $this->uses('session_id', self::TYPE_STRING, null, 'sessionId');
        $this->uses('assistance_requested_at', self::TYPE_DATETIME, null, 'assistanceRequestedAt');
        $this->uses('submitted_at', self::TYPE_DATETIME, null, 'submittedAt');
        $this->uses('submitted_by', self::TYPE_INTEGER, null, 'submittedBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->joins('created_by', self::TYPE_INTEGER, null, 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', self::TYPE_INTEGER, null, 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('deleted_by', self::TYPE_INTEGER, null, 'deletedBy');

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
            $submittedBy = $this->hydrateJoin($row, 'learner', 'learner');

            if (null !== $submittedBy?->getId()) {
                $response->setLearner($submittedBy);
            }

            $stack->add($response);
        }

        return $stack;
    }
}
