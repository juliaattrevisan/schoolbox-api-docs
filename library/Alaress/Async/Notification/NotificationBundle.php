<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Async\Notification;


use Alaress\Async\Notification\Instance\Instance;

class NotificationBundle {

	/** @var Notification[] */
	protected $notifications;


	/**
	 * Construct
	 */
	public function __construct() {
		$this->notifications = array();
	}


	/**
	 * @param Instance[] $instances
	 * @return self
	 */
	public function appendInstances($instances) {
		foreach($instances as $instance) {
			$this->appendInstance($instance);
		}
		return $this;
	}


	/**
	 * @param Instance $instance
	 * @return self
	 */
	public function appendInstance($instance) {
		$hash = $instance->getBaseHash();

		if(isset($this->notifications[$hash])) {
			$this->notifications[$hash]->appendInstance($instance);
		}
		else {
			$this->notifications[$hash] = new Notification(array($instance));
		}
		return $this;
	}


	/**
	 * @param Notification[] $notifications
	 * @return self
	 */
	public function appendNotifications($notifications) {
		foreach($notifications as $n) {
			$this->appendNotification($n);
		}
		return $this;
	}


	/**
	 * @param Notification $notification
	 * @return self
	 */
	public function appendNotification($notification) {
		// no duplicates
		if(!isset($this->notifications[$notification->getId()])) {
			$this->notifications[$notification->getId()] = $notification;
		}
		return $this;
	}

	/**
	 * @return Notification[]
	 */
	public function getAll() {
		return array_values($this->notifications);
	}


	/**
	 * @return int
	 */
	public function count() {
		return count($this->notifications);
	}

} 