<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;

use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Instance\Instance;
use Exception;
use External_User;
use Group;
use InvalidArgumentException;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Notif\Message\External\TargetedConverter;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserAccess;
use Schoolbox\UserMapper;

/**
 * Handles Dependency Injection for the UserQueue class
 *
 * Class UserQueueFactory
 * @package Schoolbox\Notif\Queue
 */
class UserQueueFactory {

	/** @var AMQPInterface */
	protected $mqConnector;

	/** @var UserMapper */
	protected $userMapper;

	/** @var CategoryMapper */
	protected $categoryMapper;

	/** @var RoleMapper */
	protected $roleMapper;

	/** @var Group */
	protected $_DEPRECATED_GROUP;

	/** @var External_User */
	protected $_DEPRECATED_EXT_USER;

	/** @var Collection[] */
	protected $roleCache;

	/** @var array */
	protected $existingBindings = null;


	/**
	 * @param AMQPInterface $mqConnector
	 */
	public function __construct($mqConnector = null) {
		$this->setMessageQueueConnector($mqConnector ?: new MQConnector());
	}


	/**
	 * @param AMQPInterface $mqConnector
	 * @return self
	 */
	public function setMessageQueueConnector($mqConnector) {
		$this->mqConnector = $mqConnector;
		return $this;
	}


	/**
	 * @param UserMapper $userMapper
	 * @return self
	 */
	public function setUserMapper($userMapper) {
		$this->userMapper = $userMapper;
		return $this;
	}


	/**
	 * @return External_User
	 */
	public function GET_DEPRECATED_EXT_USER() {
		if(!$this->_DEPRECATED_EXT_USER) {
			$this->_DEPRECATED_EXT_USER = new External_User();
			$this->_DEPRECATED_EXT_USER->getCurrentSemester();
		}

		return $this->_DEPRECATED_EXT_USER;
	}


	/**
	 * @return Group
	 */
	public function GET_DEPRECATED_GROUP() {
		if(!$this->_DEPRECATED_GROUP) {
			$this->_DEPRECATED_GROUP = new Group();
		}
		return $this->_DEPRECATED_GROUP;
	}


	/**
	 * @return RoleMapper
	 */
	public function getRoleMapper() {
		if(!$this->roleMapper) {
			$this->roleMapper = RoleMapper::getInstance();
		}
		return $this->roleMapper;
	}


	/**
	 * @return \Schoolbox\UserMapper
	 */
	public function getUserMapper() {
		if(!$this->userMapper) {
			$this->userMapper = UserMapper::getInstance();
		}
		return $this->userMapper;
	}


	/**
	 * @param CategoryMapper $categoryMapper
	 * @return self
	 */
	public function setCategoryMapper($categoryMapper) {
		$this->categoryMapper = $categoryMapper;
		return $this;
	}


	/**
	 * @return CategoryMapper
	 */
	public function getCategoryMapper() {
		if(!$this->categoryMapper) {
			$this->categoryMapper = CategoryMapper::getInstance();
		}
		return $this->categoryMapper;
	}


	/**
	 * Creates bindings for a User's queue, but doesn't actually attempt message retrieval.
	 * @param User|int $user
	 * @param bool $simulate
	 * @return UserQueue
	 */
	public function bindUserQueue($user, $simulate = false) {
		$user = $user instanceof User ? $user : $this->getUserMapper()->findById($user);
		return new UserQueue($this->generateBindingsForUser($user), $this->mqConnector, $simulate);
	}


