<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;
use Group;
use Schoolbox\Campus;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Handles Dependency Injection for the UserQueue class
 *
 * Class UserQueueFactory
 * @package Schoolbox\Notif\Queue
 */
class UserQueueFactory {

	/** @var MQConnector */
	protected $mqConnector;

	/** @var UserMapper */
	protected $userMapper;

	/** @var CategoryMapper */
	protected $categoryMapper;

	/** @var RoleMapper */
	protected $roleMapper;

	/** @var \Group */
	protected $_DEPRECATED_GROUP;

	/** @var \External_User */
	protected $_DEPRECATED_EXT_USER;

	/** @var array */
	protected $roleCache;

	/** @var array */
	protected $existingBindings = null;

	public function __construct($mqConnector = null) {
		$this->mqConnector = $mqConnector ?: new MQConnector();
		$this->userMapper = UserMapper::getInstance();
		$this->categoryMapper = CategoryMapper::getInstance();
		$this->roleMapper = RoleMapper::getInstance();
		$this->_DEPRECATED_GROUP = new \Group();
		$this->_DEPRECATED_EXT_USER = new \External_User();

		// cache roles
		$this->roleCache = $this->roleMapper->findAll()->groupBy('type')->getAll();
		foreach($this->roleCache as $roleType => $roleCollection) {
			$this->roleCache[$roleType] = $roleCollection->getColumnValues('id');
		}

		// don't forget the magic!
		$this->_DEPRECATED_EXT_USER->getCurrentSemester(/*$user->getExternalId()*/);

	}

	public function setMessageQueueConnector($mqConnector) {
		$this->mqConnector = $mqConnector;
		return $this;
	}

	public function setUserMapper($userMapper) {
		$this->userMapper = $userMapper;
		return $this;
	}

	public function setCategoryMapper($categoryMapper) {
		$this->categoryMapper = $categoryMapper;
		return $this;
	}

	/**
	 * Creates bindings for a User's queue, but doesn't actually attempt message retrieval.
	 * @param $user
	 * @param bool $simulate
	 * @return UserQueue
	 */
	public function bindUserQueue($user, $simulate = false) {
		$user = $user instanceof User ? $user : $this->userMapper->findById($user);
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
				'username'  => $user->getUsername(),
				// *.notify.user.{id}
				'id'        => $user->getId()
			),
		);

		$roleType = $user->getRole()->getType();

		// superuser bindings
		if($user->isSuperuser()) {
			// *.notify.superuser.#
			$bindings['superuser'] = array('#');
		}

		// campus bindings
		foreach($user->getCampusCollection() as $campus) {
			// *.notify.campus.{id}
			// *.notify.campus.{id}.{roletype}
			/** @var Campus $campus */
			$bindings['campus'][] = $campus->getId();
			$bindings['campus'][] = $campus->getId() . ".$roleType";
		}

		// house bindings
		if($house = $user->getSchoolHouse()) {
			// *.notify.house.{id}
			// *.notify.house.{id}.{roletype}
			$bindings['house'] = array(
				$house,
				$house . ".$roleType"
			);
		}


		// residential house bindings
		if($resHouse = $user->getResidentialHouse()) {
			// *.notify.reshouse.{id}
			// *.notify.reshouse.{id}.{roletype}
			$bindings['reshouse'] = array(
				$resHouse,
				$resHouse . ".$roleType"
			);
		}


		// category bindings

		// TODO REFACTOR: THIS METHOD TAKES ABOUT 400MS TO EXECUTE
		$cats = $this->categoryMapper->findForUser($user);

		foreach($cats as $cat) {
			/** @var Category $cat */
			// category general participation
			// *.notify.category.{id}
			// *.notify.category.{id}.{roletype}
			$bindings['category'][] = $cat->getId();
			$bindings['category'][] = $cat->getId() . ".$roleType";

			// category admin
			// *.notify.category.{id}.admin
			$catOwner = $this->_DEPRECATED_GROUP->getOwner($cat->getId());

			if($catOwner['uid'] == $user->getId()) {
				$bindings['category'][] = $cat->getId() . ".admin";
			}

			// category component bindings
			// TODO this one is more complicated


		}

		// parental bindings TODO can this be more lightweight
		if($roleType == Role::PARENT) {
			$childrenArray = $this->_DEPRECATED_EXT_USER->getEnrolledStudents($user->getExternalId());
			if(is_array($childrenArray)) {
				$childIds = array();
				foreach($childrenArray as $childArray) {
					$childIds[] = $childArray['RelatedID'];
				}
				if(array_key_exists(Role::STUDENT, $this->roleCache)) {
					$childCollection = $this->userMapper->findByColumnList(array(
						'synergy_id' => $childIds,
						'group_id' => $this->roleCache[Role::STUDENT]
					));
					$bindings['parent'] = $childCollection->getColumnValues('id');
				}
			}
		}

		// role bindings
		// *.notify.role.{id}
		$bindings['role'] = array($user->getRole()->getId());

		// role-type bindings
		// *.notify.roletype.{name}
		$bindings['roletype'] = array($roleType);

		return $bindings;
	}


	/**
	 * Build the queue for the given User, including binding generation
	 *
	 * @param User|int $user
	 * @param int $limit
	 * @param bool $bind
	 * @return UserQueue
	 */
	public function buildUserQueue($user, $limit = 10, $bind = true) {
		if($bind) {
			$q = $this->bindUserQueue($user);
		}
		else {
			$q = new UserQueue(array('user' => array(
				'username' => $user->getUsername(),
				'id'       => $user->getId()
			)), $this->mqConnector);
		}
		$q->getMessages($limit);
		return $q;
	}

	/**
	 * Destroy all routing bindings for the given user.
	 * Returns the number of bindings deleted, or -1 on error.
	 *
	 * @param User|int $user
	 * @return int
	 */
	public function destoryUserBindings($user, $simulate = false) {

		if(!$this->existingBindings) {
			$this->cacheExistingBindings();
		}

		$user = $user instanceof User ? $user : $this->userMapper->findById($user);
		$username = $user->getUsername();

		if(!isset($this->existingBindings[$username])) {
			return 0;
		}

		$count = 0;

		if(!$simulate) {
			$userExchange = Connector::SECONDARY_NAME_PREFIX . $username;

			foreach($this->existingBindings[$username] as $routingKey) {
				$this->mqConnector->unbindExchange($userExchange, $routingKey);
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

		$allBindings = `rabbitmqctl list_bindings destination_name routing_key`;

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

			$prefix = Connector::SECONDARY_NAME_PREFIX;

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
}