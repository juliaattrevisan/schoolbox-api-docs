<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


class RoutePermission {

	/** @var int */
	protected $msgSourceId;
	/** @var int */
	protected $roleId;
	/** @var int */
	protected $minLevel;
	/** @var int */
	protected $maxLevel;
	/** @var int */
	protected $defaultLevel;

	/**
	 * @param int $defaultLevel
	 */
	public function setDefaultLevel($defaultLevel) {
		$this->defaultLevel = $defaultLevel;
	}

	/**
	 * @return int
	 */
	public function getDefaultLevel() {
		return $this->defaultLevel;
	}

	/**
	 * @param int $maxLevel
	 */
	public function setMaxLevel($maxLevel) {
		$this->maxLevel = $maxLevel;
	}

	/**
	 * @return int
	 */
	public function getMaxLevel() {
		return $this->maxLevel;
	}

	/**
	 * @param int $minLevel
	 */
	public function setMinLevel($minLevel) {
		$this->minLevel = $minLevel;
	}

	/**
	 * @return int
	 */
	public function getMinLevel() {
		return $this->minLevel;
	}

	/**
	 * @param int $msgSourceId
	 */
	public function setMsgSourceId($msgSourceId) {
		$this->msgSourceId = $msgSourceId;
	}

	/**
	 * @return int
	 */
	public function getMsgSourceId() {
		return $this->msgSourceId;
	}

	/**
	 * @param int $roleId
	 */
	public function setRoleId($roleId) {
		$this->roleId = $roleId;
	}

	/**
	 * @return int
	 */
	public function getRoleId() {
		return $this->roleId;
	}



} 