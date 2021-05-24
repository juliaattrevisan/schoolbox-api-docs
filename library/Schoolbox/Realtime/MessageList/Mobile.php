<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Async\Queue\QueueInterface;
use Schoolbox\MobileApp;

class Mobile implements QueueInterface
{
    /** @var MobileApp\IServiceManager */
    private $cloudManager;

    /** @var int */
    private $userId;


    /**
     * @param MobileApp\IServiceManager $cloudManager
     * @param int                      $userId
     */
    public function __construct(MobileApp\IServiceManager $cloudManager, $userId)
    {
        $this->cloudManager = $cloudManager;
        $this->userId       = $userId;
    }


    /**
     * Returns the name of this queue
     *
     * @return string
     */
    public function getName()
    {
        return "{$this->userId}:mobile";
    }


    /**
     * Returns whether or not this queue contains the given instance
     *
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance)
    {
        // this "list" doesn't contain things
        return false;
    }


    /**
     * Request to drain the queue of its contents.
     *
     * @return void
     */
    public function drain()
    {
        // noop: this "list" doesn't contain things
    }


    /**
     * Push the given instance onto this queue
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance)
    {
        $this->cloudManager->push($this->userId, $instance);
    }


    /**
     * Populates the given Bundle with all the notifications this queue contains
     *
     * @param NotificationBundle $bundle    the bundle to populate
     * @param bool               $dropCache whether or not any internally-held cache should be dropped
     * @return void
     */
    public function fetchIntoBundle(NotificationBundle $bundle, $dropCache = false)
    {
        // noop: this "list" doesn't contain things
    }
}
