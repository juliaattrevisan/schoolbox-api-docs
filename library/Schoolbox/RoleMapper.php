<?php
/**
 * Schoolbox user role mapper
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use InvalidArgumentException;

/**
 * @method Role createModel($idValue = null)
 * @method Role findById($identifierValue)
 * @method Role hydrate(array $row, $alias = '', $callback = null)
 * @method Role findOneByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection|Role[] findAll($options = [])
 * @method GenericCollection|Role[] createCollection($contents = [])
 */
class RoleMapper extends Mapper
{

    /**
     * Setup mapper
     *
     * @throws InvalidArgumentException
     */
    protected function mapping()
    {
        $this->hasTable('groups');
        $this->hasModel(Role::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('group_type', self::TYPE_STRING, self::COLUMN_ENUM, 'type', [Role::STAFF, Role::STUDENT, Role::PARENT, Role::GUEST,]);
        $this->uses('can_change_password', self::TYPE_BOOLEAN);
        $this->uses('digest_schedule', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteUsersExists']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeletePastoralAccess']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralAudiences']);
    }


    /**
     * Soft deleted callback to ensure the role does not have any extant users
     *
     * (FKs will take care of the hard-delete scenario)
     *
     * @param Role $role
     * @return bool
     */
    public function softDeleteUsersExists(Role $role)
    {
        $userMapper = UserMapper::getInstance();
        $pb         = $userMapper->getPredicateBuilder();
        $pb->isNull('deleted_at');
        $pb->equals('group_id', $role->getId());

        return !$userMapper->countByPredicates($pb);
    }

    /**
     * @param Role $role
     * @return bool
     */
    public function softDeletePastoralAccess(Role $role)
    {
        Pastoral\Access\Mapper::getInstance()->deleteByColumn('role_id', $role->getId());
        return true;
    }


    /**
     * Deletes any pastoral audiences for a Role
     *
     * @param Role $role
     * @return bool
     */
    protected function deletePastoralAudiences(Role $role): bool
    {
        Pastoral\Audience\Mapper::getInstance()->deleteByColumn('role_id', $role->getId());
        return true;
    }


    /**
     * @param string $name
     * @param array  $options
     * @return GenericCollection|Role[]
     */
    public function findByName(string $name, array $options = []): GenericCollection
    {
        $escaped = $this->getDatabase()->escapeForLike($name);
        return $this->findByPredicates($this->getPredicateBuilder()->like('name', "%{$escaped}%"), $options);
    }

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}
