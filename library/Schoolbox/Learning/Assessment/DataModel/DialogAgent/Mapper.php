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
use Schoolbox\UserMapper;

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
        $this->uses('scenario_id', self::TYPE_INTEGER, null, 'scenario');
        $this->uses('language', self::TYPE_STRING, null, 'language');
        $this->uses('helpfulness', self::TYPE_STRING, null, 'helpfulness');
        $this->uses('opening_message', self::TYPE_STRING, null, 'openingMessage');
        $this->uses('rules', self::TYPE_STRING, null, 'rules');
        $this->uses('prompt', self::TYPE_STRING, null, 'prompt');
        $this->uses('duration', self::TYPE_INTEGER, null, 'duration');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->joins('created_by', UserMapper::class, 'id', null, null, 'createdBy', 'createdBy');
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('deleted_by', UserMapper::class, 'id', null, null, 'deletedBy', 'deletedBy');

        // This column is duplicate of assessment.deleted_at, but is required to ensure soft-deletion on this table.
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins(
            'assessment_id',
            AssessmentMapper::class,
            'id',
            null,
            null,
            'assessmentModel',
            'assessmentModel',
        );

//        $this->joins(
//            'scenario_id',
//            Scenario/Mapper::class,
//            'id',
//            null,
//            null,
//            'scenario',
//            'scenario',
//        );
    }
}
