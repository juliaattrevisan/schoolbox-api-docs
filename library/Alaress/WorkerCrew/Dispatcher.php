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
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use SplQueue;

class Dispatcher implements DispatcherInterface {

	/** @var SplQueue */
	protected $q;

	/** @var LoopInterface */
	protected $loop;

	/** @var Pool */
	protected $pool;

	/** @var TimerInterface */
	protected $timer = null;

	/** @var DispatchStrategyInterface */
	protected $strat;


	/**
	 * @param LoopInterface $loop
	 * @param TaskResponseHandlerInterface $msgHandler
	 * @param Debug $debug
	 * @param DispatchStrategyInterface $strat
	 */
	public function __construct($loop, $msgHandler, $debug, $strat = null) {

		$this->strat = $strat ?: new DefaultDispatchStrategy();

		// pool of workers
		$this->pool = new Pool($loop, $msgHandler, $this->strat, $debug);

		// dispatcher depends on a react loop
		$this->loop = $loop;

		// need a reference to this for callback
		$this->msgHandler = $msgHandler;

		// for debugging output
		$this->v = $debug;

		// build the queue, retrieving from storage if that's part of the strat, and execute if it contains stuff
		$this->q = $this->strat->buildQueue();

		if($this->q->count()) {
			$this->v->v("Queue is preloaded, draining: queue length is ".$this->q->count());
			$this->attach($this->strat->getQueueDelay());
		}
	}


	/**
	 * Daemon is going down, try to make sure the pool dries up gracefully
	 */
	public function __destruct() {
		unset($this->pool);
	}


	/**
	 * @param object $identifier
	 * @param string $command
	 * @param string $data
	 * @throws QueueingException
	 */
	public function enqueue($identifier, $command, $data) {
        $qLength = $this->q->count();
        $mLength = $this->strat->getMaxQueueLength();

        if($qLength >= $mLength) {
            throw new QueueingException("Cannot enqueue, max queue length of $mLength reached");
        }

		// call for persistence
		$this->strat->penqueue($identifier, $command, $data);

		// store the element locally
		$this->q->enqueue(array($identifier, $command, $data));
		$this->v->v("Enqueued '$command': queue length is ".$this->q->count());

        if($qLength >= $this->strat->getWarningQueueLength()) {
            $this->v->v("Queue is getting large", '?');
        }

		$this->attach($this->strat->getQueueDelay());
	}


	/**
	 * Attempts to dequeue and dispatch all elements in the queue.
	 * Nothing will happen if the queue is empty.
	 */
	public function attach($rate = null) {
		// notify the pool that we're engaged
		$this->pool->dispatcherEngage();

		// this came from the pool emitting ready
		if(is_null($rate)) {
			$rate = $this->strat->getQueueDelay();
			$this->detach();
		}

		// if there's a timer, use it, otherwise create it
		if(!($this->timer instanceof TimerInterface)) {
			$this->v->v("Creating timer to fire at {$rate}ms intervals", '+');
			$this->timer = $this->loop->addPeriodicTimer($rate / 1000, array($this, 'dispatch'));
		}
	}


	/**
	 * Detaches the Dispatcher from the loop
	 */
	protected function detach() {
		// don't try to remove a timer that isn't there
		if($this->timer instanceof TimerInterface) {
			$this->v->v("Detaching dispatcher (queue length is {$this->q->count()})",'-');
			$this->pool->dispatcherIdle();
			$this->loop->cancelTimer($this->timer);
			$this->timer = null;
		}
	}


	/**
	 * This method will be fired every self::LOOP_DELAY millis
	 *
	 * @param TimerInterface $timer
	 */
	public function dispatch($timer) {

		// there's nothing in the queue
		if(!$this->q->count()) {
			if($this->pool->canDispatcherFlywheel()) {
				$this->v->v("Queue is empty: flywheel spinning");
				return;
			}

			$this->loop->cancelTimer($timer);
			$this->timer = null;

			$this->v->v("Queue is empty: detaching timer", '-');

			$this->pool->dispatcherIdle();
			return;
		}

		/**
		 * @var object $identifier
		 * @var string $cmd
		 * @var array $data
		 */
		list($identifier, $cmd, $data) = $this->q->bottom();

		// decide whether to delegate this to a Worker, or return it straight into the handler
		if(!in_array($cmd, $this->pool->getSupportedActions())) {
			try {
				// we obtained the data from q::bottom() so just drop the reference
				$this->q->dequeue();
				$this->v->v("Internally handling '$cmd'");
				$this->msgHandler->handleResponse($identifier, $cmd, (array)$data);
			}
			catch(Exception $e) {
				// we need to catch any and all exception here, lest we explode our reactor
				$this->v->v("Exception suppressed: ".get_class($e) . " ".$e->getMessage(),'*');
			}
			// regardless of whether or not we succeeded, we've finished handling this message, so dequeue it
			$this->strat->pdequeue($identifier);
			return;
		}

		// all workers are tied up and ceiling has been reached - please wait
		if(!$this->pool->canAccept()) {
			// detach and wait for pool to notify us of a free worker
			$this->detach();
			$this->pool->once('ready', array($this, 'attach'));
			$this->attach($this->strat->getWorkerIdleTimeout());
			return;
		}

		// pop the task from the queue and run the next task
		try {
			// we obtained the data from q::bottom() so just drop the reference
			$this->q->dequeue();
			$pid = $this->pool->accept($identifier, $cmd, $data, $this->q->count());
			$this->v->v("Command '$cmd' pushed to worker {{$pid}}", '>');
		}
		catch(Exception $e) {
			// we need to catch any and all exception here, lest we explode our reactor
			$this->v->v("Exception suppressed: ".get_class($e) . " ".$e->getMessage(),'*');
		}

	}


	/**
	 * @return array
	 */
	public function getPoolStatus() {
		return $this->pool->getStatus();
	}


	/**
	 * Signals that all workers should be terminated
	 *
	 * @param bool $hard
	 * @return void
	 */
	public function releaseWorkerPool($hard = true) {
		$this->pool->releaseWorkers($hard);
	}

}
