<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Collection;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\Message\Instance\Instance;
use Schoolbox\Notif\Message\Instance\InstanceBundle;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\NotifException;
use Schoolbox\User;
use SplObjectStorage;

class Messenger {

	/** @var SplObjectStorage */
	protected $splStore;


    public function __construct() {
		$this->splStore = new SplObjectStorage();
	}

	/**
	 * @param Message $message
	 * @param int $severity
	 * @param array $topics
	 * @param array $targets
	 * @param array $params
	 * @param array $context
	 * @throws \Schoolbox\Notif\NotifException
	 */
	protected function bind($message, $severity, $topics = array(), $targets = array(), $params = array(), $context = array()) {
		if(count(array_diff(array_keys($params), $message->getTokenList()))) {
			throw new NotifException("Parameters supplied must exactly match the :tokens in the message string.");
		}

		// cast string to array if required
		$topics = (array)$topics;
		$targets = (array)$targets;
		$context = (array)$context;

		// insert the message object if it's not already in our collection
		if(!$this->splStore->contains($message)) {
			$this->splStore->attach($message, new InstanceBundle());
		}

        /** @var InstanceBundle $instances */
		$instances = $this->splStore->offsetGet($message);
        $instances->attach(new Instance($params, $severity, $topics, $targets, $context));
	}


	public function broadcastToCategory($catId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "category.$catId", $params, $filter);
	}

	public function broadcastToCategoryAdmin($catId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "category.$catId.admin", $params, $filter);
	}

	public function broadcastToCampus($campusId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "campus.$campusId", $params, $filter);
	}

	public function broadcastToRole($roleId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "role.$roleId", $params, $filter);
	}

	public function broadcastToRoleType($roleType, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "roletype.$roleType", $params, $filter);
	}

	public function broadcastToHouse($houseId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "house.$houseId", $params, $filter);
	}

	public function broadcastToResidentialHouse($resHouseId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "reshouse.$resHouseId", $params, $filter);
	}

	public function broadcastToSuperusers($message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bind($message, $severity, $topic, "superuser", $params, $filter);
	}

	public function targetToParent($studentId, $message, $topic, $severity, $params = array()) {
		$this->bind($message, $severity, $topic, "parent.$studentId", $params);
	}

	public function targetToUser($userId, $message, $topic, $severity, $params = array()) {
		$this->bind($message, $severity, $topic, "user.$userId", $params);
	}


	/**
	 * Whether or not this collection currently contains messages
	 *
	 * @return bool
	 */
	public function hasMessages() {
		return !!$this->splStore->count();
	}


	/**
	 * @param Notif $notif
	 * @param TriggerInterface $trigger
	 * @param callable $callback
	 * @return void
	 */
	protected function applyInstances($notif, $trigger, $callback) {
		if(!$this->hasMessages()) {
			return;
		}

		foreach($this->splStore as $msg) {
			/**
			 * @var Message $msg
			 * @var InstanceBundle $instanceBundle
			 */
			$instanceBundle = $this->splStore->offsetGet($msg);

			foreach($instanceBundle->getInstances() as $instance) {
				$messageInstance = $msg
					->applyInstance($instance, $notif, $trigger)
					->setTargets($instance->getTargets())
					->setContext($instance->getContext());

				// if we can filter out the user who effected this event in the first place, do so
				if($trigger) {
					$effector = $trigger->getEvent()->getEffector();
					if($effector instanceof User) {
						$messageInstance->setContext(array('user' => $effector->getId()));
					}
				}

				call_user_func($callback, $messageInstance);
			}
		}
	}

    /**
     * Send all the messages contained by this Messenger through the given Notif
     *
     * @param Notif $notif
     * @param TriggerInterface $trigger
     * @return array
     */
    public function send($notif, $trigger = null) {
		$result = 0;

		$this->applyInstances($notif, $trigger, function($instance) use (&$result) {
			if($instance->send()) {
				$result++;
			}
		});

		return $result;
    }


	/**
	 * Extracts all contained messages into a collection
	 *
	 * @param Notif $notif
	 * @param TriggerInterface $trigger
	 * @return Collection|TargetedMessage[]
	 */
	public function extractAll($notif = null, $trigger = null) {
		$c = new Collection();

		$this->applyInstances($notif, $trigger, function($instance) use (&$c) {
			$c->add($instance);
		});

		return $c;
	}

} 