	/**
	 * @param User $user
	 * @return array
	 */
	public function generateBindingsForUser($user) {

		$bindings = array(
			// required user information
			'user' => array(
				// *.notify.user.{id}.#
				'id' => $user->getId()
			)
		);

		$roleType = $user->getRole()->getType();

		// superuser bindings
		if($user->isSuperuser()) {
			// *.notify.superuser.#
			$bindings['superuser'] = array('#');
		}

		// campus bindings
		foreach($user->getCampusCollection() as $campus) {
			// *.notify.campus.{id}.#
			/** @var Campus $campus */
			$bindings['campus'][] = $campus->getId() . ".#";
		}

		// house bindings
		if($house = $user->getSchoolHouse()) {
			// *.notify.house.{id}.#
			$bindings['house'] = array(
				"$house.#"
			);
		}


		// residential house bindings
		if($resHouse = $user->getResidentialHouse()) {
			// *.notify.reshouse.{id}.#
			$bindings['reshouse'] = array(
				"$resHouse.#"
			);
		}

		// parental bindings TODO can this be more lightweight
		if($roleType == Role::PARENT && $user->getExternalId() > 0 ) {
			$childrenArray = $this->GET_DEPRECATED_EXT_USER()->getEnrolledStudents($user->getExternalId());
			if(is_array($childrenArray)) {
				$childIds = array();
				foreach($childrenArray as $childArray) {
					$childIds[] = $childArray['RelatedID'];
				}
				// this is the only time we need to actually look at the role cache, so create it on demand
				if(empty($this->roleCache)) {
					// cache roles
					$this->roleCache = $this->getRoleMapper()->findAll()->groupBy('type')->getAll();
					foreach($this->roleCache as $roleType => $roleCollection) {
						/** @var $roleCollection Collection|Role[] */
						$this->roleCache[$roleType] = $roleCollection->getColumnValues('id');
					}
				}
				if(array_key_exists(Role::STUDENT, $this->roleCache)) {
					$childCollection = $this->getUserMapper()->findByColumnList(array(
						'synergy_id' => $childIds,
						'group_id' => $this->roleCache[Role::STUDENT]
					));
					// parent bindings
					// *.notify.parent.{childId}.#
					$bindings['parent'] = $childCollection->getColumnValues('id');
				}
			}
		}

		// role bindings
		// *.notify.role.{id}.#
		$bindings['role'] = array($user->getRole()->getId().".#");

		// role-type bindings
		// *.notify.roletype.{name}.#
		$bindings['roletype'] = array("$roleType.#");

		// Bindings for services:
		// *.notify.service.{service}.{itemId}.{level}.#
		// Higher access levels get lower level bindings as well.
		$bindings['service'] = array();
		foreach ($user->getAccessList() as $access) {
			$baseBinding = $access->getService().'.'.$access->getItemId();
			$accessLevel = $access->getAccessLevel();
			if ($accessLevel >= UserAccess::ACCESS_FULL) {
				$bindings['service'][] = $baseBinding.'.'.UserAccess::ACCESS_FULL.'.#';
			}
			if ($accessLevel >= UserAccess::ACCESS_WRITE) {
				$bindings['service'][] = $baseBinding.'.'.UserAccess::ACCESS_WRITE.'.#';
			}
			if ($accessLevel >= UserAccess::ACCESS_READ) {
				$bindings['service'][] = $baseBinding.'.'.UserAccess::ACCESS_READ.'.#';
			}
		}

		// Additional service bindings: pull homepage service access from synergy as well

		/** @var Collection|Category[] $cats */
		$cats = $this->getCategoryMapper()->findForUser($user);

		foreach($cats as $cat) {
			$catOwner = $this->GET_DEPRECATED_GROUP()->getOwner($cat->getId());

			// it doesn't matter if any of these duplicate any created above
			$baseBinding = UserAccess::SERVICE_HOMEPAGE.'.'.$cat->getId();
			if($catOwner['uid'] == $user->getId()) {
				$bindings['service'][] = $baseBinding.'.'.UserAccess::ACCESS_FULL.'.#';
			}
			$bindings['service'][] = $baseBinding.'.'.UserAccess::ACCESS_READ.'.#';
		}


		return $bindings;
	}


