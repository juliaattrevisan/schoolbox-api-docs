<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Command;


use Exception;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Command\AbstractCommander;
use Schoolbox\Realtime\Schedule\Scheduler;
use Schoolbox\User;
use Schoolbox\UserMapper;

class SchedulerCommander extends AbstractCommander {

	const CMD_RELOAD        = 'reload';
	const CMD_SUBSCRIBE     = 'subscribe.';   // note the trailing dot
	const CMD_UNSUBSCRIBE   = 'unsubscribe.'; // note the trailing dot
	const CMD_BIND          = 'bind.';        // note the trailing dot


	/**
	 * Call through to scheduler to statically bind queues
	 *
	 * @return void
	 */
	protected function bindQueues() {
		Scheduler::bindQueues();
	}


	/**
	 * Alias of advise(self::CMD_RELOAD)
	 */
	public function adviseReload() {
		$this->advise(self::CMD_RELOAD);
	}


	/**
	 * Alias of advise(self::CMD_RELOAD)
	 */
	public function adviseSubscribeUser($id) {
		$this->advise(self::CMD_SUBSCRIBE, $id);
	}


	/**
	 * Alias of advise(self::CMD_RELOAD)
	 */
	public function adviseUnsubscribeUser($id) {
		$this->advise(self::CMD_UNSUBSCRIBE, $id);
	}


	/**
	 * Alias of advise(self::CMD_RELOAD)
	 */
	public function adviseBindUser($id) {
		$this->advise(self::CMD_BIND, $id);
	}


	/**
	 * Alias of advise(self::CMD_RELOAD)
	 */
	public function adviseBindAll() {
		$this->advise(self::CMD_BIND, 'all');
	}


	/**
	 * General construction
	 */
	public function __construct() {
		parent::__construct(Notif::TOPIC_CMD_SCHED);
	}


	/**
	 * @param Scheduler $scheduler
	 * @param string $command - one of:
	 *  - refresh all subscriptions                 (hooked from binder.all)
	 *  - add a subscription for a specific user    (hooked from `user.created` event)
	 *  - remove a subscription for a specific user (hooked from `user.deleted` event)
	 *  - run binder for all users                  (hooked from sync script)
	 *  - run binder for a specific user            (hooked from [User|Role]AccessNotifier)
	 * @return bool
	 */
	public function exec($scheduler, $command) {
		$cmds = array(
			self::CMD_RELOAD                    => array($this, 'reload'),
			self::CMD_SUBSCRIBE.'([0-9]+)'      => array($this, 'subscribeUser'),
			self::CMD_UNSUBSCRIBE.'([0-9]+)'    => array($this, 'unsubscribeUser'),
			self::CMD_BIND.'all'                => array($this, 'bindAll'),
			self::CMD_BIND.'([0-9]+)'           => array($this, 'bindUser')
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
		$scheduler->debug->v("Unrecognised command: $command", '!');
		return false;
	}


	/**
	 * Refresh all subscriptions (hooked from binder.all)
	 *
	 * @param Scheduler $scheduler
	 * @return bool
	 */
	protected function reload($scheduler) {
		$scheduler->debug->v("Reloading all subscriptions");
		$scheduler->reload();
		return true;
	}


	/**
	 * Add a subscription for a specific user (hooked from `user.created` event)
	 *
	 * @param Scheduler $scheduler
	 * @param int $id
	 * @return bool
	 */
	protected function subscribeUser($scheduler, $id) {
		$scheduler->debug->v("Subscribing user $id");
		return $scheduler->subscribeUser($id);
	}


	/**
	 * Remove a subscription for a specific user (hooked from `user.deleted` event)
	 *
	 * @param Scheduler $scheduler
	 * @param int $id
	 * @return bool
	 */
	protected function unsubscribeUser($scheduler, $id) {
		$scheduler->debug->v("Unsubscribing user $id");
		return $scheduler->unsubscribeUser($id);
	}


	/**
	 * Run binder for a specific user (hooked from [User|Role]AccessNotifier)
	 *
	 * @param Scheduler $scheduler
	 * @param int $id
	 * @return bool
	 */
	protected function bindUser($scheduler, $id) {
		$scheduler->debug->v("Resetting bindings for user $id");
		try {
			$uqf = new UserQueueFactory();
			$uqf->destoryUserBindings($id);
			$uqf->bindUserQueue($id);
			return true;
		}
		catch(Exception $e) {
			// catch any and all exceptions
			$scheduler->debug->v(get_class($e) . ": ".$e->getMessage(), '!');
			return false;
		}
	}


	/**
	 * Run binder for all users (hooked from sync script)
	 *
	 * @param Scheduler $scheduler
	 * @return bool
	 */
	protected function bindAll($scheduler) {
		$scheduler->debug->v("Resetting bindings for all users");
		$result = true;
		foreach(UserMapper::getInstance()->findAll() as $user) {
			/** @var User $user */
			$result = $result && $this->bindUser($scheduler, $user->getId());
		}
		return $result;
	}

}