<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Field\Context;

use Schoolbox\GenericCollection;

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
        $this->hasTable('dialog_agent_scenario_field_context');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('scenario_field_id', self::TYPE_INTEGER, null, 'scenarioField');
        $this->uses('dialog_agent_id', self::TYPE_INTEGER, null, 'dialogAgent');
        $this->uses('body', self::TYPE_STRING, null, 'body');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins(
            'scenario_field_id',
            \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Field\Mapper::class,
            'id',
            null,
            null,
            'scenarioField',
            'scenarioField',
        );

        $this->joins(
            'dialog_agent_id',
            \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper::class,
            'id',
            null,
            null,
            'dialogAgent',
            'dialogAgent',
        );
    }
}
