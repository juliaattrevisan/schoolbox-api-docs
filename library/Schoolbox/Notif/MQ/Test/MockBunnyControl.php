<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\MQ\Test;


use Alaress\Async\AMQP\Bunny\ControlInterface;
use RuntimeException;

class MockBunnyControl implements ControlInterface {

	/** @var array */
	protected $queues;
	/** @var array */
	protected $exchanges;
	/** @var array */
	protected $bindings;
	/** @var array */
	protected $connections;
	/** @var array */
	protected $channels;
	/** @var array */
	protected $consumers;
	/** @var int */
	protected $posixuid;


	/**
	 * @param array $settings
	 * @param int $posixuid
	 */
	public function __construct($settings = array(), $posixuid = 0) {
		$this->queues      = isset($settings['queues'])      ? $settings['queues']      : array();
		$this->exchanges   = isset($settings['exchanges'])   ? $settings['exchanges']   : array();
		$this->bindings    = isset($settings['bindings'])    ? $settings['bindings']    : array();
		$this->connections = isset($settings['connections']) ? $settings['connections'] : array();
		$this->channels    = isset($settings['channels'])    ? $settings['channels']    : array();
		$this->consumers   = isset($settings['consumers'])   ? $settings['consumers']   : array();
		$this->posixuid    = $posixuid;
	}


	/**
	 * @param string $param
	 * @return array
	 * @throws RuntimeException
	 */
	protected function retrieve($param) {
		if($this->posixuid !== 0) {
			throw new RuntimeException("POSIX user id must be 0");
		}

		return $this->$param;
	}

	/**
	 * @param array  $fields
	 * @return array
	 */
	public function listQueues($fields) {
		return $this->retrieve('queues');
	}


	/**
	 * @param array  $fields
	 * @return array
	 */
	public function listExchanges($fields) {
		return $this->retrieve('exchanges');
	}


	/**
	 * @param array  $fields
	 * @return array
	 */
	public function listBindings($fields) {
		return $this->retrieve('bindings');
	}


	/**
	 * @param array $fields
	 * @return array
	 */
	public function listConnections($fields) {
		return $this->retrieve('connections');
	}


	/**
	 * @param array $fields
	 * @return array
	 */
	public function listChannels($fields) {
		return $this->retrieve('channels');
	}


	/**
	 * @return array
	 */
	public function listConsumers() {
		return $this->retrieve('consumers');
	}

}