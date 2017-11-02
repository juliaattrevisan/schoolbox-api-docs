<?php
/**
 * Pastoral care access control
 *
 * This allows the user to audience a pastoral care record to individual users, roles and user defined pastoral groups
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Access;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Group\Model as Group;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\Pastoral\Access
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var Pastoral $pastoral */
    protected $pastoral;

    /** @var null|Role $role */
    protected $role;

    /** @var null|User $user */
    protected $user;

    /** @var null|Group $group */
    protected $group;

    /** @var bool $access */
    protected $access;

    /** @var PastoralGroupMemberMapper $pastoralGroupMemberMapper */
    private $pastoralGroupMemberMapper;

    /** @var array $accessCache This is a cache of the permission level for the page load cycle */
    private $accessCache;

    /**
     * Model constructor
     */
    public function __construct()
    {
        $this->pastoralGroupMemberMapper = PastoralGroupMemberMapper::getInstance();
    }

    /**
     * Return the access level granted to the user via this access record
     *
     * @param User $user
     *
     * @return int
     */
    public function getAccessLevel($user)
    {
        $userId = $user->getId();
        
        // User any previously cached record of the permissions for the page cycle

        if (isset($this->accessCache[$userId])) {
            return $this->accessCache[$userId];
        }

        if ($this->getUser() instanceof User && $userId == $this->getUser()->getId()) {
            // If this is an individual user permission record, just match user ID

            $this->accessCache[$userId] = $this->getAccess();
        } elseif ($this->getRole() instanceof Role && $user->getRole()->getId() == $this->getRole()->getId()) {
            // If this is a role access record, check group permissions

            $this->accessCache[$userId] = $this->getAccess();
        } elseif ($this->getGroup() instanceof Group) {
            // If this is a group access record, check group permissions

            try {
                // Make sure the user was in the group
                $this->pastoralGroupMemberMapper->findOneByColumnList([
                    'user_id' => $userId,
                    'group_id' => $this->getGroup()->getId(),
                ]);
                $this->accessCache[$userId] = $this->getAccess();

                return $this->accessCache[$userId];
            } catch (MapperException $e) {
            }
        }

        if (!isset($this->accessCache[$userId])) {
            // If we get here this record doesn't give them any permissions to the pastoral record-
            // this doesn't necessarily mean they were denied access (they might just not have been in the group/role)

            $this->accessCache[$userId] = null;
        }
        
        return $this->accessCache[$userId];
    }

    /**
     * @param Pastoral $pastoral
     *
     * @return self
     */
    public function setPastoral(Pastoral $pastoral)
    {
        $this->pastoral = $pastoral;

        return $this;
    }

    /**
     * @return Pastoral
     */
    public function getPastoral()
    {
        return $this->pastoral;
    }

    /**
     * @param null|Role $role
     *
     * @return $this
     */
    public function setRole(Role $role = null)
    {
        $this->role = $role;

        return $this;
    }

    /**
     * @return null|Role
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @param null|User $user
     *
     * @return self
     */
    public function setUser(User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return null|User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param null|Group $group
     *
     * @return self
     */
    public function setGroup(Group $group = null)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @return null|Group
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param int $access
     *
     * @return self
     */
    public function setAccess($access)
    {
        $this->access = $access;

        return $this;
    }

    /**
     * @return int
     */
    public function getAccess()
    {
        return $this->access;
    }
}
