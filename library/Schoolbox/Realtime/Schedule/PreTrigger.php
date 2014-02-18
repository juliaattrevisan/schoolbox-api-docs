<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Schoolbox\User;

class PreTrigger {

	protected $object;
	protected $user;

	/**
	 * @param object $object
	 * @param User $user
	 */
	public function __construct($object, $user) {
		$this->object = $object;
		$this->user = $user;
	}

	/**
	 * @return object
	 */
	public function getObject() {
		return $this->object;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUser() {
		return $this->user;
	}


} 