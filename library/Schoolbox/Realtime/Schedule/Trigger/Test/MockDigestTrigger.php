<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Test;

use Alaress\Date\IClock;
use Schoolbox\Realtime\Schedule\Trigger\DigestTrigger;
use Schoolbox\Realtime\WorkNotifier;
use Schoolbox\Role;

class MockDigestTrigger extends DigestTrigger
{

    /** @var bool */
    protected $hasFired = false;

    /** @var int */
    protected $interval;

    /** @var IClock */
    private $clock;


    /**
     * @param WorkNotifier $notifier
     * @param IClock       $clock
     * @param string       $schedule
     * @param Role         $role
     * @param int          $interval
     */
    public function __construct(WorkNotifier $notifier, IClock $clock, $schedule, Role $role, $interval = 600)
    {
        parent::__construct($notifier, $schedule, $role);
        $this->clock    = $clock;
        $this->interval = $interval;
    }


    /**
     * OVERRIDE: always returns `$this->interval` seconds from now
     *
     * @return int
     */
    public function getTimestamp()
    {
        if ($this->hasFired) {
            // return the next interval
            return $this->clock->time() + $this->interval;
        }

        $this->hasFired = true;

        // fire the first iteration immediately
        return $this->clock->time();
    }
}
