<?php
/**
 * Pastoral care access control. This allows the user to audience a pastoral care record to individual users, system
 * roles and user defined pastoral care groups.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Access;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Pastoral\Group\Member\Mapper as GroupMemberMapper;
use Schoolbox\Pastoral\Group\Member\Model as GroupMember;
use Schoolbox\Pastoral\Group\Model as Group;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Role;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var Pastoral */
    protected $pastoral;

    /** @var Role */
    protected $role;

    /** @var User */
    protected $user;
    
    /** @var Group */
    protected $group;

    /**
     * @param Pastoral $pastoral
     * @return self
     */
    public function setPastoral($pastoral) {
        $this->pastoral = $pastoral;
        return $this;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral() {
        return $this->pastoral;
    }

    /**
     * @param Role $role
     * @return self
     */
    public function setRole($role) {
        $this->role = $role;
        return $this;
    }

    /**
     * @return Role
     */
    public function getRole() {
        return $this->role;
    }

    /**
     * @param User $user
     * @return self
     */
    public function setUser($user) {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * @param Group $group
     * @return self
     */
    public function setGroup($group) {
        $this->group = $group;
        return $this;
    }

    /**
     * @return Group
     */
    public function getGroup() {
        return $this->group;
    }

    /**
     * Determine if this access record grants the user access to the associated pastoral record
     *
     * @param User $user
     * @param bool $checkRole Allows skipping of role check
     * @param bool $checkUser Allows skipping of user check
     * @param bool $checkGroup Allows skipping of group check
     * @return bool
     */
    public function hasAccess($user, $checkRole = true, $checkUser = true, $checkGroup = true) {

        $groupMemberMapper = GroupMemberMapper::getInstance();

        if ($checkUser && $this->getUser() instanceof User) {

            // This is a user based access record, just match user IDs

            if ($user->getId() == $this->getUser()->getId()) {
                return true;
            }
        }

        if ($checkRole && $this->getRole() instanceof Role) {

            // This is a role based access record, just match role IDs

            if ($user->getRole()->getId() == $this->getRole()->getId()) {
                return true;
            }
        }

        if ($checkGroup && $this->getGroup() instanceof Group) {

            // This is a pastoral group access record, retrieve all of the users pastoral group memberships

            $groups = $groupMemberMapper->findByColumn('user_id', $user->getId());

            // Iterate these groups and try to find a match

            /** @var GroupMember $groupMember */
            foreach ($groups as $groupMember) {

                if ($groupMember->getGroup() instanceof Group) {

                    if ($groupMember->getGroup()->getId() == $this->getGroup()->getId()) {

                        // User was in a group, they have access
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
