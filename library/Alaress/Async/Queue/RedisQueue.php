<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Queue;


use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;

class RedisQueue implements QueueInterface
{

    const TTL = 1209600; // 2 weeks

    /** @var ClientInterface */
    protected $redis;

    /** @var string */
    protected $name;

    /** @var int */
    protected $ttl;


    /**
     * @param ClientInterface $redis
     * @param string          $name
     * @param int             $ttl
     */
    public function __construct(ClientInterface $redis, $name, $ttl = self::TTL)
    {
        $this->redis = $redis;
        $this->name  = $name;
        $this->ttl   = $ttl;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     *
     * @return Instance[]
     */
    public function fetch()
    {
        $instances = array();

        $idList = $this->fetchIdList();

        if (empty($idList)) {
            return $instances;
        }

        $result = $this->exec("MGET", $idList);

        $count = count($idList);

        for ($i = 0; $i < $count; ++$i) {
            // $key  = $idList[$i];
            $data = $result[$i];

            // attempt to unserialize the message
            if (is_null($data)) {
                // the thing we're pointing at doesn't exist? remove the reference
                 $this->exec('SREM', $this->getRedisSetKey(), $key);
                continue;
            }

            $instances[] = $this->inflate($data);
        }

        return $instances;
    }


    /**
     * Returns whether or not this queue contains the given instance
     *
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance)
    {
        $id = $this->getRedisMsgKey($instance);

        $exists = !!$this->exec('EXISTS', $id);
        $listed = !!$this->exec('SISMEMBER', $this->getRedisSetKey(), $id);

        // a little housekeeping: if the 'queue' contains the reference, but the msg has expired, drop the reference
        if ($listed && !$exists) {
            $this->exec('SREM', $this->getRedisSetKey(), $id);
        }

        return $exists && $listed;
    }


    /**
     * Request to drain the queue of its contents.
     *
     * @return void
     */
    public function drain()
    {
        // delete the list, which is a user's reference to the messages. they will be expired automatically (and other users may still be attached)
        $this->exec("DEL", $this->getRedisSetKey());
    }


    /**
     * Push a message instance into this 'queue'
     * Note: this isn't an interfaced method; AMQP doesn't support this concept
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance)
    {
        $id    = $this->getRedisMsgKey($instance);
        $data  = $this->deflate($instance);

        // insert the msg body into storage, with expiry automatically set
        $this->exec("SET", $id, $data);
        $this->exec("EXPIRE", $id, $this->ttl);

        // insert the msg reference into this 'queue'
        $this->exec("SADD", $this->getRedisSetKey(), $id);
    }


    /**
     * Simple wrapper to encapsulate Predis interfaces
     *
     * @param string $cmd
     * @param mixed $_args
     * @return mixed
     */
    protected function exec($cmd, $_args = null)
    {
        // if the args just got put in this var, use that; otherwise, read func_args
        if (!is_array($_args)) {
            $_args = func_get_args();
            array_shift($_args);
        }

        $cmd = $this->redis->createCommand($cmd, $_args);

        return $this->redis->executeCommand($cmd);
    }


    /**
     * Returns the index hash name
     *
     * @return string
     */
    protected function getInstanceHash()
    {
        return "msg:" . INSTANCE_ID;
    }


    /**
     * @return array
     */
    protected function fetchIdList()
    {
        return $this->exec("SMEMBERS", $this->getRedisSetKey()) ? : array();
    }


    /**
     * The key in Redis for the given Instance, in the form 'msg:vagrantx:m:hash'
     *
     * @param Instance $instance
     * @return string
     */
    protected function getRedisMsgKey(Instance $instance)
    {
        return $this->getInstanceHash() . ':m:' . $instance->getBaseHash() . ':' . $instance->getParamHash();
    }


    /**
     * The key for the Redis SET that holds this queue's data, in the form 'msg:vagrantx:q:name'
     *
     * @return string
     */
    protected function getRedisSetKey()
    {
        return $this->getInstanceHash() . ':q:' . $this->getName();
    }

    /**
     * Prepare an instance for storage.
     * Inverse of inflate()
     *
     * @param Instance $instance
     * @return string
     */
    protected function deflate(Instance $instance)
    {
        return gzcompress("$instance", 9);
    }


    /**
     * Revert an instance from its stored form.
     * Inverse of deflate()
     *
     * @param string $data
     * @return Instance
     */
    protected function inflate($data)
    {
        return Instance::jsonUnserialize(gzuncompress($data));
    }
}