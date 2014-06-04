<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\Async\Notification\Parameter\Param;

/**
 * Class Messenger
 *
 * @deprecated this will continue to work, but for more flexibility use Schoolbox\Realtime\Notification\Envelope
 * @package Schoolbox\Notif\Message
 */
class Messenger extends InstanceBundle {

	/** @var Builder */
	protected $builder;


	/**
	 * Add builder to construction
	 *
	 * @deprecated this will continue to work, but for more flexibility use Schoolbox\Realtime\Notification\Envelope
	 */
	public function __construct() {
		parent::__construct();
		$this->builder = new Builder();
	}


	/**
	 * @param string $target
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filters
	 */
	protected function bindMessage($target, $message, $topic, $severity, $params, $filters) {
		$this->builder->clear()
			->setTitle($message->getMessageString())
			->setIcon($message->getIcon())
			->setHref($message->getHref())
			->setSeverity($severity)
			->setNamespace(substr($topic, 0, strpos($topic, '.')))
			->addTopic($topic);

		foreach($params as $k => $p) {
			if(is_array($p)) {
				$this->builder->setParam($k, new Param($p[0], $p[1]));
			}
			else {
				$this->builder->setParam($k, new Param($p));
			}
		}

		foreach($filters as $k => $f) {
			$this->builder->addFilter($k, $f);
		}

		$this->bind($target, $this->builder->build());
	}

	// MAINTAIN OLD API FUNCTION SIGNATURES

	/**
	 * @param int $catId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToCategory($catId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("category.$catId", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param int $catId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToCategoryAdmin($catId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("category.$catId.admin", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param int $campusId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToCampus($campusId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("campus.$campusId", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param int $roleId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToRole($roleId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("role.$roleId", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param int $roleType
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToRoleType($roleType, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("roletype.$roleType", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param int $houseId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToHouse($houseId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("house.$houseId", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * Broadcasts a message to all users with permissions on a particular
	 * service.
	 *
	 * @param int     $service     The service to broadcast to: one of the
	 *                             UserAccess::SERVICE_* constants.
	 * @param int     $identifier  The identifying key of the service: usually
	 *                             this is a campus ID.
	 * @param int     $accessLevel The access level to broadcast to: one of the
	 *                             UserAccess::ACCESS_* constants.
	 *                             Messages broadcast to lower access levels are
	 *                             also sent to higher level users.
	 * @param Message $message
	 * @param string  $topic
	 * @param string  $severity
	 * @param array   $params
	 * @param array   $filter
	 */
	public function broadcastToService($service, $identifier, $accessLevel, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("service.$service.$identifier.$accessLevel", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param int $resHouseId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToResidentialHouse($resHouseId, $message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("reshouse.$resHouseId", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 * @param array $filter
	 */
	public function broadcastToSuperusers($message, $topic, $severity, $params = array(), $filter = array()) {
		$this->bindMessage("superuser", $message, $topic, $severity, $params, $filter);
	}


	/**
	 * @param int $studentId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 */
	public function targetToParent($studentId, $message, $topic, $severity, $params = array()) {
		$this->bindMessage("parent.$studentId", $message, $topic, $severity, $params, array());
	}


	/**
	 * @param int $userId
	 * @param Message $message
	 * @param string $topic
	 * @param int $severity
	 * @param array $params
	 */
	public function targetToUser($userId, $message, $topic, $severity, $params = array()) {
		$this->bindMessage("user.$userId", $message, $topic, $severity, $params, array());
	}


	/**
	 * Whether or not this collection currently contains messages
	 *
	 * @return bool
	 */
	public function isEmpty() {
		return !$this->count();
	}

} 