<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew;


use Alaress\WorkerCrew\Pooling\PoolingInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Schoolbox\Realtime\Verboser;
use SplQueue;

class Dispatcher {

	/** @var SplQueue */
	protected $q;

	/** @var int */
	protected $qDelay;

	/** @var LoopInterface */
	protected $loop;

	/** @var Pool */
	protected $pool;

	/** @var TimerInterface */
	protected $timer;


	/**
	 * @param LoopInterface $loop
	 * @param TaskResponseHandlerInterface $msgHandler
	 * @param Verboser $verboser
	 * @param PoolingInterface $strat
	 */
	function __construct($loop, $msgHandler, $verboser, $strat = null) {

		// create our task queue
		$this->q = new SplQueue();

		// pool of workers
		$this->pool = new Pool($loop, $msgHandler, $strat);

		$this->qDelay = $this->pool->getQueueDelay();

		// dispatcher depends on a react loop
		$this->loop = $loop;

		// need a reference to this for callback
		$this->msgHandler = $msgHandler;

		$this->v = $verboser;
	}


	/**
	 * @param object $identifier
	 * @param string $command
	 * @param mixed $arguments
	 */
	public function enqueue($identifier, $command, $arguments) {
		$this->q->enqueue(array($identifier, $command, $arguments));

		$this->v->v("Enqueued '$command': queue length is ".$this->q->count());

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
			unset($this->timer);

			$this->v->v("Queue is empty: detaching timer", '-');

			$this->pool->dispatcherIdle();
			return;
		}

		$this->v->v("Dispatching...");

		// can this task be dispatched, or does it need to be handled internally?
		list($identifier, $cmd, $args) = $this->q->bottom();

		// decide whether to delegate this to a Worker, or run it straight into the handler
		if(!in_array($cmd, $this->pool->getSupportedActions())) {
			$this->q->dequeue();

			$this->v->v("Internally handling '$cmd'");
			$this->msgHandler->handleResponse($identifier, $cmd, $args);

			return;
		}

		// all workers are tied up and ceiling has been reached - please wait
		if(!$this->pool->canAccept()) {
			$this->v->v("Cannot accept right now, will try again", '*');
			return;
		}

		$this->v->v("Pushing command to worker", '>');

		// pop the task from the queue and run the next task
		$this->q->dequeue();
		$this->pool->accept($identifier, $cmd, $args, $this->q->count());

	}
} 