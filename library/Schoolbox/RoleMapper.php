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
use Schoolbox\Tour\Audience\Model as TourAudience;

/**
 * @method Role createModel($idValue = null)
 * @method Role findById($identifierValue)
 * @method Role hydrate(array $row, $alias = '', $callback = null)
 * @method Role findOneByColumn($columnName, $columnValue, $order = [], $options = [])
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
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_POSTSAVE, function (Role $model, Role $previous = null) {
            $this->setTour($model, $previous);
            return true;
        });
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteUsersExists']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeletePastoralAccess']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deletePastoralAudiences']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteTour']);
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


    /**
     * Sets tours for a role, if it's a staff type role.
     * @param Role      $role
     * @param Role|null $previous
     * @return bool
     * @throws Mapper\Exception
     */
    public function setTour(Role $role, ?Role $previous): bool
    {
        if ($previous && $previous->isStaff() && !$role->isStaff()) {
            $this->deleteTour($role);
            return true;
        }
        if (!$role->isStaff()
            || ($previous && $previous->isStaff() && $role->isStaff())) {
            return true;
        }

        //TODO: who is the tour moderator?
        $moderator = UserMapper::getInstance()->findByUsername('alaress');

        $tours = \Schoolbox\Tour\Mapper::getInstance()->findAll();

        foreach ($tours as $tour) {
            $tourAudience = new TourAudience();
            $tourAudience->setRole($role);
            $tourAudience->setModerator($moderator);
            $tourAudience->setTour($tour);
            Tour\Audience\Mapper::getInstance()->save($tourAudience);
        }
        return true;
    }


    /**
     * Deletes tours for a role.
     * @param Role $role
     * @return bool
     */
    public function deleteTour(Role $role): bool
    {
        Tour\Audience\Mapper::getInstance()->deleteByColumn('role_id', $role->getId());
        return true;
    }
}
