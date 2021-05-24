<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Group;

use PDOStatement;
use Schoolbox\Pastoral;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Pastoral\Group\Member as GroupMember;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
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
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joinsReverseMulti('group_id', GroupMember\Mapper::class, 'members', 'members');

        $this->registerCallback(Mapper::CALLBACK_PRESOFTDELETE, [$this, 'deleteGroupMembers']);

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralAccess']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralAudiences']);
    }

    /**
     * Prior to deleting, make sure we delete anything that is linked to this group
     *
     * @param Model $model
     */
    public function deleteGroupMembers(Model $model)
    {
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
            INNER JOIN  {$this->getJoinSql('user', '', 'u', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // Return pastoral records fully hydrated by default

        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var User $user */
            $user = $this->hydrateJoin($row, 'user', 'u');
            $model->setUser($user);

            $model->setMembers($this->getJoinMapper('members')->findByColumn('group_id', $model->getId()));

            $collection->add($model);
        }

        return $collection;
    }


    /**
     * Deletes any pastoral access for a PastoralGroup
     *
     * @param Model $pastoralGroup
     * @return bool
     */
    protected function deletePastoralAccess(Model $pastoralGroup): bool
    {
        Pastoral\Access\Mapper::getInstance()->deleteByColumn('group_id', $pastoralGroup->getId());
        return true;
    }


    /**
     * Deletes any pastoral audiences for a PastoralGroup
     *
     * @param Model $pastoralGroup
     * @return bool
     */
    protected function deletePastoralAudiences(Model $pastoralGroup): bool
    {
        Pastoral\Audience\Mapper::getInstance()->deleteByColumn('group_id', $pastoralGroup->getId());
        return true;
    }
}
