<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Pooling;


use Alaress\Cli\Debug;
use Alaress\WorkerCrew\IPC\AlaressIPCProtocol;
use Alaress\WorkerCrew\IPC\ProtocolInterface;
use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerArray;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use Exception;
use InvalidArgumentException;
use OutOfBoundsException;
use SplQueue;

class DefaultDispatchStrategy implements DispatchStrategyInterface {

	const MIN_WORKERS  = 1;
	const MAX_WORKERS  = 6;
	const MAX_FLYWHEEL = 0;
	const QUEUE_DELAY  = 1;
	const IDLE_TIMEOUT = 2000;

	const TASK_FETCH     = 'fetch';
	const TASK_PUBLISH   = 'publish';
	const TASK_INTERCEPT = 'intercept';
	const TASK_DIGEST    = 'digest';
	const TASK_REROUTE   = 'intercept';

	protected $flywheelSpins;


	public function __construct() {
		$this->flywheelSpins = self::MAX_FLYWHEEL;
		$this->protocol = new AlaressIPCProtocol();
	}


	/**
	 * Get the name of the strategy currently being used
	 *
	 * @return string
	 */
	public function getName() {
		return 'Default';
	}


	/**
	 * Returns the IPC protocol that this strategy employs
	 *
	 * @return ProtocolInterface
	 */
	public function getProtocol() {
		return $this->protocol;
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
//		echo "LEMMY REPORTS THE TOTAL ATTACHED WORKER COUNT AS {$workers->count()}\n";

		// if we haven't got the min number of workers, favour starting a new one
		if($workers->countWhere(~(Worker::ENDED & Worker::ERROR)) < self::MIN_WORKERS) {
//			echo "WENDY REPORTS MIN WORKERS NOT YET REACHED\n";
			return 0;
		}

		// if a worker is free, yes we can accept
		try {
			$free = $workers->getPidWhere(Worker::READY);
//			echo "JUNIOR REPORTS $free AS A FREE WORKER\n";
			return $free;
		}
		catch(OutOfBoundsException $e) {
			// ok, so there's none of those
//			echo "LUDWIG REPORTS ALL EXISTING WORKERS ARE TIED UP\n";
		}

		// if number of workers THAT ARE NOT ENDED OR ERRORED is below the cap, spawn a new one
		if($workers->countWhere(~(Worker::ENDED & Worker::ERROR)) < self::MAX_WORKERS) {
//			echo "ROY REPORTS STILL BELOW MAX WORKER CAP\n";
			return 0;
		}

//		echo "IGGY REPORTS NO MORE WORKERS ARE ALLOWED\n";
		return -1;
	}


	/**
	 * Return the list of commands that can be delegated to workers
	 *
	 * @return array
	 */
	public function getSupportedActions() {
		return array(self::TASK_FETCH, self::TASK_PUBLISH, self::TASK_INTERCEPT, self::TASK_DIGEST);
	}


	/**
	 * Evaluate the segment of data given and return a Worker STATE based on it
	 *
	 * @param string $data
	 * @return int
	 */
	public function checkState($data) {

		// no data: finished
		if(strlen($data) == 0) {
			return Worker::DONE;
		}

		// rejected: ready for more
		if($this->protocol->isRejected($data)) {
			return Worker::READY;
		}

		// cancelled: I am in error now
		if($this->protocol->isCancelled($data)) {
			return Worker::ERROR;
		}

		// ended: That's all your data
		if($this->protocol->isEnded($data)) {
			return Worker::DONE;
		}

		// default case: "here's a chunk of data, I'm not finished yet"
		return Worker::BUSY;
	}


	/**
	 * Combine the two given parameters into a string that can be piped as a command to the worker process
	 *
	 * @param string $command
	 * @param mixed $arguments
	 * @return string
	 */
	public function composeTaskString($command, $arguments) {
		return $this->protocol->prepareCommand($command, $arguments);
	}


	/**
	 * Separate the given parameter into an array of two elements: command and args.
	 *
	 * @param string $taskString
	 * @throws InvalidArgumentException
	 * @return array
	 */
	public function decomposeTaskString($taskString) {
		$xpl = $this->protocol->rebuild($taskString);
		if(count($xpl) != 2) {
			throw new InvalidArgumentException("Invalid task given: '$taskString'");
		}
		return $xpl;
	}


	/**
	 * Converts some data to be in a format safe for socket transmission from Worker to Server
	 *
	 * @param mixed $data
	 * @return string
	 */
	public function encodeRecord($data) {
		return $this->protocol->flatten($data);
	}


	/**
	 * Decode the given data as received from socket transmission
	 *
	 * @param string $data
	 * @return array
	 */
	public function decodeRecords($data) {
		return $this->protocol->rebuild($data);
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
		return new WorkerArray($this->protocol);
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


	/**
	 * @param Exception $exception
	 * @param Debug $debug
	 * @return void
	 */
	public function handleException($exception, $debug) {
		$debug->v(sprintf("Exception during dispatch - %s: %s", get_class($exception), $exception->getMessage()), '!');
	}


	/**
	 * Returns a Queue, populated from storage if persistence is part of the strategy.
	 *
	 * @return SplQueue
	 */
	public function buildQueue() {
		return new SplQueue();
	}


	/**
	 * Persistent enqueue.
	 *
	 * @param object $identifier
	 * @param string $command
	 * @param array $data
	 * @return void
	 */
	public function penqueue($identifier, $command, $data) {
		// noop: this strategy does not use persistent queues.
	}

	/**
	 * Persistent dequeue.
	 *
	 * @param object $identifier
	 * @return void
	 */
	public function pdequeue($identifier) {
		// noop: this strategy does not use persistent queues.
	}


}
