<?php
/**
 * Collection for submission box notifications.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;

class NotificationCollection extends Collection {

	public function getOfType($type) {
		foreach ($this as $item) {
			if ($item->getType() === $type) {
				return $item;
			}
		}
		return false;
//		//
//		// consider returning blank item with default values.
//		$notification = new SubmissionBoxNotification();
//		$notification->setType($type);
//		return $notification;
	}

	public function getOverdue() {
		return $this->getOfType(Notification::TYPE_OVERDUE);
	}

	public function getUpcoming() {
		return $this->getOfType(Notification::TYPE_UPCOMING);
	}

	public function getReturned() {
		return $this->getOfType(Notification::TYPE_RETURNED);
	}
}
