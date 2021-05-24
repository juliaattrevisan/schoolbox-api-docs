<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Factory;

use Alaress\Date\IClock;
use Alaress\Scheduler\Trigger\RecurringTrigger;
use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockDigestTrigger;
use Schoolbox\Realtime\WorkNotifier;
use Schoolbox\Role;

class DigestTriggerFactory
{

    /** @var WorkNotifier */
    private $notifier;

    /** @var IClock */
    private $clock;

    /** @var int */
    private $debugInterval;


    /**
     * @param WorkNotifier $notifier
     * @param IClock       $clock
     * @param int          $debugInterval
     */
    public function __construct(WorkNotifier $notifier, IClock $clock, $debugInterval = null)
    {
        $this->notifier      = $notifier;
        $this->clock         = $clock;
        $this->debugInterval = $debugInterval;
    }


    /**
     * @param string $schedule
     * @param Role   $role
     * @return RecurringTrigger
     */
    public function build($schedule, Role $role)
    {
        if ($this->debugInterval) {
            return new MockDigestTrigger($this->notifier, $this->clock, $schedule, $role, $this->debugInterval);
        }

        return new DigestTrigger($this->notifier, $schedule, $role);
    }
}
