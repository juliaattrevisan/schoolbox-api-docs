<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Alaress\Async\Stomp\SubscriptionList;
use Schoolbox\Collection;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\User;
use Schoolbox\UserMapper;

class UserSubscriptionList extends SubscriptionList {


	/**
	 * @param UserMapper $source
	 * @param bool       $clear
	 * @return void
	 */
	public function importFromSource($source, $clear = true) {
		if($clear) {
			$this->queues = array();
		}

		/** @var Collection|User[] $users */
		$users = $source->findAll();

		foreach($users as $user) {
			$queue = UserQueue::getUserToSortQueueName($user->getId());
			$this->queues[$queue] = $source->findById($user->getId()); // TODO srsly. this has to change.
		}
	}

}