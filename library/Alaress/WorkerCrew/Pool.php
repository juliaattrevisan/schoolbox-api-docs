<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew;


use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use Evenement\EventEmitter;
use Exception;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use RuntimeException;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use SplObjectStorage;
use UnexpectedValueException;

/**
 * Class Pool
 * @package AlaressWorkerCrew
 */
class Pool extends EventEmitter {

	/** @var LoopInterface */
	protected $loop;

	/** @var DispatchStrategyInterface */
	protected $poolingStrat;

	/** @var WorkerBundleInterface */
	protected $workers;

	/** @var SplObjectStorage */
	protected $datastreams;

	/** @var SplObjectStorage */
	protected $logstreams;

	/** @var Debug */
	protected $debug;


	/**
	 * @param LoopInterface $loop
	 * @param TaskResponseHandlerInterface $msgHandler
	 * @param DispatchStrategyInterface $strat
	 * @param Debug $debug
	 */
	public function __construct($loop, $msgHandler, $strat, $debug) {
		$this->loop = $loop;
		$this->poolingStrat = $strat;
		$this->workers = $this->poolingStrat->newWorkerBundle();
		$this->datastreams = new SplObjectStorage();
		$this->logstreams = new SplObjectStorage();
		$this->msgHandler = $msgHandler;
		$this->debug = $debug;
	}


	/**
	 * Kill workers gracefully
	 */
	public function __destruct() {
		unset($this->workers);
	}


	/**
	 * Request to the pool to accept the given command. Returns the PID of the process to which the task was assigned.
	 *
	 * @param object $identifier
	 * @param string $cmd
	 * @param string $args
	 * @param int $remainingTaskCount
	 * @throws RuntimeException
	 * @return int
	 */
	public function accept($identifier, $cmd, $args, $remainingTaskCount) {
		$pid = $this->poolingStrat->getAvailableWorker($this->workers, $remainingTaskCount);

		switch($pid) {
			case -1: // there is nowhere you can put this, you should not have requested
				throw new RuntimeException("No process is available to handle this request, did you check first?");
			case 0:  // no current processes available, but you can create one
				$pid = $this->attachWorker();
				break;
			default: // any positive integer is the PID of the process we will be sending this to
				break;
		}

		$this->assignTask($pid, $cmd, $args, $identifier);
		return $pid;
	}


	/**
	 * Spawns a new worker
	 *
	 * @return int
	 * @throws RuntimeException
	 */
	protected function attachWorker() {
		$this->debug->v("Attaching worker to {$this->poolingStrat->getName()} pool", '+');
		$cmd = $this->poolingStrat->getCommand();

		$descriptorSpec = array(
			0 => array("pipe", "r"), // stdin is a pipe that the child will read from
			1 => array("pipe", "w"), // stdout is a pipe that the child will write to
			2 => array("pipe", "w")  // stderr is a pipe that the child will write to
		);

		if(!($pp = proc_open($cmd, $descriptorSpec, $pipes))) {
			throw new RuntimeException("Cannot launch worker process");
		}

		// disable stream blocking for the proc IO
		foreach(array_keys($pipes) as $i) {
			stream_set_blocking($pipes[$i], 0);
		}

		$status = proc_get_status($pp);
		$pid = $status['pid'];

		if(!$status['running']) {
			throw new RuntimeException("Worker process terminated unexpectedly");
		}

		$this->workers->attach($pid, new Worker($status['command'], $pp, $pipes, $this->poolingStrat));

		$this->workers->get($pid)->attachDebug($this->debug);

		// listen to worker's STDOUT for data
		$datastream = new Stream($pipes[1], $this->loop);
		$datastream->on('data',  array($this, 'onData'));
		$datastream->on('close', array($this, 'onClose'));

		$this->datastreams->attach($datastream, $pid);

		// listen to worker's STDERR for logging
		$logstream = new Stream($pipes[2], $this->loop);
		$logstream->on('data', array($this, 'onLog'));

		$this->logstreams->attach($logstream, $pid);

		return $pid;

	}


	/**
	 * @param int $pid
	 * @param string $cmd
	 * @param mixed $args
	 * @param object $identifier
	 * @throws RuntimeException
	 */
	protected function assignTask($pid, $cmd, $args, $identifier) {
		if(!$this->workers->contains($pid)) {
			throw new RuntimeException("Cannot assign task to nonexistent worker: $pid");
		}
		$this->workers->get($pid)->setTask($identifier, $cmd, $args);
	}


