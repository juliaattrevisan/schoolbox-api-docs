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

	public function __construct($mqConnector = null) {
		$this->mqConnector = $mqConnector ?: new MQConnector();
		$this->userMapper = UserMapper::getInstance();
		$this->categoryMapper = CategoryMapper::getInstance();
		$this->roleMapper = RoleMapper::getInstance();
		$this->_DEPRECATED_GROUP = new \Group();
		$this->_DEPRECATED_EXT_USER = new \External_User();
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

		// don't forget the magic!
		$this->categoryMapper->getExternalUser()->getCurrentSemester($user->getExternalId());
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
				$childCollection = $this->userMapper->findByColumnList(array(
					'synergy_id' => $childIds,
					'group_id' => $this->roleMapper->findByColumn('group_type', Role::STUDENT)->getColumnValues('id')
				));
				$bindings['parent'] = $childCollection->getColumnValues('id');
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
	 * @return UserQueue
	 */
	public function buildUserQueue($user) {
		$q = $this->bindUserQueue($user);
		$q->getMessages();
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

		// there's a bit of black magic in here
		if(posix_getuid() !== 0) {
			// this method can only succeed if the user is root - don't even try otherwise
			return -1;
		}

		$user = $user instanceof User ? $user : $this->userMapper->findById($user);

		$userExchange = Connector::SECONDARY_NAME_PREFIX . $user->getUsername();

		$bindings = `rabbitmqctl list_bindings destination_name routing_key | grep $userExchange`;

		if(!$bindings) {
			return 0;
		}

		$bindings = explode("\n", $bindings);

		if(!count($bindings)) {
			return 0;
		}

		$count = 0;

		foreach($bindings as $binding) {

//			echo bin2hex(print_r($binding, true));

			$xpl = preg_split('/\s+/', $binding);

			if(count($xpl) != 2) {
				print_r($binding);
				continue;
			}

			list($exchange, $routingKey) = $xpl;

			if(!$simulate) {
				$this->mqConnector->unbindExchange($exchange, $routingKey);
			}

			++$count;
		}

		return $count;
	}

} 