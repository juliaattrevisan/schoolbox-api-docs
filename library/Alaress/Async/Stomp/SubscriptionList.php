<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Stomp;


use OutOfBoundsException;

abstract class SubscriptionList {

	/**
	 * In the form { queueName => data }
	 *
	 * @var array
	 */
	protected $queues = array();


	/**
	 * @return array
	 */
	public function getAllQueueNames() {
		return array_keys($this->queues);
	}


	/**
	 * @param string $regex
	 * @return array
	 */
	public function getMatchingQueueNames($regex) {
		return preg_grep($regex, array_keys($this->queues));
	}


	/**
	 * @param string $queue
	 * @return mixed
	 * @throws OutOfBoundsException
	 */
	public function getQueueData($queue) {
		if(!array_key_exists($queue, $this->queues)) {
			throw new OutOfBoundsException("Queue '$queue' not registered");
		}

		return $this->queues[$queue];
	}


	/**
	 * @param mixed $source
	 * @param bool  $clear
	 * @return void
	 */
	public abstract function importFromSource($source, $clear = true);


    /**
     * @param int $userId
     * @return string
     */
    public abstract function createQueueName($userId);

} 