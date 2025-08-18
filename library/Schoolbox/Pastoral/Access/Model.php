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
 * This allows the user to audience a pastoral care record to individual users,
 * roles, role types and user defined pastoral groups.
 *
 * @package Schoolbox\Pastoral\Access
 */
class Model implements ISoftDeletable, Pastoral\IPastoralAccess
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var Pastoral\Model $pastoral */
    protected $pastoral;

    /** @var null|Role $role */
    protected $role;

    /**
     * The role type for this pastoral care access, if any.
     */
    private ?string $roleType = null;

    /** @var null|User $user */
    protected $user;

    /** @var null|Group $group */
    protected $group;

    /** @var int $access */
    protected $access;

    /**
     * Whether role / group access is limited to users in student campus only
     * @var bool
     */
    private bool $matchCampus = false;

    /**
     * Whether the user/role is allowed to comment
     * Staff with access can always comment regardless of this flag
     */
    private bool $commentsAllowed = false;

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
        if ($this->getRoleType() || $this->getRole() instanceof Role) {
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
        $isApplicable = false;
        if ($this->getRole() && $user->getRole()->getId() === $this->getRole()->getId()) {
            $isApplicable = true;
        }
        if ($this->getRoleType() && $user->getRole()->getType() === $this->getRoleType()) {
            $isApplicable = true;
        }

        // Not a role-based rule, or for a different role?
        if (!$isApplicable) {
            // Does not apply
            return null;
        }

        // Test in order of expected expensiveness:
        // staff, then student, then parent

        // Roles match, and roleType is staff
        if ($user->getRole()->isStaff()) {
            return $this->access;
        }

        // Is this a student rule?
        // (Only applies if the student is viewing their own record)
        if ($user->getRole()->isStudent()) {
            return $user->getId() === $this->getPastoral()->getStudent()->getId()
                ? $this->access
                : null;
        }

        // Is this a parent rule?
        if ($user->getRole()->isParent()) {
            // Rule only applies if user is actually a parent of the student.
            //isn't this what we are trying to stop... model's knowing how to access external information???
            (new \Schoolbox\External\User())->loadChildren([$this->getPastoral()->getStudent()]);
            if ($user->isParentOf($this->getPastoral()->getStudent())) {
                return $this->access;
            }
            return null;
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

        foreach ($user->getPastoralGroupIds() as $groupId) {
            if ($thisGroup->getId() === $groupId) {
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
     * @param Role|null $role
     *
     * @return $this
     */
    public function setRole(?Role $role = null): self
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
     * @return string|null
     */
    public function getRoleType(): ?string
    {
        return $this->roleType;
    }

    /**
     * @param string|null $roleType
     * @return $this
     */
    public function setRoleType(?string $roleType): self
    {
        $this->roleType = $roleType;
        return $this;
    }

    /**
     * @param User|null $user
     *
     * @return self
     */
    public function setUser(?User $user = null): self
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
     * @param Group|null $group
     *
     * @return self
     */
    public function setGroup(?Group $group = null): self
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
    public function setAccess(int $access): self
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

    /**
     * @return bool
     */
    public function getMatchCampus(): bool
    {
        return $this->matchCampus;
    }

    /**
     * @param bool $matchCampus
     * @return static
     */
    public function setMatchCampus(bool $matchCampus): self
    {
        $this->matchCampus = $matchCampus;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCommentsAllowed(): bool
    {
        return $this->commentsAllowed;
    }

    /**
     * @param bool $commentsAllowed
     * @return static
     */
    public function setCommentsAllowed(bool $commentsAllowed): self
    {
        $this->commentsAllowed = $commentsAllowed;
        return $this;
    }
}
