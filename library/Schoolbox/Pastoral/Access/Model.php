<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Access;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral;
use Schoolbox\Pastoral\Group\Model as Group;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Pastoral care access control
 *
 * This allows the user to audience a pastoral care record to individual users, roles and user defined pastoral groups
 *
 * @package Schoolbox\Pastoral\Access
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var Pastoral\Model $pastoral */
    protected $pastoral;

    /** @var null|Role $role */
    protected $role;

    /** @var null|User $user */
    protected $user;

    /** @var null|Group $group */
    protected $group;

    /** @var int $access */
    protected $access;


    /**
     * Return the access level granted to the user via this access record
     *
     * Returns NULL if this record is not relevant for the specified user
     * Consider using AccessLevelCensor::getAccessLevel instead
     *
     * @param User $user Must have Role and PastoralGroupMembership relations hydrated
     *
     * @return null|int
     */
    public function getAccessLevel(User $user): ?int
    {
        if ($this->getUser() instanceof User) {
            return $this->getUserAccessLevel($user);
        }
        if ($this->getRole() instanceof Role) {
            return $this->getRoleAccessLevel($user);
        }
        if ($this->getGroup() instanceof Group) {
            return $this->getPastoralGroupAccessLevel($user);
        }
        // This will only happen if this model is a stub. Treat this as unknown
        return null;
    }


    /**
     * Returns the accessLevel granted by this record to the specified user via 'User' access
     *
     * @param User $user
     * @return int|null
     */
    private function getUserAccessLevel(User $user): ?int
    {
        $thisUser = $this->getUser();
        if ($thisUser && $thisUser->getId() === $user->getId()) {
            return $this->access;
        }
        return null;
    }


    /**
     * Returns the accessLevel granted by this record to the specified user via 'User' access
     *
     * In the case where the referenced role has a roleType of 'staff', there is an intentional difference between
     * the set of users who have access to the record via this rule, and the set of users who will receive
     * notifications via this rule. The latter is a subset of the first, being only those staff who teach classes
     * the student is enrolled in.
     *
     * @param User $user
     * @return int|null
     */
    private function getRoleAccessLevel(User $user): ?int
    {
        $thisRole = $this->getRole();

        // Not a role-based rule, or for a different role?
        if (!$thisRole || $user->getRole()->getId() !== $thisRole->getId()) {
            // Does not apply
            return null;
        }

        // Is this a parent rule?
        if ($user->getRole()->isParent()) {
            // Rule only applies if user is actually a parent of the student.
            if ($user->isParentOf($this->getPastoral()->getStudent())) {
                return $this->access;
            }
            return null;
        }

        // Roles match, and roleType is staff
        if ($user->getRole()->isStaff()) {
            return $this->access;
        }
        return null;
    }


    /**
     * Returns the accessLevel granted by this record to the specified user via 'User' access
     *
     * @param User $user
     * @return int|null
     */
    private function getPastoralGroupAccessLevel(User $user): ?int
    {
        $thisGroup = $this->getGroup();
        if (!$thisGroup) {
            return null;
        }

        foreach ($user->getPastoralGroupsMemberships() as $groupsMembership) {
            if ($thisGroup->getId() === $groupsMembership->getGroup()->getId()) {
                return $this->access;
            }
        }

        return null;
    }

    // Plain getters/setters

    /**
     * @param Pastoral\Model $pastoral
     *
     * @return self
     */
    public function setPastoral(Pastoral\Model $pastoral): self
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * @return Pastoral\Model
     */
    public function getPastoral(): Pastoral\Model
    {
        return $this->pastoral;
    }

    /**
     * @param null|Role $role
     *
     * @return $this
     */
    public function setRole(Role $role = null): self
    {
        $this->role = $role;

        return $this;
    }

    /**
     * @return null|Role
     */
    public function getRole(): ?Role
    {
        return $this->role;
    }

    /**
     * @param null|User $user
     *
     * @return self
     */
    public function setUser(User $user = null): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return null|User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param null|Group $group
     *
     * @return self
     */
    public function setGroup(Group $group = null): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @return null|Group
     */
    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * @param int $access
     *
     * @return self
     */
    public function setAccess($access): self
    {
        $this->access = $access;

        return $this;
    }

    /**
     * @return null|int
     */
    public function getAccess(): ?int
    {
        return $this->access;
    }
}
