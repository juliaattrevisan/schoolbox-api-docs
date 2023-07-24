<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\RedisQueue;
use Predis\ClientInterface;

abstract class AbstractList extends RedisQueue
{
    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     * @param int             $userId
     */
    public function __construct(ClientInterface $redis, private string $instanceId, private int $userId)
    {
        parent::__construct($redis);
    }


    /**
     * Returns the overall namespace for this queue's data
     *
     * @return string
     */
    protected function getNamespace(): string
    {
        return "msg:{$this->instanceId}";
    }


    /**
     * @return string
     */
    protected function getMessageKeyPattern(): string
    {
        return '%s:m:%s:%s';
    }


    /**
     * @return string
     */
    protected function getSetKeyPattern(): string
    {
        return '%s:q:%s';
    }


    /**
     * @return int
     */
    protected function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Get the suffix for this set
     *
     * @return string
     */
    public function getName(): string
    {
        return "{$this->userId}:{$this->getSuffix()}";
    }


    /**
     * @return string
     */
    abstract protected function getSuffix(): string;
}
