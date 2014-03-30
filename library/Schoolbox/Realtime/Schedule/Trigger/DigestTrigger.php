<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger;


use Alaress\Schedule\Trigger\RecurringTrigger;
use Cron\CronExpression;
use Schoolbox\Realtime\Digest\MailNotifier;
use Schoolbox\Role;

class DigestTrigger extends RecurringTrigger {


	/**
	 * @param string $schedule
	 * @param Role $role
	 */
	function __construct($schedule, $role) {
		$this->cron = CronExpression::factory($schedule);
		$this->notifier = new MailNotifier();
		$this->label = $role->getName();
		$this->model = $role;
	}

	/**
	 * Return an array of callables that are appropriate for this trigger
	 *
	 * @return callable[]
	 */
	public function getCallbacks() {
		return array(array($this->notifier, 'processDigest'));
	}

} 