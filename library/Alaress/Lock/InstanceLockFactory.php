<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Lock;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Schoolbox's lock factory class.
 *
 * Wraps around a Symfony lock factory instance, and ensures that an instance ID
 * is applied to the lock key.
 *
 * Example lock usage:
 * <code>
 * $factory = new InstanceLockFactory($lockFactory, INSTANCE_ID);
 * $lock    = $factory->createLock('lockName', 300);
 * if ($lock->acquire()) {
 *     // perform a process which requires a lock, and then:
 *     $lock->release();
 * }
 * </code>
 *
 * @see https://symfony.com/doc/master/components/lock.html for further
 *      documentation of how to use a lock.
 */
class InstanceLockFactory
{
    /**
     * A Symfony lock factory instance.
     *
     * @var LockFactory
     */
    private $lockFactory;


    /**
     * This Schoolbox instance's ID: used when creating a lock, to ensure that
     * the lock is associated with that instance.
     *
     * @var string
     */
    private $instanceId;


    /**
     * Constructor
     *
     * @param LockFactory $lockFactory
     * @param string      $instanceId
     */
    public function __construct(LockFactory $lockFactory, string $instanceId)
    {
        $this->lockFactory = $lockFactory;
        $this->instanceId  = $instanceId;
    }


    /**
     * Creates a lock for the given resource.
     *
     * @param string     $resource    The resource to lock
     * @param float|null $ttl         Maximum expected lock duration in seconds
     * @param bool       $autoRelease Whether to automatically release the lock
     *                                or not when the lock instance is destroyed
     * @return LockInterface
     */
    public function createLock(string $resource, $ttl = 300.0, $autoRelease = true): LockInterface
    {
        return $this->lockFactory->createLock($this->calculateKeyName($resource), $ttl, $autoRelease);
    }


    /**
     * Determines the key name for the given resource name.
     *
     * @param string $resource The resource to determine the key name for
     * @return string
     */
    public function calculateKeyName(string $resource): string
    {
        return $this->instanceId . '.' . $resource;
    }
}
