<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use DomainException;
use Exception;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;

class UserNotifier extends AbstractModuleNotifier
{

    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';


    /**
     * @return string
     */
    protected function getSlug()
    {
        return 'user';
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;
            case Mapper::METHOD_UPDATE:
                // we're only interested if extId changed
                if (array_key_exists('externalId', $bundle->getEvent()->getDiff())) {
                    $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                }
                break;
        }
    }


    /**
     * Override, we're only interested in one callback
     *
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [
            [ $this, 'notifySystem' ]
        ];
    }


    /**
     * @deprecated
     * Manual trigger for when a user is created.
     *
     * This and manualTriggerUpdated() are intended for use by handlers, so that
     * code that currently uses handlers doesn't have to be rewritten right away
     * to use mappers.
     *
     * @param int  $userId      The ID of the user who was created
     * @param User $currentUser The user who did the creating. Usually comes
     *                          from $_SESSION[user]
     *
     * @return bool
     */
    public function manualNotifyCreated($userId, $currentUser)
    {
        try {
            if (is_null($currentUser)) {
                throw new DomainException("No effector specified");
            }
            /** @var User $user */
            $user = $this->mapper->findById($userId);
        } catch (Exception $e) {
            return false;
        }
        $this->notifySystem($user, $currentUser);
        return true;
    }


    /**
     * @deprecated
     * Manual trigger for when a user is updated.
     *
     * @param int  $userId      The ID of the user who was updated
     * @param User $currentUser The user who did the updating. Usually comes
     *                          from $_SESSION[user]
     *
     * @return bool
     */
    public function manualNotifyUpdated($userId, $currentUser)
    {
        try {
            if (is_null($currentUser)) {
                throw new DomainException("No effector specified");
            }
            /** @var User $user */
            $user = $this->mapper->findById($userId);
        } catch (Exception $e) {
            return false;
        }
        $this->notifySystem($user, $currentUser);
        return true;
    }


    /**
     * @param User  $user
     * @param User  $effector
     * @param array $diff
     */
    public function notifySystem($user, $effector, $diff = null)
    {
        try {
            $uam = UserAccessMapper::getInstance();

            // recalculate this user
            $uam->calculateUserSubscription(
                $uam->createModel()
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setUser($user)
            );

            \Alaress\Redis::getClientInstance()->publish('routed.' . INSTANCE_ID . '.reload', $user->getId());
        } catch (DefinitionException $e) {
            // ur access mapper be broked, homeslice.
        }
    }
}
