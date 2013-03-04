<?php
/**
 * Collection for submission box notifications.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox\Collection;

class SubmissionBoxNotificationCollection extends Collection {

	protected function getOfType($type) {
		foreach ($this as $item) {
			if ($item->getType() === $type) {
				return $item;
			}
		}
		//
		// blank item with default values.
		return new SubmissionBoxNotification();
	}

	protected function getOverdue() {
		return $this->getOfType('overdue');
	}

	protected function getUpcoming() {
		return $this->getOfType('overdue');
	}

	protected function getReturned() {
		return $this->getOfType('overdue');
	}
}
