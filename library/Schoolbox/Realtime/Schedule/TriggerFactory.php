<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


class TriggerFactory {

	protected $notifier;
	protected $model;
	protected $userId;

	public function __construct($moduleNotifier, $model, $userId) {
		$this->notifier = $moduleNotifier;
		$this->model = $model;
		$this->userId = $userId;
	}

	/**
	 * @param array $events
	 * @return Trigger[]
	 */
	public function build($events) {
		$triggers = array();
		foreach($events as $label => $time) {
			$triggers[] = new Trigger($time, $this->notifier, $label, $this->model, $this->userId);
		}
		return $triggers;
	}

} 