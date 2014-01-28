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
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\GroupMapper;
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

	/** @var \Group */
	protected $_DEPRECATED_GROUP;

	public function __construct($mqConnector = null) {
		$this->mqConnector = $mqConnector ?: new MQConnector();
		$this->userMapper = UserMapper::getInstance();
		$this->categoryMapper = CategoryMapper::getInstance();
		$this->_DEPRECATED_GROUP = new \Group();
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
	 * @return UserQueue
	 */
	public function bindUserQueue($user) {
		$user = $user instanceof User ? $user : $this->userMapper->findById($user);
		return new UserQueue($this->generateBindingsForUser($user), $this->mqConnector);
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

		// superuser bindings
		if($user->isSuperuser()) {
			// *.notify.superuser.#
			$bindings['superuser'] = array('#');
		}

		// campus bindings
		foreach($user->getCampusCollection() as $campus) {
			// *.notify.campus.{id}

			/** @var Campus $campus */
			$bindings['campus'][] = $campus->getId();
		}

		// house bindings
		if($house = $user->getSchoolHouse()) {
			// *.notify.house.{id}
			$bindings['house'] = array($house);
		}


		// residential house bindings
		if($resHouse = $user->getResidentialHouse()) {
			// *.notify.reshouse.{id}
			$bindings['reshouse'] = array($resHouse);
		}


		// category bindings
		$cats = $this->categoryMapper->findForUser($user);

		foreach($cats as $cat) {
			/** @var Category $cat */
			// category general participation
			// *.notify.category.{id}
			$bindings['category'][] = $cat->getId();

			// category admin
			// *.notify.category.{id}.admin
			$catOwner = $this->_DEPRECATED_GROUP->getOwner($cat->getId());

			if($catOwner['uid'] == $user->getId()) {
				$bindings['category'][] = $cat->getId() . ".admin";
			}

			// category component bindings
			// TODO this one is more complicated


		}

		// role bindings
		// *.notify.role.{id}
		$bindings['role'] = array($user->getRole()->getId());

		// role-type bindings
		// *.notify.roletype.{name}
		$bindings['roletype'] = array($user->getRole()->getType());

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
	 * Destroy all routing bindings for the given user
	 *
	 * @param User|int $user
	 */
	public function destoryUserBindings($user) {
//		$user = $user instanceof User ? $user : $this->userMapper->findById($user);
//		$exchange = MQConnector::SECONDARY_NAME_PREFIX . $user->getUsername();

		// unbind all routes
//		$this->mqConnector->deleteExchange($exchange);
	}
} 