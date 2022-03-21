<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Overrides;

use Schoolbox\GenericCollection;

/**
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method GenericCollection findByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_event_user_overrides');
        $this->hasModel(Model::class);
        $this->uses('event_id', self::TYPE_INTEGER);
        $this->uses('action', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins('external_id', \Schoolbox\UserMapper::class, 'synergy_id', null, null, 'user', 'externalId');
    }


    /**
     * @inheritDoc
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('externalId', 'u')},
                {$this->getJoinColumnString('externalId.role', 'r')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('externalId', '', 'u')}
            LEFT JOIN {$this->getJoinSql('externalId.role', 'u', 'r')}
        ";
    }


    /**
     * @inheritDoc
     *
     * @param \PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            $override = $this->hydrate($row);

            /** @var \Schoolbox\User|null $user */
            $user = $this->hydrateJoin($row, 'externalId', 'u');

            /* This corresponds to a 'clear' mutation in the overrides
             */
            if (null === $user) {
                $stack->add($override);
                continue;
            }

            /** @var \Schoolbox\Role $role */
            $role = $this->hydrateJoin($row, 'externalId.role', 'r');

            $user->setRole($role);
            $override->setUser($user);

            $stack->add($override);
        }

        return $stack;
    }
}
