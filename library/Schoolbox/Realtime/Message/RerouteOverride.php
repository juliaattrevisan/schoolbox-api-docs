<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\User;

class RerouteOverride {

	/** @var User */
	protected $userId;

	/** @var string */
	protected $routeKey;

	/** @var int */
	protected $outgoingLevel;


	/**
	 * @param string $msgRouteKey
	 */
	public function setRouteKey($msgRouteKey) {
		$this->routeKey = $msgRouteKey;
	}

	/**
	 * @return string
	 */
	public function getRouteKey() {
		return $this->routeKey;
	}

	/**
	 * @param int $outgoingLevel
	 */
	public function setOutgoingLevel($outgoingLevel) {
		$this->outgoingLevel = $outgoingLevel;
	}

	/**
	 * @return int
	 */
	public function getOutgoingLevel() {
		return $this->outgoingLevel;
	}

	/**
	 * @param User $userId
	 */
	public function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * @return User
	 */
	public function getUserId() {
		return $this->userId;
	}



} 