<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Queue;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\NotificationBundle;

interface QueueInterface
{

    /**
     * Returns the name of this queue
     *
     * @return string
     */
    public function getName();


    /**
     * Returns whether or not this queue contains the given instance
     *
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance);


    /**
     * Request to drain the queue of its contents.
     *
     * @return void
     */
    public function drain();


    /**
     * Push the given instance onto this queue
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance);


    /**
     * Populates the given Bundle with all the notifications this queue contains
     *
     * @param NotificationBundle $bundle     the bundle to populate
     * @param bool               $dropCache  whether or not any internally-held cache should be dropped
     * @return void
     */
    public function fetchIntoBundle(NotificationBundle $bundle, $dropCache = false);

    /**
     * Surgically remove this message from the queue, if it's there.
     *
     * @param Instance $instance
     * @return bool
     */
    public function remove(Instance $instance): bool;

    /**
     * Surgically remove this message and any with the same base hash from the
     * queue.
     *
     * @param Instance $instance
     * @return int The number of msgs removed
     */
    public function removeAllLike(Instance $instance): int;
}
