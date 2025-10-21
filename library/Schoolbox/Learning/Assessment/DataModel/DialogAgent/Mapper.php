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
        $this->uses('helpfulness', self::TYPE_STRING, null, 'helpfulness');
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

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'auditorFields']);
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
}
