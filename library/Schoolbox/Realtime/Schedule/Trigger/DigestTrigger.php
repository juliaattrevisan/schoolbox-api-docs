<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger;


use Schoolbox\Realtime\Digest\MailNotifier;
use Schoolbox\Realtime\Schedule\Recurrence\Recurrence;
use Schoolbox\Role;

class DigestTrigger extends AbstractTrigger {


	/** @var Recurrence */
	protected $recurrence;


	/**
	 * @param Recurrence $recurrence
	 * @param Role $role
	 */
	function __construct($recurrence, $role) {
		$this->recurrence = $recurrence;
		$this->notifier = new MailNotifier();
		$this->label = $role->getName();
		$this->model = $role;
	}

	/**
	 * Return the timestamp at which this trigger should fire (can be in the past)
	 *
	 * @return int
	 */
	public function getTimestamp() {
		return $this->recurrence->getNextTimestamp();
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