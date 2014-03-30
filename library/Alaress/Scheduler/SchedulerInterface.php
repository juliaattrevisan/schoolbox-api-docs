<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler;


use Alaress\Scheduler\Trigger\TriggerInterface;

interface SchedulerInterface {

    const IMMEDIATELY = 0;

    /**
     * Schedule a trigger for deferred execution
     *
     * @param TriggerInterface $trigger
     * @return void
     */
    public function scheduleNotification($trigger);

    /**
     * Schedule an array of triggers for deferred execution
     *
     * @param TriggerInterface[] $triggers
     * @return void
     */
    public function scheduleNotifications($triggers);

    /**
     * Dispatch a trigger for execution
     *
     * @param TriggerInterface $trigger
     * @return void
     */
    public function dispatchTrigger($trigger);
}
