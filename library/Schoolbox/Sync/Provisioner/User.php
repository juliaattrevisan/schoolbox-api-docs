<?php
/**
 * User
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Provisioner;

use Schoolbox\User as SchoolboxUser;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Collection;

class User extends SchoolboxUser
{
    /**
     * A list of UserAccess instances to remove for this
     * particular user instance
     *
     * @var array
     */
    protected $accessRemoveList = [];

    /**
     * Get user access read
     * A list of Groups to which this user has Read access
     *
     * @return array
     */
    public function getUserAccessRead()
    {
        return $this->accessList->filterBy('accessLevel', UserAccess::ACCESS_READ)->getColumnValues('serviceIdentifier');
    }

    /**
     * Set user access read
     *
     * @param array $userAccessRead
     */
    public function setUserAccessRead($userAccessRead)
    {
        $this->setAccessLevel($userAccessRead, UserAccess::ACCESS_READ);
    }

    /**
     * Get user access write
     * A list of Groups to which this user has Write access
     *
     * @return array
     */
    public function getUserAccessWrite()
    {
        return $this->accessList->filterBy('accessLevel', UserAccess::ACCESS_WRITE)->getColumnValues('serviceIdentifier');
    }

    /**
     * Set user access write
     *
     * @param array $userAccessWrite
     */
    public function setUserAccessWrite($userAccessWrite)
    {
        $this->setAccessLevel($userAccessWrite, UserAccess::ACCESS_WRITE);
    }

    /**
     * Get user access remove
     *
     * @return mixed
     */
    public function getUserAccessRemove()
    {
        return $this->accessRemoveList;
    }

    /**
     * Remove selected UserAccess instances
     *
     * @param $userAccessRemove
     */
    public function setUserAccessRemove($userAccessRemove)
    {
        /* Modify accessList only when ups_groups_enabled is actually enabled
         * Otherwise leave it unchanged from whatever it was
         */
        if ($this->getConfigLoader()->get('ups_groups_enabled')) {
            $userAccessMapper = new UserAccessMapper();

            /* Get a list of all the UserAccess instances for this User
             */
            $allUserAccess = $userAccessMapper->findAllGroupsForUser($this);

            /* Search $allUserAccess for anything that should be deleted
             * If found, delete it from the db if it was already there
             * These are spawned from a previous Rule.
             */
            foreach ($userAccessRemove as $current) {
                $found = $allUserAccess->getBy('serviceIdentifier', $current);

                /* Do not blow away Owners, only Read/Write accessors
                 */
                if ($found && ($found->getAccessLevel() < UserAccess::ACCESS_FULL)) {
                    $userAccessMapper->delete($found);
                }
            }

            /* If you combined "Groups (Read)" and "Groups (Remove)" in the same rule
             * you might be in a state where items in $userAccessRemove won't be in the
             * db, but will have been added via "Groups (Read)". This means it won't
             * have anything to delete right now.
             *
             * Truncate the $userAccessRemove elements from $this->accessList as well
             * to ensure that what you remove applies to all other Groups (Read/Write)
             * effects in this rule
             */
            $trimmedAccessList = new Collection();

            foreach ($this->getAccessList() as $currentAccessExists) {
                $found = false;

                foreach ($userAccessRemove as $currentAccessRemove) {
                    if ($currentAccessExists->getServiceIdentifier() == $currentAccessRemove) {
                        $found = true;
                    }
                }

                /* Keep it, if it's not supposed to be removed
                 */
                if (!$found) {
                    $trimmedAccessList->add($currentAccessExists);
                }
            }

            /* Square it away
             */
            $this->setAccessList($trimmedAccessList);
        }
    }

    /**
     * Set $identifiers to access level $level in $accessList collection
     *
     * @param $identifiers
     * @param $level
     */
    private function setAccessLevel($identifiers, $level)
    {
        /* Modify accessList only when ups_groups_enabled is actually enabled
         * Otherwise leave it unchanged from whatever it was
         */
        if ($this->getConfigLoader()->get('ups_groups_enabled')) {
            $found = [];

            /* Update existing userAccess instance
             */
            foreach ($this->getAccessList() as $current) {
                if (in_array($current->getServiceIdentifier(), $identifiers)) {
                    $current->setAccessLevel($level);

                    $found[] = $current->getServiceIdentifier();
                }
            }

            /* If the userAccess instance didn't exist, create it
             */
            $diff = array_diff($identifiers, $found);

            if (count($diff)) {
                foreach ($diff as $current) {
                    $userAccess = new UserAccess();
                    $userAccess->setUser($this);
                    $userAccess->setService(HOMEPAGE_SERVICE);
                    $userAccess->setServiceIdentifier($current);
                    $userAccess->setAccessLevel($level);

                    $this->accessList->add($userAccess);
                }
            }
        }
    }
}