<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;
use Predis\ClientInterface;


class Factory implements IFactory
{

    /** @var string */
    private $instanceId;

    /** @var ClientInterface */
    private $redis;


    /**
     * @param string          $instanceId
     * @param ClientInterface $redis
     */
    public function __construct($instanceId, ClientInterface $redis)
    {
        $this->instanceId = $instanceId;
        $this->redis      = $redis;
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function listed($userId)
    {
        return new Listed($this->redis, $this->instanceId, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function unread($userId)
    {
        return new Unread($this->redis, $this->instanceId, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function digest($userId)
    {
        return new Digest($this->redis, $this->instanceId, $userId);
    }

}