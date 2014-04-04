<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Role;

class RoutePermission {

	/** @var Source */
	protected $msgSource;
	/** @var int */
	protected $role;
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

	/** @return array */
	public function getLevels() {
		return array(
			'max' => $this->maxLevel,
			'default' => $this->defaultLevel,
			'min' => $this->minLevel
		);
	}

	/**
	 * @param Source $msgSource
	 */
	public function setMsgSource($msgSource) {
		$this->msgSource = $msgSource;
	}

	/**
	 * @return Source
	 */
	public function getMsgSource() {
		return $this->msgSource;
	}

	/**
	 * @param Role $role
	 */
	public function setRole($role) {
		$this->role = $role;
	}

	/**
	 * @return Role
	 */
	public function getRole() {
		return $this->role;
	}



} 