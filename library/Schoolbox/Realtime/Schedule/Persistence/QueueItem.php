<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Persistence;


class QueueItem {

	/** @var object */
	protected $identifier;

	/** @var string */
	protected $command;

	/** @var array */
	protected $data;


	/**
	 * @param string $command
	 */
	public function setCommand($command) {
		$this->command = $command;
	}


	/**
	 * @return string
	 */
	public function getCommand() {
		return $this->command;
	}


	/**
	 * @param array $data
	 */
	public function setData($data) {
		$this->data = $data;
	}


	/**
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}


	/**
	 * @param string $hash
	 */
	public function setHash($hash) {
		// noop: dynamic field
	}


	/**
	 * @return string
	 */
	public function getHash() {
		return sha1(serialize($this->getIdentifier()));
	}


	/**
	 * @param object $identifier
	 */
	public function setIdentifier($identifier) {
		$this->identifier = $identifier;
	}


	/**
	 * @return object
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

}
