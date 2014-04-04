<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Scheduler\Trigger;


use Alaress\Scheduler\Event\EventInterface;
use Cron\CronExpression;

abstract class RecurringTrigger extends AbstractTrigger {

    /** @var CronExpression */
    protected $cron;

    /**
     * @param string $what - the label describing this trigger
     * @param string $when    - the timestamp at which this trigger should fire
     * @param EventInterface $why   - the event that precipitated this trigger's existence
     */
    public function __construct($what, $when, $why) {
        $this->label = $what;
        $this->timestamp = $when;
        $this->cron = CronExpression::factory($when);
        $this->event = $why;
    }

    /**
     * Return the timestamp at which this trigger should fire (can be in the past)
     *
     * @return int
     */
    public function getTimestamp() {
        return $this->cron->getNextRunDate()->getTimestamp();
    }

} 