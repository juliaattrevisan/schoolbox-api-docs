<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;


use DomainException;
use Exception;
use Schoolbox\Realtime\Rerouter\RerouterCommander;
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
        return array(
            array($this, 'notify' . ucfirst($label))
        );
    }


    /**
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
            $user = $this->mapper->findById($userId);
        } catch (Exception $e) {
            return false;
        }
        $this->notifyCreated($user, $currentUser);
        return true;
    }


    /**
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
            $user = $this->mapper->findById($userId);
        } catch (Exception $e) {
            return false;
        }
        $this->notifyUpdated($user, $currentUser);
        return true;
    }


    /**
     * @param User  $user
     * @param User  $effector
     * @param array $diff
     */
    public function notifyCreated($user, $effector, $diff = null)
    {
        $cmdr = new RerouterCommander($this->mapper);
        $cmdr->adviseBindUser($user->getId());
        $cmdr->adviseSubscribeUser($user->getId());
    }


    /**
     * @param User  $user
     * @param User  $effector
     * @param array $diff
     */
    public function notifyUpdated($user, $effector, $diff = null)
    {
        // as above, only executed if externalId changed
        $cmdr = new RerouterCommander($this->mapper);
        $cmdr->adviseBindUser($user->getId());
    }

}