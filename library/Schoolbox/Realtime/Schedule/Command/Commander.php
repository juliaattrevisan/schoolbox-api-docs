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
use Schoolbox\Realtime\Schedule\Scheduler;

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
					call_user_func($callback, $this, $matches[1]);
				}
				else {
					call_user_func($callback, $this);
				}
				break;
			}
		}
	}


	/**
	 * Refresh all subscriptions (hooked from binder.all)
	 */
	protected function reload() {
		$this->scheduler->debug->v("Reloading all subscriptions");
	}


	/**
	 * Add a subscription for a specific user (hooked from `user.created` event)
	 *
	 * @param int $id
	 */
	protected function subscribeUser($id) {
		$this->scheduler->debug->v("Subscribing user $id");

	}


	/**
	 * Remove a subscription for a specific user (hooked from `user.deleted` event)
	 *
	 * @param int $id
	 */
	protected function unsubscribeUser($id) {
		$this->scheduler->debug->v("Unsubscribing user $id");

	}


	/**
	 * Run binder for a specific user (hooked from [User|Role]AccessNotifier)
	 *
	 * @param int $id
	 */
	protected function bindUser($id) {
		$this->scheduler->debug->v("Resetting bindings for user $id");

	}


	/**
	 * Run binder for all users (hooked from sync script)
	 */
	protected function bindAll() {
		$this->scheduler->debug->v("Resetting bindings for all users");

	}

} 