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

	public function __construct($mqConnector = null) {
		$this->mqConnector = $mqConnector ?: new MQConnector();
		$this->userMapper = UserMapper::getInstance();
		$this->categoryMapper = CategoryMapper::getInstance();
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
	 * Build the queue for the given User, including binding generation
	 *
	 * @param User|int $user
	 * @return UserQueue
	 */
	public function buildUserQueue($user) {
		$user = $user instanceof User ? $user : $this->userMapper->findById($user);

		$bindings = array(
			// required user information
			'user' => array(
				'username'  => $user->getUsername(),
				'id'        => $user->getId()
			),
		);

		$cats = $this->categoryMapper->findForUser($user);

		foreach($cats as $cat) {
			/** @var Category $cat */
			$bindings['category'][] = $cat->getId();
		}


		$q = new UserQueue($bindings, $this->mqConnector);

		$q->getMessages();

		return $q;
	}

	/**
	 * Destroy all routing bindings for the given user
	 *
	 * @param User|int $user
	 */
	public function destoryUserBindings($user) {
		$user = $user instanceof User ? $user : $this->userMapper->findById($user);
		$exchange = MQConnector::SECONDARY_NAME_PREFIX . $user->getUsername();

		// unbind all routes
		$this->mqConnector->deleteExchange($exchange);
	}
} 