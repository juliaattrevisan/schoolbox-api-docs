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

    /** @var string */
    private $instanceId;

    /** @var int */
    private $userId;


    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     * @param int             $userId
     */
    public function __construct(ClientInterface $redis, $instanceId, $userId)
    {
        parent::__construct($redis);

        $this->instanceId = $instanceId;
        $this->userId     = $userId;
    }


    /**
     * Returns the overall namespace for this queue's data
     *
     * @return string
     */
    protected function getNamespace()
    {
        return "msg:{$this->instanceId}";
    }


    /**
     * @return string
     */
    protected function getMessageKeyPattern()
    {
        return '%s:m:%s:%s';
    }


    /**
     * @return string
     */
    protected function getSetKeyPattern()
    {
        return '%s:q:%s';
    }


    /**
     * @return int
     */
    protected function getUserId()
    {
        return $this->userId;
    }

    /**
     * Get the suffix for this set
     *
     * @return string
     */
    public function getName()
    {
        return "{$this->userId}:{$this->getSuffix()}";
    }


    /**
     * @return string
     */
    abstract protected function getSuffix();
}
