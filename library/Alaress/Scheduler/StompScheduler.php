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
            $stompClient = $this->getStompConnection();
            $stompClient->connect()->then(array($this, 'onConnect'));
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
     * @param Client $client
     * @return void
     */
    public function onConnect($client) {
        $this->debug->v("Connected to STOMP", '+');
        $client->subscribeWithAck($this->queue, 'client', array($this, 'onMessage'));
	    $client->on('error', array($this, 'onException'));
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