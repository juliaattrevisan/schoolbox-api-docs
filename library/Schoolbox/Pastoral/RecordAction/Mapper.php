<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\RecordAction;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @method Model findById($id)
 * @method Model hydrate($row)
 * @package Schoolbox\Pastoral\RecordAction
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_record_action');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('external_id', self::TYPE_STRING);
        $this->uses('completed', self::TYPE_BOOLEAN);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('action_id', \Schoolbox\Pastoral\Action\Mapper::class, 'id', null, null, 'action', 'action');
        $this->joins('pastoral_id', \Schoolbox\Pastoral\Mapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('action', 'a')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString()
    {
        return "
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('action', '', 'a')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $reportAction = $this->hydrate($row);

            /** @var \Schoolbox\Pastoral\Action\Model $action */
            $action = $this->hydrateJoin($row, 'action', 'a');
            $reportAction->setAction($action);
            $collection->add($reportAction);
        }

        return $collection;
    }

    /**
     * Sort by action sequence
     * @inheritdoc
     */
    protected function getDefaultOrderByParams(): array
    {
        return ['a.sequence' => 'ASC'];
    }
}
