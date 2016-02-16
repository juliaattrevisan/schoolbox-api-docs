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

class User extends SchoolboxUser
{

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
     * Set $identifiers to access level $level in $accessList collection
     *
     * @param $identifiers
     * @param $level
     */
    private function setAccessLevel($identifiers, $level)
    {
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