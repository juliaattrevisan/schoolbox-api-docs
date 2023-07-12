<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Compressed;

use Alaress\Scheduler\Trigger\CancelTrigger;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;

class CompressedTriggerBundle extends TriggerBundle
{
    /**
     * @return TriggerInterface[]
     */
    public function getTriggers()
    {
        $triggers = array();

        foreach ($this->triggers as $t) {
            if ($t instanceof CancelTrigger) {
                $triggers[] = $t;
            } else {
                $triggers[] = new CompressedTrigger($t);
            }
        }

        return $triggers;
    }
}
