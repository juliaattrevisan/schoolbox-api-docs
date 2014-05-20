<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;


use Schoolbox\Realtime\Schedule\Command\Commander;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\ResourceBooking\Booking\Mapper;

class UserNotifier extends AbstractModuleNotifier {

	const TRIGGER_CREATED = 'created';
	const TRIGGER_UPDATED = 'updated';

	/**
	 * @return string
	 */
	protected function getSlug() {
		return 'user';
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		// noop
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		switch($bundle->getMethod()) {
			case Mapper::METHOD_CREATE:
				$bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
				break;
			case Mapper::METHOD_UPDATE:
				// we're only interested if extId changed
				if(array_key_exists('externalId', $bundle->getEvent()->getDiff())) {
					$bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
				}
				break;
		}
	}


	/**
	 * Override, we're only interested in one callback
	 *
	 * @param string $label
	 * @return array
	 */
	public function retrieveCallbacks($label) {
		return array(
			array($this, 'notify'.ucfirst($label))
		);
	}


	/**
	 * @param User $user
	 * @param User $effector
	 * @param array $diff
	 */
	public function notifyCreated($user, $effector, $diff = null) {
		Commander::adviseBindUser($user->getId());
		Commander::adviseSubscribeUser($user->getId());
	}


	/**
	 * @param User $user
	 * @param User $effector
	 * @param array $diff
	 */
	public function notifyUpdated($user, $effector, $diff = null) {
		// as above, only executed if externalId changed
		Commander::adviseBindUser($user->getId());
	}


} 