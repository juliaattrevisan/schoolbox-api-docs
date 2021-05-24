<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Trigger;

use Alaress\Scheduler\Event\BlankEvent;

class BlankTrigger extends OnetimeTrigger
{

    /**
     * The only parameter relevant to this trigger is a timestamp
     *
     * @param int $when
     */
    public function __construct($when)
    {
        parent::__construct('', $when, new BlankEvent());
    }

    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks()
    {
        return array();
    }
}
