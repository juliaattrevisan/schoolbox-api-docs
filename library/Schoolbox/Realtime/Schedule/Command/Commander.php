<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Command;


use Alaress\Cli\Debug;
use Exception;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Schedule\Scheduler;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Commander {

	/** @var Scheduler */
	protected $scheduler;

	/**
	 * @param Scheduler $scheduler
	 */
	public function __construct($scheduler) {
		$this->scheduler = $scheduler;
	}


	/* available commands are:
	 *  - refresh all subscriptions                 (hooked from binder.all)
	 *  - add a subscription for a specific user    (hooked from `user.created` event)
	 *  - remove a subscription for a specific user (hooked from `user.deleted` event)
	 *  - run binder for all users                  (hooked from sync script)
	 *  - run binder for a specific user            (hooked from [User|Role]AccessNotifier)
	 */

	/**
	 * @param string $command - one of:
	 *  - refresh all subscriptions                 (hooked from binder.all)
	 *  - add a subscription for a specific user    (hooked from `user.created` event)
	 *  - remove a subscription for a specific user (hooked from `user.deleted` event)
	 *  - run binder for all users                  (hooked from sync script)
	 *  - run binder for a specific user            (hooked from [User|Role]AccessNotifier)
	 * @return bool
	 */
	public function exec($command) {
		$cmds = array(
			'reload'                => array($this, 'reload'),
			'subscribe.([0-9]+)'    => array($this, 'subscribeUser'),
			'unsubscribe.([0-9]+)'  => array($this, 'unsubscribeUser'),
			'bind.all'              => array($this, 'bindAll'),
			'bind.([0-9]+)'         => array($this, 'bindUser')
		);

		foreach($cmds as $regex => $callback) {
			if(preg_match("`^$regex$`", $command, $matches)) {
				if(isset($matches[1])) {
					return call_user_func($callback, $this, $matches[1]);
				}
				else {
					return call_user_func($callback, $this);
				}
			}
		}
		$this->scheduler->debug->v("Unrecognised command: $command", '!');
		return false;
	}


	/**
	 * Refresh all subscriptions (hooked from binder.all)
	 *
	 * @return bool
	 */
	protected function reload() {
		$this->scheduler->debug->v("Reloading all subscriptions");
		$this->scheduler->reload();
		return true;
	}


	/**
	 * Add a subscription for a specific user (hooked from `user.created` event)
	 *
	 * @param int $id
	 * @return bool
	 */
	protected function subscribeUser($id) {
		$this->scheduler->debug->v("Subscribing user $id");
		return $this->scheduler->subscribeUser($id);
	}


	/**
	 * Remove a subscription for a specific user (hooked from `user.deleted` event)
	 *
	 * @param int $id
	 * @return bool
	 */
	protected function unsubscribeUser($id) {
		$this->scheduler->debug->v("Unsubscribing user $id");
		return $this->scheduler->unsubscribeUser($id);
	}


	/**
	 * Run binder for a specific user (hooked from [User|Role]AccessNotifier)
	 *
	 * @param int $id
	 * @return bool
	 */
	protected function bindUser($id) {
		$this->scheduler->debug->v("Resetting bindings for user $id");
		try {
			$uqf = new UserQueueFactory();
			$uqf->destoryUserBindings($id);
			$uqf->bindUserQueue($id);
			return true;
		}
		catch(Exception $e) {
			// catch any and all exceptions
			$this->scheduler->debug->v(get_class($e) . ": ".$e->getMessage(), '!');
			return false;
		}
	}


	/**
	 * Run binder for all users (hooked from sync script)
	 * @return bool
	 */
	protected function bindAll() {
		$this->scheduler->debug->v("Resetting bindings for all users");
		$result = true;
		foreach(UserMapper::getInstance()->findAll() as $user) {
			/** @var User $user */
			$result = $result && $this->bindUser($user->getId());
		}
		return $result;
	}

} 