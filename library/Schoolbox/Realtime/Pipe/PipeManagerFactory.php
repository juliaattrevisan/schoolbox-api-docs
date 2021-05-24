<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Alaress\Database\Connector\IConnector;
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
     * @var IConnector
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
     * @var int
     */
    private $fdLimit;


    /**
     * Take all the root dependencies, ready for composing into more complex object
     *
     * @param LoopInterface             $loop
     * @param HandlerInterface          $handler
     * @param LoggerInterface           $log
     * @param ClientInterface           $redis
     * @param Async\Client              $asyncRedis
     * @param int                       $sessionLength
     * @param IConnector                $db
     * @param string                    $instanceId
     * @param DispatchStrategyInterface $strat
     * @param int                       $fdLimit
     */
    public function __construct($loop, $handler, $log, $redis, $asyncRedis, $sessionLength, $db, $instanceId, $strat, $fdLimit)
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
        $this->fdLimit       = $fdLimit;
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
        $pipeManager = new PipeManager($this->loop, $pipes, $subManager, $this->log, $this->strat, $this->fdLimit);

        // setup the listener
        $subFactory->setListener($pipeManager);

        return $pipeManager;
    }
}
