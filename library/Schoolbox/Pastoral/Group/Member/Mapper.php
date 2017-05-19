<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Group\Member;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Pastoral\Group\Model as Group;
use Schoolbox\User;
use Schoolbox\UserMapper;

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
        $this->joins('group_id', GroupMapper::class, 'id', null, null, 'group', 'group');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');            
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return
            "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('user', 'u')},
                        {$this->getJoinColumnString('author', 'a')},
                        {$this->getJoinColumnString('group', 'g')}
            FROM        {$this->getTable()} 
            LEFT JOIN   {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN   {$this->getJoinSql('author', '', 'a')}
            LEFT JOIN   {$this->getJoinSql('group', '', 'g')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return pastoral records fully hydrated by default

        $collection = new Collection();

        foreach ($statement as $row) {

            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User $user */
            $user = $this->hydrateJoin($row, 'user', 'u');
            $model->setUser($user);

            /** @var User $author */
            $author = $this->hydrateJoin($row, 'author', 'a');
            $model->setAuthor($author);

            /** @var Group $group */
            $group = $this->hydrateJoin($row, 'group', 'g');
            $model->setGroup($group);

            $collection->add($model);
        }

        return $collection;
    }    
}