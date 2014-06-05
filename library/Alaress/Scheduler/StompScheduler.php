<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler;


use Alaress\Cli\Debug;
use Alaress\Scheduler\Interactive\Interactor;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Protocol\Frame;

abstract class StompScheduler extends AbstractScheduler {

	/** @var string */
    protected $scheduleQueue;

	/** @var string */
	protected $commandQueue;

	/** @var array */
	protected $subscriptions;

	/** @var Client */
	protected $stomp;


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param string $scheduleQueue
     * @param string $commandQueue
     * @param Debug $debug
     */
    public function __construct($loop, $dispatchStrategy, $scheduleQueue, $commandQueue, $debug) {
        parent::__construct($loop, $dispatchStrategy, $debug);
        $this->method = DefaultDispatchStrategy::TASK_PUBLISH;
        $this->scheduleQueue = $scheduleQueue;
		$this->commandQueue = $commandQueue;

        try {
            // defer to implementing classes
            $this->stomp = $this->getStompConnection();
	        $this->stomp->connect()->then(array($this, 'onConnect'));
        }
        catch(ConnectionException $e) {
            // if the connection bugged out...
            $this->debug->v('Rabbitmq is offline or STOMP misconfigured: '. $e->getMessage(), '!');
            $this->onException($e);
        }
    }


    /**
     * Subscribes, with ACKnowledgement, to the queue provided in construction
     *
     * @return void
     */
    public function onConnect() {
        $this->debug->v("Connected to STOMP", '+');
	    $this->subscribeAck($this->scheduleQueue, array($this, 'onMessage'));
	    $this->subscribe($this->commandQueue, array($this, 'onCommand'));
	    $this->stomp->on('error', array($this, 'onException'));
    }


	/**
	 * @param string $queue
	 * @param callable $callback
	 * @return bool
	 */
	protected function subscribe($queue, $callback) {
		if(isset($this->subscriptions[$queue])) {
			return false;
		}
		$this->subscriptions[$queue] = $this->stomp->subscribe($queue, $callback);
		return true;
	}


	/**
	 * @param string $queue
	 * @param callable $callback
	 * @param string $mode
	 * @return bool
	 */
	protected function subscribeAck($queue, $callback, $mode = 'client-individual') {
		if(isset($this->subscriptions[$queue])) {
			return false;
		}
		$this->subscriptions[$queue] = $this->stomp->subscribeWithAck($queue, $mode, $callback);
		return true;
	}


	/**
	 * @param string $queue
	 * @return bool
	 */
	protected function unsubscribe($queue) {
		if(isset($this->subscriptions[$queue])) {
			$this->stomp->unsubscribe($this->subscriptions[$queue]);
			unset($this->subscriptions[$queue]);
			return true;
		}
		return false;
	}


	/**
	 * Accepts a command (from STDIN) to log data about the scheduler's current state
	 *
	 * @param string $data
	 */
	public function _stdincmd($data) {
		$interactor = new Interactor($this);
		$interactor->handle($data);
	}


	/**
	 * Return the state-data requested
	 *
	 * @param array $fields
	 * @return array
	 */
	public function getStatus($fields = array()) {
		$status = array();
		if(in_array('pool', $fields)) {
			$status['pool'] = $this->getDispatcher()->getPoolStatus();
		}

		return $status;
	}


	/**
     * @return Client
     */
    protected abstract function getStompConnection();

    /**
     * @param Frame $frame
     * @param AckResolver $resolver
     * @return void
     */
    public abstract function onMessage($frame, $resolver);

	/**
	 * @param Frame $frame
	 * @return void
	 */
	public abstract function onCommand($frame);

    /**
     * @param Exception $e
     * @return void
     */
    public abstract function onException($e);

} 