<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Report\Access;

use PDOStatement;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method static self getInstance()
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{
    public const TABLE_NAME = 'pastoral_report_access';

    /**
     * @inheritdoc
     */
    protected function mapping(): void
    {
        $this->hasTable(self::TABLE_NAME);
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('pastoral_report_id', \Schoolbox\Pastoral\Report\Mapper::class, 'id', null, null, 'pastoralReport', 'pastoralReport');
        $this->uses('access', self::TYPE_INTEGER);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('group_id', GroupMapper::class, 'id', null, null, 'group', 'group');
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        return "
            SELECT    {$this->getColumnString()},
                      {$this->getJoinColumnString('user', 'u')},
                      {$this->getJoinColumnString('user.role', 'ur')},
                      {$this->getJoinColumnString('group', 'g')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function getFromString()
    {
        return "
            FROM      {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN {$this->getJoinSql('user.role', 'u', 'ur')}
            LEFT JOIN {$this->getJoinSql('group', '', 'g')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection          = $this->createCollection();
        $pastoralGroupMapper = \Schoolbox\Pastoral\Group\Mapper::getInstance();
        // Get all groups populated with members
        $pastoralGroups = $pastoralGroupMapper->findAll()->indexBy('id');
        foreach ($statement as $row) {
            /** @var Model $access */
            $access      = $this->hydrate($row);
            $accessValid = false;
            // set user
            $user = $this->hydrateJoin($row, 'user', 'u');
            if ($user instanceof User) {
                $access->setUser($user);
                $accessValid = true;
                /** @var Role $role */
                $role = $this->hydrateJoin($row, 'user.role', 'ur');
                $user->setRole($role);
            }
            // set group
            $group = $this->hydrateJoin($row, 'group', 'g');
            if ($group instanceof \Schoolbox\Pastoral\Group\Model && $pastoralGroups->offsetExists($group->getId())) {
                $access->setGroup($pastoralGroups->offsetGet($access->getGroup()->getId()));
                $accessValid = true;
            }
            // Role is not supported for Report Access
            // Do not add to collection, if both User and Group are null
            if ($accessValid) {
                $collection->add($access);
            }
        }
        return $collection;
    }

    /**
     * @inheritDoc
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return false;
    }
}
