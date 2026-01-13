<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DialogAgent\Lookup\Helpfulness;
use Schoolbox\Mapper\DefinitionException;
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
        $this->hasTable('dialog_agent');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('scenario_id', self::TYPE_INTEGER, null, 'scenarioId');
        $this->uses('language', self::TYPE_STRING, null, 'language');
        $this->uses('helpfulness', self::TYPE_STRING, self::COLUMN_ENUM, 'helpfulness', [...array_column(Helpfulness::cases(), 'value'), null]);
        $this->uses('use_diff_profile', self::TYPE_BOOLEAN, null, 'useDifferentiationProfile');
        $this->uses('opening_message', self::TYPE_STRING, null, 'openingMessage');
        $this->uses('rules', self::TYPE_STRING, null, 'rules');
        $this->uses('prompt', self::TYPE_STRING, null, 'prompt');
        $this->uses('duration', self::TYPE_INTEGER, null, 'duration');
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

        $this->hasJoin('responses', array(
            'multiple'  => true,
            'mapper'    => \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Mapper::class,
            'this_key'  => 'id',
            'other_key' => 'dialog_agent_id',
        ));

        /* Created/Updated/Deleted By fields need special treatment due to swampy reasons
         */
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'auditorFields']);
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'deleterFields']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreDeleterFields']);

        /* Recursive Undeleter callbacks: deleting a dialog agent also deletes all students' responses and feedbacks
         */
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteResponses']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteFeedbacks']);

        /* Recursive Undeleter callbacks: deleting a dialog agent also deletes the scenario field contexts that users
         * have lovingly crafted; these are used to make prompts so people can make responses.
         */
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCriteriaContext']);
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
            $dialogAgent = $this->hydrate($row);

            /* Bolt on the IAssessable instance
             */
            $assessment = $this->hydrateJoin($row, 'assessmentModel', 'assessmentModel');
            $dialogAgent->setAssessmentModel($assessment);

            $stack->add($dialogAgent);
        }

        return $stack;
    }


    /**
     * Prefill auditor fields (the Swamp shouldn't need to know about this)
     */
    protected function auditorFields(Model $dialogAgent): void
    {
        /* TODO Technical Uplift Candidate
         *  The traits are wrong and should have accepted a user instance rather than just an id
         *  All usages of the trait require you to ->getId() anyway, which means you have the user instance already.
         *  WHy not just supply that instead?
         */
        if (null === $dialogAgent->getCreatedBy()) {
            $dialogAgent->setCreatedBy($this->getSessionUser()->getId());
        }

        if (null === $dialogAgent->getUpdatedBy()) {
            $dialogAgent->setUpdatedBy($this->getSessionUser()->getId());
        }
    }


    /**
     * Prefill deleter fields (the Swamp shouldn't need to know about this)
     *
     * @param Model $dialogAgent
     * @return void
     * @throws Exception
     */
    protected function deleterFields(Model $dialogAgent): void
    {
        /* Remember who deleted this thing, in a dirty doubletap
         */
        $dialogAgent->setDeletedBy($this->getSessionUser()->getId());
        $this->save($dialogAgent);

        /* TODO remove scenario context stuff specifically related to this instance
         */
    }


    /**
     * Forget who deleted this. Used when undeleting
     *
     * @param Model $dialogAgent
     * @return void
     * @throws Exception
     */
    public function restoreDeleterFields(Model $dialogAgent): void
    {
        /* Forget who deleted this thing, in a dirty doubletap
         */
        $dialogAgent->setDeletedBy(null);
        $this->save($dialogAgent);
    }


    /**
     * Soft deletes all responses for this dialog agent
     *
     * @param Model $dialogAgent
     * @return void
     * @throws Exception
     */
    public function softDeleteResponses(Model $dialogAgent): void
    {
        $mapper = $this->getJoinMapper('responses');

        $mapper->suppressNotification();
        $mapper->deleteByColumn('dialog_agent_id', $dialogAgent->getId());
        $mapper->restoreNotification();
    }


    /**
     * Soft deletes all feedbacks for this dialog agent
     *
     * @param Model $dialogAgent
     * @return void
     * @throws Exception
     */
    public function softDeleteFeedbacks(Model $dialogAgent): void
    {
        $mapper = \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper::getInstance();

        $mapper->suppressNotification();
        $mapper->deleteByColumn('dialog_agent_id', $dialogAgent->getId());
        $mapper->restoreNotification();
    }


    /**
     * @param Model $dialogAgent
     * @return void
     * @throws Exception
     */
    public function softDeleteCriteriaContext(Model $dialogAgent): void
    {
        $mapper = \Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper::getInstance();

        $mapper->suppressNotification();
        $mapper->deleteByColumn('dialog_agent_id', $dialogAgent->getId());
        $mapper->restoreNotification();
    }
}
