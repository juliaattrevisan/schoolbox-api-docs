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

interface QueueInterface
{

    /**
     * Returns the name of this queue
     *
     * @return string
     */
    public function getName();


    /**
     * Will returns all the messages this queue contains, parsed as Instance objects
     *
     * @return Instance[]
     */
    public function fetch();


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
}