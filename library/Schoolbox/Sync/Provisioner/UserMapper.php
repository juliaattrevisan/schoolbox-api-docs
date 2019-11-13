<?php
/**
 * UserMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Provisioner;

use Schoolbox;
use Schoolbox\UserMapper as SchoolboxMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Collection;

/** @method User findByExternalId($id, $roleType = null) */
class UserMapper extends SchoolboxMapper
{
    protected function mapping()
    {
        parent::mapping();

        $this->hasModel(User::class);

        $this->registerCallback('postSave', array($this, 'postSave'));

        /** @var UserAccessMapper $accessMapper */
        $accessMapper = $this->getJoinMapper('userAccess');

        // unilaterally stop the access mapper from attempting to mutate the route cache
        $accessMapper->unregisterCallbacks();
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
        /* Modify accessList only when ups_groups_enabled is actually enabled
         * Otherwise leave it unchanged from whatever it was
         */

        // TODO inject this
        $config = new ConfigLoader();

        if ($config->get('ups_groups_enabled')) {
            /** @var UserAccessMapper $userAccessMapper */
            $userAccessMapper = $this->getJoinMapper('userAccess');

            // make sure any mutes go through correctly
            $userAccessMapper->setEventPropagator($this->getEventPropagator());

            /* If you combined 'Groups (Read/Write)' and 'Groups (Remove)' in the
             * same rule, you'll always be added to the groups specified by the
             * value of 'Groups (Read/Write)'. It will overpower the Remove.
             * It is recommended that Remove operations be set up before Read/Write
             *
             * This is so you can remove people from multiple groups, then reassign
             * people to new groups, in the same rule.
             *
             * There isn't a way to tell whether the 'Groups (Remove)' came before
             * or after the 'Groups (Read/Write)' in the same rule.
             */

            /* Get a list of all the UserAccess instances for this User
             */
            $allUserAccess = $userAccessMapper->findAllGroupsForUser($user);

            /* Search $allUserAccess for anything that should be deleted
             * If found, delete it from the db if it was already there
             * These are spawned from a previous Rule.
             */
            foreach ($user->getUserAccessRemove() as $current) {
                $found = $allUserAccess->getBy('serviceIdentifier', $current);

                /* Do not blow away Owners, only Read/Write accessors
                 */
                if ($found instanceof UserAccess && ($found->getAccessLevel() < UserAccess::ACCESS_FULL)) {
                    $userAccessMapper->deleteByColumnList([
                        'uid'     => $user->getId(),
                        'sid'     => $found->getServiceIdentifier(),
                        'service' => UserAccess::SERVICE_HOMEPAGE,
                    ]);
                }
            }

            /* Reinstate all elements in $accessList
             */
            foreach ($user->getAccessList() as $current) {
                $userAccessMapper->save($current);
            }
        }
    }
}
