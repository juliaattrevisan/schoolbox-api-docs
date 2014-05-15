<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;


use Schoolbox\User;

class UserAccess {

	/** @var User */
	protected $user;

	/** @var int */
	protected $service;

	/** @var int */
	protected $sid;

	/** @var int */
	protected $type;

	/**
	 * @param User $user
	 * @return self
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}


	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}


	/**
	 * @param int $service
	 * @return self
	 */
	public function setService($service) {
		$this->service = $service;
		return $this;
	}


	/**
	 * @return int
	 */
	public function getService() {
		return $this->service;
	}


	/**
	 * @param int $sid
	 * @return self
	 */
	public function setServiceIdentifier($sid) {
		$this->sid = $sid;
		return $this;
	}


	/**
	 * @return int
	 */
	public function getServiceIdentifier() {
		return $this->sid;
	}


	/**
	 * @param int $type
	 * @return self
	 */
	public function setType($type) {
		$this->type = $type;
		return $this;
	}


	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}


} 