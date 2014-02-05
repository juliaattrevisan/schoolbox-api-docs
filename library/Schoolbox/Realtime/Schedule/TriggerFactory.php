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
	 * @return Trigger[]
	 */
	public function build($events) {
		$triggers = array();
		foreach($events as $label => $time) {
			$triggers[] = new Trigger($time, $this->notifier, $label, $this->model, $this->user, $this->diff);
		}
		return $triggers;
	}

} 