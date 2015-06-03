<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Command\AbstractCommander;

class RerouterCommander extends AbstractCommander {

	const CMD_RELOAD        = 'reload';
	const CMD_SUBSCRIBE     = 'subscribe.';   // note the trailing dot
	const CMD_UNSUBSCRIBE   = 'unsubscribe.'; // note the trailing dot
	const CMD_BIND          = 'bind.';        // note the trailing dot

    /** @var Mapper */
    protected $mapper;


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
     * @param Mapper $userMapper
     */
    public function __construct(Mapper $userMapper)
    {
        parent::__construct(Notif::TOPIC_CMD_REROUTE);
        $this->mapper = $userMapper;
    }


    /**
	 * @param Rerouter $rerouter
	 * @param string $command - one of:
	 *  - refresh all subscriptions                 (hooked from binder.all)
	 *  - add a subscription for a specific user    (hooked from `user.created` event)
	 *  - remove a subscription for a specific user (hooked from `user.deleted` event)
	 *  - run binder for all users                  (hooked from sync script)
	 *  - run binder for a specific user            (hooked from [User|Role]AccessNotifier)
	 * @return bool
	 */
	public function exec($rerouter, $command) {
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
					return call_user_func($callback, $rerouter, (int)$matches[1]);
				}
				else {
					return call_user_func($callback, $rerouter);
				}
			}
		}
		$rerouter->log->critical("Unrecognised command: $command");
		return false;
	}


	/**
	 * Refresh all subscriptions (hooked from binder.all)
	 *
	 * @param Rerouter $rerouter
	 * @return bool
	 */
	protected function reload($rerouter) {
		$rerouter->onPause();
		$rerouter->onResume();
		return true;
	}


	/**
	 * Add a subscription for a specific user (hooked from `user.created` event)
	 *
	 * @param Rerouter $rerouter
	 * @param int $id
	 * @return bool
	 */
	protected function subscribeUser($rerouter, $id) {
		$rerouter->log->info("Subscribing user $id");
        // there will be an implicit subscription list cache refresh in doing this: the user may not have a subscription
		return $rerouter->subscribeUser($id);
	}


	/**
	 * Remove a subscription for a specific user (hooked from `user.deleted` event)
	 *
	 * @param Rerouter $rerouter
	 * @param int $id
	 * @return bool
	 */
	protected function unsubscribeUser($rerouter, $id) {
		$rerouter->log->info("Unsubscribing user $id");
		return $rerouter->unsubscribeUser($id);
	}


	/**
	 * Run binder for a specific user (hooked from [User|Role]AccessNotifier)
	 *
	 * @param Rerouter $rerouter
	 * @param int $id
	 * @return bool
	 */
	protected function bindUser($rerouter, $id) {
        $rerouter->rebindUser($this->mapper->findById($id));
	}


	/**
	 * Run binder for all users (hooked from sync script)
	 *
	 * @param Rerouter $rerouter
	 * @return bool
	 */
	protected function bindAll($rerouter) {
        $rerouter->onHangup();
	}

}