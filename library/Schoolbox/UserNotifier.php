<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Redis;
use DomainException;
use Exception;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;

class UserNotifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';

    /** @var ClientInterface $redis */
    private ClientInterface $redis;

    /**
     * @return string
     */
    protected function getSlug()
    {
        return 'user';
    }

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function loadDependencies($dependencies = null)
    {
        $this->redis = Mapper::getDi()->get('redis.storage');
        parent::loadDependencies($dependencies);
    }

    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        /** @var User $currentUser */
        $currentUser = $bundle->getEvent()->getObject();
        /** @var User|null $prevUser */
        $prevUser = $this->buildPrevious($currentUser, $bundle->getEvent()->getDiff());
        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;
            case Mapper::METHOD_UPDATE:
                // trigger update when externalId is changed or the user is disabled
                if (
                    array_key_exists('externalId', $bundle->getEvent()->getDiff())
                    || ($prevUser && $prevUser->getEnabled() && !$currentUser->getEnabled())
                ) {
                    $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                }
                break;
            case Mapper::METHOD_DELETE:
                $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
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

            Redis::getSignalQueueClientInstance()->publish('routed.' . INSTANCE_ID . '.reload', $user->getId());
        } catch (DefinitionException $e) {
            // ur access mapper be broked, homeslice.
        }

        if (!$user->getEnabled() || $user->isDeleted()) {
            // remember me
            $search = new Keyspace($this->redis, sprintf(\Schoolbox\Auth\Remember\Redis::REDIS_KEY_FORMAT, '*', '*'));
            foreach ($search as $key) {
                $username = (string)$this->redis->get($key);
                if ($username === $user->getUsername()) {
                    $this->redis->del($key);
                    // do not break, as there can be multiple entries
                }
            }

            // session
            $search = new Keyspace($this->redis, PipeManager::REDIS_SESSION_PREFIX . '*');
            // Decode the session data.
            // Unfortunately a session must be started in order to do this.
            // Providing tmp dir as session path
            session_save_path(sys_get_temp_dir());
            session_start();
            foreach ($search as $key) {
                $redisSession = $this->redis->get($key);
                if (!$redisSession) {
                    continue;
                }
                session_decode($redisSession);
                /** @var User|null $redisSessionUser */
                $redisSessionUser = $_SESSION['user'] ?? null;
                if ($redisSessionUser?->getId() === $user->getId()) {
                    $this->redis->del($key);
                    // do not break, as there can be multiple entries
                }
            }
            session_unset();
            session_abort();
        }
    }
}
