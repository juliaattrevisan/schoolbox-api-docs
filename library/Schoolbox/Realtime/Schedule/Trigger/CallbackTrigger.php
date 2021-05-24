<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Scheduler\Event\EventInterface;
use Alaress\Scheduler\Trigger\AbstractTrigger;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;

class CallbackTrigger extends AbstractTrigger
{

    /** @var callable */
    protected $callback;

    /** @var ModuleNotifierInterface */
    protected $notifier;


    /**
     * @param string $what                  - label describing the trigger
     * @param callable $when                - timestamp at which callbacks are to be executed
     * @param EventInterface $why           - event that precipitated this trigger's existence
     * @param ModuleNotifierInterface $how  - the notifier providing the callbacks
     */
    public function __construct($what, $when, $why, $how)
    {
        $this->label    = $what;
        $this->callback = $when;
        $this->event    = $why;
        $this->notifier = $how;
    }


    /**
     * Return the timestamp at which this trigger should fire (can be in the past)
     *
     * @return int
     */
    public function getTimestamp()
    {
        return (int)call_user_func($this->callback, $this->event);
    }


    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks()
    {
        return $this->notifier->retrieveCallbacks($this->label);
    }
}
