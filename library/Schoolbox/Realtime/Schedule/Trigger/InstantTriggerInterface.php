<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;

interface InstantTriggerInterface extends TriggerInterface
{

    /**
     * @return AbstractModuleNotifier
     */
    public function getModuleNotifier();
}
