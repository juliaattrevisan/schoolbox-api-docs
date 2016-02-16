<?php
/**
 * UserMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Provisioner;

use Schoolbox\UserMapper as SchoolboxMapper;

class UserMapper extends SchoolboxMapper
{
    protected function mapping()
    {
        parent::mapping();

        $this->hasPrototype(new User());
    }

    /**
     * All Permissions
     *
     * Get a list of all Role- and Group-level userAccess instances
     * Default behaviour, just as it always was
     *
     * @param User $user
     * @return mixed
     */
    protected function findAccessList(User $user)
    {
        return $this->getJoinMapper('userAccess')->findAllGroupsForUser($user);
    }
}