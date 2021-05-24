<?php

 /**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Scheduler\Event\EventInterface;
use Alaress\Scheduler\Trigger\OnetimeTrigger;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;

class InstantTrigger extends OnetimeTrigger implements InstantTriggerInterface
{

    /** @var ModuleNotifierInterface */
    protected $notifier;


    /**
     * @param string $what                  - label describing the trigger
     * @param int $when                     - timestamp at which callbacks are to be executed
     * @param EventInterface $why           - event that precipitated this trigger's existence
     * @param ModuleNotifierInterface $how  - the notifier providing the callbacks
     */
    public function __construct($what, $when, $why, $how)
    {
        parent::__construct($what, $when, $why);
        $this->notifier = $how;
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

    /**
     * @return ModuleNotifierInterface
     */
    public function getModuleNotifier()
    {
        return $this->notifier;
    }
}
