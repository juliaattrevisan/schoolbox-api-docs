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

class Reroute {

	/** @var User */
	protected $userId;
	/** @var Source */
	protected $source;
	/** @var int */
	protected $outgoingLevel;

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
	 * @param \Schoolbox\Realtime\Message\Source $source
	 */
	public function setSource($source) {
		$this->source = $source;
	}

	/**
	 * @return \Schoolbox\Realtime\Message\Source
	 */
	public function getSource() {
		return $this->source;
	}

	/**
	 * @param \Schoolbox\User $userId
	 */
	public function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUserId() {
		return $this->userId;
	}

} 