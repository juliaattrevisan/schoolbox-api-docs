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
use Schoolbox\Realtime\Digest\MailNotifier;
use Schoolbox\Realtime\Schedule\Event;
use Schoolbox\Role;

class DigestTrigger extends RecurringTrigger {

	/** @var MailNotifier */
	protected $notifier;

	/**
	 * @param string $schedule
	 * @param Role $role
	 */
	function __construct($schedule, $role) {
		parent::__construct($role->getName(), $schedule, new Event(null, '', $role, null));
		$this->notifier = new MailNotifier();
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