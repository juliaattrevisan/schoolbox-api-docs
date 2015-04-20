<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\UserQueue\UserQueue;
use InvalidArgumentException;
use Predis\ClientInterface;
use UnexpectedValueException;

abstract class AbstractQueueFactory implements IQueueFactory {

    /** @var AMQPInterface */
    private $amqp  = null;

    /** @var ClientInterface */
    private $redis = null;

    /** @var string */
    protected $kind;


    /**
     * @param AMQPInterface $amqp
     * @return static
     */
    public function setAMQP(AMQPInterface $amqp)
    {
        $this->amqp = $amqp;
        return $this;
    }


    /**
     * @param ClientInterface $redis
     * @return static
     */
    public function setRedis(ClientInterface $redis)
    {
        $this->redis = $redis;
        return $this;
    }


    /**
     * @param string $kind
     * @return static
     */
    public function setKind($kind)
    {
        $this->kind = $kind;
        return $this;
    }


    /**
     * @return AMQPInterface
     * @throws InvalidArgumentException
     */
    protected function getAMQP()
    {
        if ($this->amqp instanceof AMQPInterface) {
            return $this->amqp;
        }
        throw new InvalidArgumentException("AMQP client not defined");
    }


    /**
     * @return ClientInterface
     * @throws InvalidArgumentException
     */
    protected function getRedis()
    {
        if ($this->redis instanceof ClientInterface) {
            return $this->redis;
        }
        throw new InvalidArgumentException("Redis client not defined");
    }

    /**
     * @param int $userId
     * @throws UnexpectedValueException
     * @return UserQueue
     */
    public abstract function buildForUser($userId);

} 