<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\User;

class TriggerFactory {

	/** @var ModuleNotifierInterface */
	protected $notifier;

	/** @var object */
	protected $model;

	/** @var User */
	protected $user;

	/** @var array */
	protected $diff;

	public function __construct($moduleNotifier, $model, $user, $diff = null) {
		$this->notifier = $moduleNotifier;
		$this->model = $model;
		$this->user = $user;
		$this->diff = $diff;
	}

	/**
	 * @param array $events
	 * @param bool $disregardExpired
	 * @return Trigger[]
	 */
	public function build($events, $disregardExpired = false) {
		$triggers = array();
		$now = time();

		// iterate across the events supplied and construct triggers out of them
		foreach($events as $label => $time) {
			$t = new Trigger($time, $this->notifier, $label, $this->model, $this->user, $this->diff);

			// if the timestamp is in the past, and we're disregarding expired, do not include in the return
			if(!($disregardExpired && $t->getTimestamp() < $now)) {
				$triggers[] = $t;
			}
		}
		return $triggers;
	}

} 