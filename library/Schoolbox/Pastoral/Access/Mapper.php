<?php

/**
 * Pastoral care access control
 *
 * This allows the user to audience a pastoral care record to individual users, roles and user defined pastoral groups
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Access;

use PDO;
use PDOStatement;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Pastoral\Group\Mapper as GroupMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 *
 * @package Schoolbox\Pastoral\Access
 *
 * @method Model createModel($idValue = null)
 */
class Mapper extends BaseMapper
{
    // Define record access levels

    const ACCESS_DENY = 0;                      // Deny trumps all other access to allow exclusion of a user/group/role
    const ACCESS_PUBLIC = 1;
    const ACCESS_PRIVATE = 2;

    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('pastoral_access');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('access', self::TYPE_INTEGER);
        $this->joins('pastoral_id', PastoralMapper::class, 'id', null, null, 'pastoral', 'pastoral');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('role_id', RoleMapper::class, 'id', null, null, 'role', 'role');
        $this->uses(
            'role_type',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            'roleType',
            [Role::GUEST, Role::STUDENT, Role::PARENT, Role::STAFF, null],
        );
        $this->joins('group_id', GroupMapper::class, 'id', null, null, 'group', 'group');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * Return the first part of the string required to select from this mapper
     *
     * @throws BaseMapper\DefinitionException
     * @return string
     */
    protected function getSelectString()
    {
        // Intentionally does not join onto soft-deleted strong entities
        // (e.g. An access is for a User, and user is soft-deleted)

        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('role', 'r')},
                        {$this->getJoinColumnString('user', 'u')},
                        {$this->getJoinColumnString('group', 'g')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('role', '', 'r')}
            LEFT JOIN   {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN   {$this->getJoinSql('group', '', 'g')}
        ";
    }

    /**
     * @param PDOStatement $statement
     *
     * @throws Exception
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $results = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            // The record must have a user, role, role type or group specified

            if (empty($row['u.id']) && empty($row['r.id']) && empty($row['g.id']) && empty($row['role_type'])) {
                continue;
            }

            $access = $this->hydrate($row);

            // Hydrate user

            if (!empty($row['u.id'])) {
                /** @var User $user */
                $user = $this->hydrateJoin($row, 'user', 'u');
                $user->setRole = $this->getJoinMapper('role')->findByIdWithFallback($user->getRole()->getId());
                $access->setUser($user);
            }

            // Hydrate role

            if (!empty($row['r.id'])) {
                $access->setRole($this->hydrateJoin($row, 'role', 'r'));
            }

            // Hydrate group

            if (!empty($row['g.id'])) {
                $access->setGroup($this->hydrateJoin($row, 'group', 'g'));
            }

            $results->add($access);
        }

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }

    /**
     * @return GenericCollection<Role>
     */
    public function determineAllowedRoles(): GenericCollection
    {
        $allowedRoleTypes = [Role::STAFF];
        $roleMapper = RoleMapper::getInstance();
        return $roleMapper->findByColumn('group_type', $allowedRoleTypes)->indexBy('id');
    }

    /**
     * @return array<string>
     */
    public function determineAllowedRoleTypes(): array
    {
        $allowedRoleTypes = [];
        if ($this->config_loader->get(ConfigMapper::NAME_PASTORAL_STUDENT_ACCESS_ENABLED)) {
            $allowedRoleTypes[] = Role::STUDENT;
        }
        if ($this->config_loader->get(ConfigMapper::NAME_PASTORAL_PARENT_ACCESS_ENABLED)) {
            $allowedRoleTypes[] = Role::PARENT;
        }
        return $allowedRoleTypes;
    }
}
