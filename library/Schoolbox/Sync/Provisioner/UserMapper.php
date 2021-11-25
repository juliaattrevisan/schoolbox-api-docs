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
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postSave($user): void
    {
        /* Modify accessList only when ups_groups_enabled is actually enabled
         * Otherwise leave it unchanged from whatever it was
         */
        $config = $this->getConfigLoader();

        if ($config->get('ups_groups_enabled')) {
            /** @var UserAccessMapper $userAccessMapper */
            $userAccessMapper = $this->getJoinMapper('userAccess');

            // make sure any mutes go through correctly
            $userAccessMapper->setEventPropagator($this->getEventPropagator());

            /* Update the UserAccess instances
             *
             * Clear their existing ones
             * Find the final UserAccess instances that a person should have
             * Insert these ones
             */
            $userAccesses = $userAccessMapper->findByColumnList([
                'uid' => $user->getId(),
                'service' => UserAccess::SERVICE_HOMEPAGE
            ]);

            foreach ($userAccesses as $userAccess) {
                if ($userAccess->getAccessLevel() !== UserAccess::ACCESS_FULL) {
                    $userAccessMapper->delete($userAccess);
                }
            }

            $finalUserAccess = (new FinalUserAccess(\Schoolbox\Resource\Folder\Mapper::getInstance(), null))
                ->generate($user);

            foreach ($finalUserAccess as $folderId => $accessLevel) {
                $userAccess = $userAccessMapper->createModel()
                    ->setUser($user)
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setServiceIdentifier($folderId)
                    ->setAccessLevel($accessLevel);

                $userAccessMapper->save($userAccess);
            }
        }
    }
}
