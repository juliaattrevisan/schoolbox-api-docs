<?php
/**
 * Schoolbox user role mapper
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

/**
 * @method Role hydrate(array $row, $alias = '', $callback = null)
 * @method Role findOneByColumn($columnName, $columnValue, $order = [], $options = [])
 */
class RoleMapper extends Mapper
{

    /* Enum in groups table
     */
    const ROLE_STAFF = 'staff';
    const ROLE_PARENT = 'parent';
    const ROLE_STUDENT = 'student';
    const ROLE_GUEST = 'guest';


    /**
     * Setup mapper
     */
    protected function mapping()
    {
        $this->hasTable('groups');
        $this->hasModel(Role::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('group_type', self::TYPE_STRING, null, 'type');
        $this->uses('can_change_password', self::TYPE_BOOLEAN);
        $this->uses('digest_schedule', self::TYPE_STRING);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteUsersExists']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeletePastoralAccess']);
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
     * @param string $name
     * @param array  $options
     * @return GenericCollection|Role[]
     */
    public function findByName(string $name, array $options = []): GenericCollection
    {
        $escaped = $this->getDatabase()->escapeForLike($name);
        return $this->findByPredicates($this->getPredicateBuilder()->like('name', "%{$escaped}%"), $options);
    }
}
