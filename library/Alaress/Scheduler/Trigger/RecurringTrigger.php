<?php

 /**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Trigger;

use Alaress\Scheduler\Event\EventInterface;
use Cron\CronExpression;
use RuntimeException;
use Schoolbox\Realtime\WorkNotifier;

abstract class RecurringTrigger extends AbstractTrigger
{

    /** @var CronExpression */
    protected $cron;

    /** @var WorkNotifier */
    protected $notifier;


    /**
     * @param string $what           - the label describing this trigger
     * @param string $when           - the timestamp at which this trigger should fire
     * @param EventInterface $why    - the event that precipitated this trigger's existence
     * @param WorkNotifier $notifier - the notifier that will be invoked to process at the appointed time
     */
    public function __construct($what, $when, EventInterface $why, WorkNotifier $notifier)
    {
        $this->label    = $what;
        $this->cron     = CronExpression::factory($when);
        $this->event    = $why;
        $this->notifier = $notifier;
    }


    /**
     * Return the timestamp at which this trigger should fire (can be in the past)
     *
     * @return int
     * @throws RuntimeException
     */
    public function getTimestamp()
    {
        return $this->cron->getNextRunDate()->getTimestamp();
    }


    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks()
    {
        return [ [ $this->notifier, 'process' ] ];
    }
}
