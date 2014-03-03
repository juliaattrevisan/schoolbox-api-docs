<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew;


use Alaress\WorkerCrew\Pooling\DefaultStrategy;
use Alaress\WorkerCrew\Pooling\PoolingInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Schoolbox\Realtime\Verboser;
use SplQueue;

class Dispatcher implements DispatcherInterface {

	/** @var SplQueue */
	protected $q;

	/** @var int */
	protected $qDelay;

	/** @var LoopInterface */
	protected $loop;

	/** @var Pool */
	protected $pool;

	/** @var TimerInterface */
	protected $timer = null;

	/** @var PoolingInterface */
	protected $strat;

	/**
	 * @param LoopInterface $loop
	 * @param TaskResponseHandlerInterface $msgHandler
	 * @param Verboser $verboser
	 * @param PoolingInterface $strat
	 */
	function __construct($loop, $msgHandler, $verboser, $strat = null) {

		$this->strat = $strat ?: new DefaultStrategy();

		// pool of workers
		$this->pool = new Pool($loop, $msgHandler, $this->strat);

		// dispatcher depends on a react loop
		$this->loop = $loop;

		// need a reference to this for callback
		$this->msgHandler = $msgHandler;

		// for debugging output
		$this->v = $verboser;

		// build the queue, retrieving from storage if that's part of the strat, and execute if it contains stuff
		$this->q = $this->strat->buildQueue();
		$this->qDelay = $this->strat->getQueueDelay();

		if($this->q->count()) {
			$this->v->v("Queue is preloaded, draining: queue length is ".$this->q->count());
			$this->drainQueue();
		}
	}


	/**
	 * @param object $identifier
	 * @param string $command
	 * @param string $data
	 */
	public function enqueue($identifier, $command, $data) {

		// call for persistence
		$this->strat->penqueue($identifier, $command, $data);

		// store the element locally
		$this->q->enqueue(array($identifier, $command, $data));
		$this->v->v("Enqueued '$command': queue length is ".$this->q->count());

		$this->drainQueue();
	}


	/**
	 * Attempts to dequeue and dispatch all elements in the queue.
	 * Nothing will happen if the queue is empty.
	 */
	protected function drainQueue() {
		// notify the pool that we're engaged
		$this->pool->dispatcherEngage();

		// if there's a timer, use it, otherwise create it
		if(!($this->timer instanceof TimerInterface)) {
			$this->v->v("Creating timer to fire at {$this->qDelay}ms intervals", '+');
			$this->timer = $this->loop->addPeriodicTimer($this->qDelay / 1000, array($this, 'dispatch'));
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

		$this->v->v("Dispatching...");

		/**
		 * @var object $identifier
		 * @var string $cmd
		 * @var array $data
		 */
		list($identifier, $cmd, $data) = $this->q->bottom();

		// decide whether to delegate this to a Worker, or return it straight into the handler
		if(!in_array($cmd, $this->pool->getSupportedActions())) {
			try {
				$this->q->dequeue();
				$this->v->v("Internally handling '$cmd'");
				$this->msgHandler->handleResponse($identifier, $cmd, (array)$data);
			}
			catch(Exception $e) {
				// we need to catch any and all exception here, lest we explode our reactor
				$this->v->v("Exception suppressed: ".get_class($e) . " ".$e->getMessage(),'*');
			}
			return;
		}

		// all workers are tied up and ceiling has been reached - please wait
		if(!$this->pool->canAccept()) {
			$this->v->v("Cannot accept right now, will try again", '*');
			return;
		}

		$this->v->v("Pushing command to worker", '>');

		// pop the task from the queue and run the next task
		try {
			$this->q->dequeue();
			$this->pool->accept($identifier, $cmd, $data, $this->q->count());
		}
		catch(Exception $e) {
			// we need to catch any and all exception here, lest we explode our reactor
			$this->v->v("Exception suppressed: ".get_class($e) . " ".$e->getMessage(),'*');
		}

	}
} 