	/**
	 * Build the queue for the given User, including binding generation
	 *
	 * @param int $userId
	 * @param int $limit
	 * @param bool $bind
	 * @return UserQueue
	 */
	public function buildUserQueue($userId, $limit = 10, $bind = true) {
		if($bind) {
			$q = $this->bindUserQueue($userId);
		}
		else {
			$q = new UserQueue(array('user' => array(
				'id' => $userId
			)), $this->mqConnector);
		}
		$q->getNotifications($limit);
		return $q;
	}


	/**
	 * Destroy all routing bindings for the given user.
	 * Returns the number of bindings deleted, or -1 on error.
	 *
	 * @param int $userId
	 * @param bool $simulate
	 * @return int
	 */
	public function destoryUserBindings($userId, $simulate = false) {

		if(!$this->existingBindings) {
			$this->cacheExistingBindings();
		}

		if(!isset($this->existingBindings[$userId])) {
			return 0;
		}

		$count = 0;

		if(!$simulate) {
			$userExchange = $this->mqConnector->getSecondaryExchange($userId);

			foreach($this->existingBindings[$userId] as $routingKey) {
				$this->mqConnector->unbindExchange($userExchange->getName(), $routingKey);
				++$count;
			}
		}

		return $count;
	}


	/**
	 * Retrieve all existing bindings from RabbitMQ and cache into an array, indexed by username
	 *
	 * @return int
	 */
	protected function cacheExistingBindings() {
		$this->existingBindings = array();

		// there's a bit of black magic in here
		if(posix_getuid() !== 0) {
			// this method can only succeed if the user is root - don't even try otherwise
			return -1;
		}

		$allBindings = `/usr/sbin/rabbitmqctl list_bindings destination_name routing_key`;

		if(!$allBindings) {
			return 0;
		}

		$bindings = explode("\n", $allBindings);

		if(!count($bindings)) {
			return 0;
		}

		$count = 0;

		foreach($bindings as $binding) {

			$xpl = preg_split('/\s+/', $binding);

			if(count($xpl) != 2) {
				continue;
			}

			list($exchange, $routingKey) = $xpl;

			$prefix = $this->mqConnector->getSecondaryExchange('')->getName();

			// only parse line if this is a user exchange
			if(substr($exchange, 0, strlen($prefix)) != $prefix) {
				continue;
			}

			$username = substr($exchange, strlen($prefix));

			$this->existingBindings[$username][] = $routingKey;

			++$count;
		}

		return $count;
	}


	/**
	 * Returns whether or not a given message is sitting in the given user's listed or digest queues.
	 * Use this method shrewdly - its expense is proportional to the length of the given user's queues.
	 *
	 * @param int $userId
	 * @param Instance $instance
	 * @throws InvalidArgumentException
	 * @return bool
	 */
	public function userHasSeen($userId, $instance) {
		$result = false;

		$qs = array(
			UserQueue::getUserListedQueueName($userId),
			UserQueue::getUserDigestQueueName($userId),
		);

		foreach($qs as $q) {
			$nack = array();
			try {
				while($m = $this->mqConnector->readFromQueue($q)) {
					// store list of retrieved messages to NACK later
					$nack[] = $m;
					$unjson = json_decode($m->body, true);

					try {
						$compare = Instance::jsonUnserialize($unjson['message']);
					}
					catch(InvalidArgumentException $e) {
						// TODO once TargetedMessage fully deprecated this catch can be removed
						$conv = new TargetedConverter();
						$compare = $conv->convert($unjson['message']);
					}

					if($instance->getParamHash() == $compare->getParamHash()) {
						$result = true;
						break;
					}
				}
				// push any messages we retrieved back onto the queue
				$this->mqConnector->rejectMessages($nack);
			}
			catch(InvalidArgumentException $e) {
				// regardless of anything else, you MUST reject the messages you retrieved
				$this->mqConnector->rejectMessages($nack);
				throw $e;
			}

			// if we returned true, don't bother continuing
			if($result) {
				break;
			}
		}

		return $result;
	}

}
