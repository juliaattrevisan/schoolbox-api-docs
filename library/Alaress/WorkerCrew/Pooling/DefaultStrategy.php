<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Pooling;


use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerArray;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use InvalidArgumentException;
use OutOfBoundsException;

class DefaultStrategy implements PoolingInterface {

	const MIN_WORKERS  = 1;
	const MAX_WORKERS  = 4;
	const MAX_FLYWHEEL = 250; // @1ms delay means a flywheel duration of 250ms
	const QUEUE_DELAY  = 1;
	const IDLE_TIMEOUT = 5;

	protected $flywheelSpins;


	public function __construct() {
		$this->flywheelSpins = self::MAX_FLYWHEEL;
	}


	/**
	 * Returns the command that will be executed each time a worker is spawned by this strategy.
	 *
	 * @return string
	 */
	public function getCommand() {
		return SITE_root . "/tools/socket/worker.php";
	}


	/**
	 * Returns whether or not a new command can be accepted.
	 *
	 * @param WorkerBundleInterface $workers
	 * @return bool
	 */
	public function canAccept($workers) {
		return $this->getAvailableWorker($workers, 0) != -1;
	}


	/**
	 * Return:
	 *   the PID of an available worker,
	 *   0 if none are available but it is permissible to create one, or
	 *   -1 if none are available, and no more may be spawned right now.
	 *
	 * @param WorkerBundleInterface $workers
	 * @param int $remainingTaskCount
	 * @return int
	 */
	public function getAvailableWorker($workers, $remainingTaskCount) {

		// if we haven't got the min number of workers, favour starting a new one
		if($workers->count() < self::MIN_WORKERS) {
			return 0;
		}

		// if a worker is free, yes we can accept
		try {
			return $workers->getPidWhere(Worker::READY);
		}
		catch(OutOfBoundsException $e) {
			// ok, so there's none of those
		}

		// if number of workers THAT ARE NOT ENDED OR ERRORED is below the cap, spawn a new one
		if($workers->countWhere(~(Worker::ENDED & Worker::ERROR)) < self::MAX_WORKERS) {
			return 0;
		}

		return -1;
	}


	/**
	 * Return the list of commands that can be delegated to workers
	 *
	 * @return array
	 */
	public function getSupportedActions() {
		return array('fetch');
	}


	/**
	 * Evaluate the segment of data given and return a Worker STATE based on it
	 *
	 * @param string $data
	 * @return int
	 */
	public function checkState($data) {

		if(strlen($data) == 0) {
			return Worker::DONE;
		}

		$char = $data[0];

		switch($char) {
			case self::STR_NACKNOWLEDGE: // task rejected, give me another
				return Worker::READY;
			case self::STR_CANCEL:       // I got an exception -_-
				return Worker::ERROR;
		}

		// if the record separator was included, we're at the end of a response
		if(strpos($data, self::STR_RECORD_SEPARATOR) !== false) {
			return Worker::DONE;
		}

		// default case is "here's a chunk of data, I'm not finished yet"
		return Worker::BUSY;
	}


	/**
	 * Combine the two given parameters into a string that can be piped as a command to the worker process
	 *
	 * @param string $command
	 * @param mixed $arguments
	 * @return string
	 */
	public function buildTaskString($command, $arguments) {
		return $command . self::STR_UNIT_SEPARATOR . json_encode($arguments);
	}


	/**
	 * Separate the given parameter into an array of two elements: command and args.
	 *
	 * @param string $taskString
	 * @throws InvalidArgumentException
	 * @return array
	 */
	public function decomposeTaskString($taskString) {
		$xpl = explode(self::STR_UNIT_SEPARATOR, $taskString, 2);
		if(count($xpl) != 2) {
			throw new \InvalidArgumentException("Invalid task given: '$taskString'");
		}
		return $xpl;
	}


	/**
	 * Converts some data to be in a format safe for socket transmission from Worker to Server
	 *
	 * @param mixed $data
	 * @return string
	 */
	public function formatTransmissionData($data) {
		return json_encode($data) . self::STR_RECORD_SEPARATOR;
	}


	/**
	 * Returns, in millis, how long the react loop should wait between dispatches from the queue
	 * @return int
	 */
	public function getQueueDelay() {
		return self::QUEUE_DELAY;
	}


	/**
	 * Returns whether the Dispatcher should keep spinning, or spin down.
	 * If false is returned, this method should reset any internal counter.
	 *
	 * @return bool
	 */
	public function canDispatcherFlywheel() {
		if($this->flywheelSpins-- > 0) {
			return true;
		}
		$this->flywheelSpins = self::MAX_FLYWHEEL;
		return false;
	}


	/**
	 * Return the number of seconds a Worker should be idle before self-destructing
	 *
	 * @return int
	 */
	public function getWorkerIdleTimeout() {
		return self::IDLE_TIMEOUT;
	}


	/**
	 * Method to advise the pool that the Dispatcher is idle.
	 *
	 * @return void
	 */
	public function dispatcherIdle() {
		// TODO: Implement dispatcherIdle() method.
	}


	/**
	 * Method to advise the pool that the Dispatcher is engaged.
	 *
	 * @return void
	 */
	public function dispatcherEngage() {
		// TODO: Implement dispatcherEngage() method.
	}


	/**
	 * Returns a NEW, empty WorkerBundle.
	 *
	 * @return WorkerBundleInterface
	 */
	public function newWorkerBundle() {
		return new WorkerArray();
	}


	/**
	 * Strategically remove the workers that you want to from the given collection
	 *
	 * @param WorkerBundleInterface $workers
	 * @return void
	 */
	public function cleanup($workers) {
		$workers->detachWhere(Worker::ENDED);
	}


} 