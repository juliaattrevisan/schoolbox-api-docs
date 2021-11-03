<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Group\Member;

use PDOStatement;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Group;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_group_member');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('author_id', UserMapper::class, 'id', null, null, 'author', 'author');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('group_id', Group\Mapper::class, 'id', null, null, 'group', 'group');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        $userMapper = $this->getJoinMapper('user');
        $roleMapper = $userMapper->getJoinMapper('role');
        return
            "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('user', 'u')},
                        {$roleMapper->getColumnString( 'r')},
                        {$this->getJoinColumnString('author', 'a')},
                        {$this->getJoinColumnString('group', 'g')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('user', '', 'u')}
            INNER JOIN  {$userMapper->getJoinSql('role', 'u', 'r')}
            INNER JOIN  {$this->getJoinSql('author', '', 'a', true)}
            INNER JOIN  {$this->getJoinSql('group', '', 'g', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();
        $userMapper = $this->getJoinMapper('user');
        $roleMapper = $userMapper->getJoinMapper('role');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var User $user */
            $user = $this->hydrateJoin($row, 'user', 'u');
            /** @var Role $role */
            $role = $roleMapper->hydrate($row, 'r');
            $user->setRole($role);
            $model->setUser($user);

            /** @var User $author */
            $author = $this->hydrateJoin($row, 'author', 'a');
            $model->setAuthor($author);

            /** @var Group\Model $group */
            $group = $this->hydrateJoin($row, 'group', 'g');
            $model->setGroup($group);

            $collection->add($model);
        }

        return $collection;
    }
}
