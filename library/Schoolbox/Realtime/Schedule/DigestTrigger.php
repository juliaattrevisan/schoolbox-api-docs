<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress\Schedule\Trigger\AbstractTrigger;
use Cron\CronExpression;
use Schoolbox\Realtime\Digest\MailNotifier;
use Schoolbox\Role;

class DigestTrigger extends AbstractTrigger {


	/** @var CronExpression */
	protected $cron;


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
	 * Return the timestamp at which this trigger will NEXT fire
	 *
	 * @return int
	 */
	public function getTimestamp() {
		return $this->cron->getNextRunDate()->getTimestamp();
	}

	/**
	 * Return an array of callables that are appropriate for this trigger
	 *
	 * @return callable[]
	 */
	public function getCallbacks() {
		return array(array($this->notifier, 'processDigest'));
	}

	/**
	 * Return the diff of the object represented by this trigger, or null if it's new
	 *
	 * @return array|null
	 */
	public function getDiff() {
		return null;
	}


} 