<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Database;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Session\Serialize\HandlerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Subscription;

/**
 * Convenience class for instantiation of the Pipe Manager
 *
 * @package Schoolbox\Realtime\Pipe
 */
class PipeManagerFactory
{
    /**
     * @var LoopInterface
     */
    private $loop;
    /**
     * @var LoggerInterface
     */
    private $log;
    /**
     * @var ClientInterface
     */
    private $redis;
    /**
     * @var Async\Client
     */
    private $asyncRedis;
    /**
     * @var int
     */
    private $sessionLength;
    /**
     * @var Database
     */
    private $db;
    /**
     * @var string
     */
    private $instanceId;
    /**
     * @var DispatchStrategyInterface
     */
    private $strat;
    /**
     * @var HandlerInterface
     */
    private $handler;


    /**
     * Take all the root dependencies, ready for composing into more complex object
     *
     * @param LoopInterface $loop
     * @param HandlerInterface $handler
     * @param LoggerInterface $log
     * @param ClientInterface $redis
     * @param Async\Client $asyncRedis
     * @param int $sessionLength
     * @param Database $db
     * @param string $instanceId
     * @param DispatchStrategyInterface $strat
     */
    public function __construct($loop, $handler, $log, $redis, $asyncRedis, $sessionLength, $db, $instanceId, $strat)
    {
        $this->loop          = $loop;
        $this->handler       = $handler;
        $this->log           = $log;
        $this->redis         = $redis;
        $this->asyncRedis    = $asyncRedis;
        $this->sessionLength = $sessionLength;
        $this->db            = $db;
        $this->instanceId    = $instanceId;
        $this->strat         = $strat;
    }


    /**
     * @return PipeManager
     */
    public function build()
    {
        $validator   = new SessionValidator($this->redis, $this->handler, $this->sessionLength, $this->db);
        $pipes       = new PipeCollection($validator, $this->log);
        $subFactory  = new Subscription\Redis\SubscriptionFactory($this->redis, $this->asyncRedis);
        $subManager  = new Subscription\SubscriptionManager($this->instanceId, $subFactory, $this->log);
        $pipeManager = new PipeManager($this->loop, $pipes, $subManager, $this->log, $this->strat);

        // setup the listener
        $subFactory->setListener($pipeManager);

        return $pipeManager;
    }

}