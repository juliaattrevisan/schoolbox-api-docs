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

use Schoolbox;
use Schoolbox\UserMapper as SchoolboxMapper;
use Schoolbox\ConfigLoader;

/** @method User findByExternalId($id, $roleType = null) */
class UserMapper extends SchoolboxMapper
{
    protected function mapping()
    {
        parent::mapping();

        $this->hasPrototype(new User());

        $this->registerCallback('postSave', array($this, 'postSave'));
    }

    /**
     * All Permissions
     *
     * Get a list of all Role- and Group-level userAccess instances
     * Default behaviour, just as it always was
     *
     * @param Schoolbox\User $user
     * @return mixed
     */
    protected function findAccessList(Schoolbox\User $user)
    {
        return $this->getJoinMapper('userAccess')->findAllGroupsForUser($user);
    }

    /**
     * Save an updated list of UserAccess instances
     *
     * @param User $user
     */
    public function postSave($user)
    {
        $config = new ConfigLoader();

        if ($config->get('ups_groups_enabled')) {
            $userAccessMapper = $this->getJoinMapper('userAccess');

            // make sure any mutes go through correctly
            $userAccessMapper->setNotifier($this->getNotifier());

            foreach ($user->getAccessList() as $current) {
                $userAccessMapper->save($current);
            }
        }
    }
}
