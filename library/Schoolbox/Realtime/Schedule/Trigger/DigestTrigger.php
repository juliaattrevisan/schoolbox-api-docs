<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger;


use Alaress\Scheduler\Trigger\RecurringTrigger;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\WorkNotifier;
use Schoolbox\Role;

class DigestTrigger extends RecurringTrigger {

    /**
     * @param WorkNotifier $notifier
     * @param string       $schedule
     * @param Role         $role
     */
	public function __construct(WorkNotifier $notifier, $schedule, $role) {
		parent::__construct(
            $role->getName(),
            $schedule,
            new Event(null, '', $role, null),
            $notifier
        );
    }

}