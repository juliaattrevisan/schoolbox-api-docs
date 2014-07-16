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
use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\AMQP\Bunny\ControlInterface;
use Alaress\Async\Notification\Instance\Instance;
use External_User;
use External_UserInterface;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Message\External\TargetedConverter;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
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

	/** @var ControlInterface */
	protected $bunnyController;

	/** @var UserMapper */
	protected $userMapper;

	/** @var CategoryMapper */
	protected $categoryMapper;

	/** @var RoleMapper */
	protected $roleMapper;

	/** @var UserAccessMapper */
	protected $userAccessMapper;

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
		$this->setMessageQueueConnector($mqConnector ?: MQConnector::getInstance());
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
	 * @return AMQPInterface
	 */
	public function getMessageQueueConnector() {
		return $this->mqConnector;
	}


	/**
	 * @param ControlInterface $controller
	 * @return self
	 */
	public function setRabbitController($controller) {
		$this->bunnyController = $controller;
		return $this;
	}


	/**
	 * @return ControlInterface
	 */
	public function getRabbitController() {
		if(!$this->bunnyController) {
			$this->setRabbitController(new BunnyControl());
		}
		return $this->bunnyController;
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
	 * @return External_UserInterface
	 */
	public function GET_DEPRECATED_EXT_USER() {
		if(!$this->_DEPRECATED_EXT_USER) {
			$this->SET_DEPRECATED_EXT_USER(new External_User());
			$this->_DEPRECATED_EXT_USER->getCurrentSemester();
		}

		return $this->_DEPRECATED_EXT_USER;
	}


	/**
	 * @param External_UserInterface $externalUserHandler
	 * @return self
	 */
	public function SET_DEPRECATED_EXT_USER($externalUserHandler) {
		$this->_DEPRECATED_EXT_USER = $externalUserHandler;
		return $this;
	}


	/**
	 * @param UserAccessMapper $userAccessMapper
	 * @return self
	 */
	public function setUserAccessMapper($userAccessMapper) {
		$this->userAccessMapper = $userAccessMapper;
		return $this;
	}


	/**
	 * @return UserAccessMapper
	 */
	public function getUserAccessMapper() {
		if(!$this->userAccessMapper) {
			$this->setUserAccessMapper(UserAccessMapper::getInstance());
		}
		return $this->userAccessMapper;
	}


	/**
	 * @return RoleMapper
	 */
	public function getRoleMapper() {
		if(!$this->roleMapper) {
			$this->setRoleMapper(RoleMapper::getInstance());
		}
		return $this->roleMapper;
	}


	/**
	 * @param \Schoolbox\RoleMapper $roleMapper
	 * @return self
	 */
	public function setRoleMapper($roleMapper) {
		$this->roleMapper = $roleMapper;
		return $this;
	}


	/**
	 * @return \Schoolbox\UserMapper
	 */
	public function getUserMapper() {
		if(!$this->userMapper) {
			$this->setUserMapper(UserMapper::getInstance());
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
			$this->setCategoryMapper(CategoryMapper::getInstance());
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
					foreach($this->roleCache as $rType => $roleCollection) {
						/** @var $roleCollection Collection|Role[] */
						$this->roleCache[$rType] = $roleCollection->getColumnValues('id');
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

		try {
			// retrieve all homepages the user has access to; pull homepage service access from synergy as well
			$catIds = $this->getCategoryMapper()->findForUser($user)->getColumnValues('id');

			/** @var UserAccess $access */
			$accessList = $this->getUserAccessMapper()->findByColumnList(array(
				'uid'     => $user->getId(),
				'service' => UserAccess::SERVICE_HOMEPAGE,
				'sid'     => $catIds
			));
		}
		catch(MapperException $e) {
			$accessList = new Collection();
		}

		// combine with all other services user has access to
		$accessList->mergeUnique($user->getAccessList());

		foreach ($user->getAccessList() as $access) {
			$baseBinding = $access->getService().'.'.$access->getServiceIdentifier();
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
	public function destroyUserBindings($userId, $simulate = false) {

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

		try {
			$bindings = $this->getRabbitController()->listBindings(array('destination_name', 'routing_key'));
		}
		catch(RuntimeException $e) {
			return -1;
		}

		if(!count($bindings)) {
			return 0;
		}

		$count = 0;
		$prefix = $this->mqConnector->getSecondaryExchange('')->getName();

		foreach($bindings as $binding) {
			// only parse line if this is a user exchange
			if(substr($binding['destination_name'], 0, strlen($prefix)) != $prefix) {
				continue;
			}

			$username = substr($binding['destination_name'], strlen($prefix));

			$this->existingBindings[$username][] = $binding['routing_key'];

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

					if($instance->getBaseHash() == $compare->getBaseHash()) {
						if($instance->getParamHash() == $compare->getParamHash()) {
							$result = true;
							break;
						}
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