	/**
	 * @param string $data
	 * @param Stream $stream
	 */
	public function onData($data, $stream) {
		try {
			$pid = $this->datastreams->offsetGet($stream);
		}
		catch(UnexpectedValueException $e) {
			$this->debug->v("Unexpected stream supplied, cannot locate PID", '!');
			return;
		}

		$worker = $this->workers->get($pid);

		// interpret the first character sent and determine the worker's state based on it
		switch($this->poolingStrat->checkState($data)) {
			case Worker::READY: // ready for another task
				$this->debug->v("Worker $pid ready for new task");
				$worker->setStatus(Worker::READY);
				break;

			case Worker::BUSY:  // data sent, but not finished yet
				$this->debug->v("Worker $pid still working");
				$worker->appendData($data);
				break;

			case Worker::ERROR: // still alive, but cannot accept more tasks
				$this->debug->v("Worker $pid in error");
				$worker->setStatus(Worker::BUSY);
				break;

			case Worker::DONE:  // job done, ready for another task
				$this->debug->v("Worker $pid complete, ready for new task");
				$worker
					->appendData($data)
					->markTaskComplete();

				// worker reports being finished, so handle the response it came back with
				try {
					// pass to the registered response handler
					$this->msgHandler->handleResponse(
						$worker->getTask()->getIdentifier(),
						$worker->getTask()->getCommand(),
						$this->poolingStrat->decodeRecords($worker->getTask()->getData(false))
					);
				}
				catch(Exception $e) {
					// your reactor will explode if you don't catch exceptions coming from your response handler
					$this->poolingStrat->handleException($e, $this->debug);
				}

				// regardless of whether or not the handling of the response succeeded, we need to ditch this message
				$this->poolingStrat->pdequeue($worker->getTask()->getIdentifier());
				break;

			case Worker::ENDED: // process has closed. Primarily this will happen if fatal errors are hit
				$this->debug->v("Worker $pid killed");
				$worker->setStatus(Worker::ENDED);
				break;
		}

		// if we can accept, emit READY (to be caught by dispatcher)
		if($this->canAccept()) {
			$this->emit('ready');
		}
	}


	/**
	 * @param Stream $stream
	 */
	public function onClose($stream) {
		try {
			$pid = $this->datastreams->offsetGet($stream);
		}
		catch(UnexpectedValueException $e) {
			return;
		}

		$this->debug->v("Worker $pid killed");
		$this->workers->get($pid)->setStatus(Worker::ENDED);
	}


	/**
	 * Push all worker logging to the daemon's log
	 *
	 * @param string $data
	 * @param Stream $stream
	 */
	public function onLog($data, $stream) {
		try {
			$pid = $this->datastreams->offsetGet($stream);
		}
		catch(UnexpectedValueException $e) {
			$this->debug->v("$data");
			return;
		}
		$this->debug->v("{$pid} $data");
	}


	/**
	 * Returns the current status of the pool
	 *
	 * @return array
	 */
	public function getStatus() {
		return array(
			'workers' => $this->workers->getStatus()
		);
	}


	/**
	 * Removes internal reference to any workers that are dead and gone.
	 */
	protected function cleanup() {
		$this->poolingStrat->cleanup($this->workers);
	}


	/**
	 * Returns whether or not a new command can be accepted.
	 *
	 * @return bool
	 */
	public function canAccept() {
		return $this->poolingStrat->canAccept($this->workers);
	}


	/**
	 * Returns the command that will be executed each time a worker is spawned by this strategy.
	 *
	 * @return string
	 */
	public function getCommand() {
		return $this->poolingStrat->getCommand();
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
		return $this->poolingStrat->getAvailableWorker($workers, $remainingTaskCount);
	}


	/**
	 * Return the list of commands that can be delegated to workers
	 *
	 * @return array
	 */
	public function getSupportedActions() {
		return $this->poolingStrat->getSupportedActions();
	}


	/**
	 * Evaluate the segment of data given and return a Worker STATE based on it
	 *
	 * @param string $data
	 * @return int
	 */
	public function checkState($data) {
		return $this->poolingStrat->checkState($data);
	}


	/**
	 * Combine the two given parameters into a string that can be piped as a command to the worker process
	 *
	 * @param string $command
	 * @param mixed $arguments
	 * @return string
	 */
	public function composeTaskString($command, $arguments) {
		return $this->poolingStrat->composeTaskString($command, $arguments);
	}


	/**
	 * Separate the given parameter into an array of two elements: command and args.
	 *
	 * @param string $taskString
	 * @throws InvalidArgumentException
	 * @return array
	 */
	public function decomposeTaskString($taskString) {
		return $this->poolingStrat->decomposeTaskString($taskString);
	}


	/**
	 * Converts some data to be in a format safe for socket transmission from Worker to Server
	 *
	 * @param mixed $data
	 * @return string
	 */
	public function encodeRecord($data) {
		return $this->poolingStrat->encodeRecord($data);
	}


	/**
	 * Returns, in millis, how long the react loop should wait between dispatches from the queue
	 * @return int
	 */
	public function getQueueDelay() {
		return $this->poolingStrat->getQueueDelay();
	}


	/**
	 * Returns whether the Dispatcher should keep spinning, or spin down.
	 * If false is returned, this method should reset any internal counter.
	 *
	 * @return bool
	 */
	public function canDispatcherFlywheel() {
		return $this->poolingStrat->canDispatcherFlywheel();
	}


	/**
	 * Method to advise the pool that the Dispatcher is idle.
	 *
	 * @return void
	 */
	public function dispatcherIdle() {
		$this->poolingStrat->dispatcherIdle();
	}


	/**
	 * Method to advise the pool that the Dispatcher is engaged.
	 *
	 * @return void
	 */
	public function dispatcherEngage() {
		$this->poolingStrat->dispatcherEngage();
	}


	/**
	 * Return the number of seconds a Worker should be idle before self-destructing
	 *
	 * @return int
	 */
	public function getWorkerIdleTimeout() {
		return $this->poolingStrat->getWorkerIdleTimeout();
	}

}
