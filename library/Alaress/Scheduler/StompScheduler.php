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
    protected $queue;

	/** @var array */
	protected $subscriptions;

	/** @var Client */
	protected $stomp;


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param string $queue
     * @param Debug $debug
     */
    public function __construct($loop, $dispatchStrategy, $queue, $debug) {
        parent::__construct($loop, $dispatchStrategy, $debug);
        $this->method = DefaultDispatchStrategy::TASK_PUBLISH;
        $this->queue = $queue;

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
	    $this->subscribeAck($this->queue, array($this, 'onMessage'));
	    $this->stomp->on('error', array($this, 'onException'));
    }


	/**
	 * @param string $queue
	 * @param callable $callback
	 */
	protected function subscribe($queue, $callback) {
		$this->subscriptions[$queue] = $this->stomp->subscribe($queue, $callback);
	}


	/**
	 * @param string $queue
	 * @param callable $callback
	 * @param string $mode
	 */
	protected function subscribeAck($queue, $callback, $mode = 'client-individual') {
		$this->subscriptions[$queue] = $this->stomp->subscribeWithAck($queue, $mode, $callback);
	}


	/**
	 * @param string $queue
	 */
	protected function unsubscribe($queue) {
		$this->stomp->unsubscribe($this->subscriptions[$queue]);
		unset($this->subscriptions[$queue]);
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
     * @param Exception $e
     * @return void
     */
    public abstract function onException($e);

} 