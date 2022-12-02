<?php

/**
 *
 * @copyright   Copyright 2022, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\TypeAction;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Pastoral\TypeAction
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_type_action');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('type_id', self::TYPE_INTEGER);
        $this->uses('subtype_id', self::TYPE_INTEGER);
        $this->joins('action_id', \Schoolbox\Pastoral\Action\Mapper::class, 'id', null, null, 'action', 'action');
        $this->uses('default', self::TYPE_BOOLEAN);
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
            $typeAction = $this->hydrate($row);

            /** @var \Schoolbox\Pastoral\Action\Model $action */
            $action = $this->hydrateJoin($row, 'action', 'a');
            $typeAction->setAction($action);
            $collection->add($typeAction);
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
