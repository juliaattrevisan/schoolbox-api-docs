<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Group;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as GroupMemberMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_group');
        $this->hasModel(Model::class);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');

        // Anyone got a shorthand version of these?

        $this->hasJoin(
            'members',
            array(
                'mapper' => GroupMemberMapper::class,
                'other_key' => 'group_id',
                'this_key' => 'id',
                'multiple' => true,
            )
        );

        $this->registerCallback(Mapper::CALLBACK_PREDELETE, [$this, 'deleteGroupMembers']);
    }

    /**
     * Prior to deleting, make sure we delete anything that is linked to this group
     *
     * @param Model $model
     */
    public function deleteGroupMembers(Model $model) {

        // Delete members from group

        $this->getJoinMapper('members')->deleteByColumn('group_id', $model->getId());
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return
            "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('user', 'u')}
            FROM        {$this->getTable()} 
            LEFT JOIN   {$this->getJoinSql('user', '', 'u')}
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

            $model->setMembers($this->getJoinMapper('members')->findByColumn('group_id', $model->getId()));

            $collection->add($model);
        }

        return $collection;
    }
}