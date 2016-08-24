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
        $this->accessRemoveList = $userAccessRemove;
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
             *
             * In accordance with SB-11230:
             * - don't change Full Access settings
             * - downgrade Write to Read
             */
            foreach ($this->getAccessList()->filterBy('service', UserAccess::SERVICE_HOMEPAGE) as $current) {
                if (in_array($current->getServiceIdentifier(), $identifiers)) {
                    if ($current->getAccesslevel() < FULL_ACCESS) {
                        $current->setAccessLevel($level);
                    }

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
                    $userAccess->setService(UserAccess::SERVICE_HOMEPAGE);
                    $userAccess->setServiceIdentifier($current);
                    $userAccess->setAccessLevel($level);

                    $this->accessList->add($userAccess);
                }
            }
        }
    }
}