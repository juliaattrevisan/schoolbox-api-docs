<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Criteria;

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
        $this->hasTable('dialog_agent_scenario_criteria');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('scenario_id', self::TYPE_INTEGER, null, 'scenario');
        $this->uses('title', self::TYPE_STRING, null, 'title');
        $this->uses('prompt', self::TYPE_STRING, null, 'prompt');
        $this->uses('sequence', self::TYPE_INTEGER, null, 'sequence');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins(
            'scenario_id',
            \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Scenario\Mapper::class,
            'id',
            null,
            null,
            'scenario',
            'scenario',
        );
    }
}
