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
use Redis;

class RedisQueue implements QueueInterface
{

    const TTL = 1209600; // 2 weeks

    /** @var Redis|ClientInterface */
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

        $result = $this->redis->mget($idList);

        $count = count($idList);

        for ($i = 0; $i < $count; ++$i) {
            $key  = $idList[$i];
            $data = $result[$i];

            // attempt to unserialize the message
            if (is_null($data)) {
                // the thing we're pointing at doesn't exist? remove the reference
                 $this->redis->sRem($this->getRedisSetKey(), $key);
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

        $exists = !!$this->redis->exists($id);
        $listed = !!$this->redis->sIsMember($this->getRedisSetKey(), $id);

        // a little housekeeping: if the 'queue' contains the reference, but the msg has expired, drop the reference
        if ($listed && !$exists) {
            $this->redis->sRem($this->getRedisSetKey(), $id);
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
        $this->redis->del($this->getRedisSetKey());
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

        // insert the msg payload ONLY if it doesn't already exist
        $this->redis->setnx($id, $data);

        // set the expiry; if the key already existed, this has the effect of resetting the TTL
        $this->redis->expire($id, $this->ttl);

        // insert the msg reference into this 'queue'
        $this->redis->sAdd($this->getRedisSetKey(), $id);
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
        return $this->redis->sMembers($this->getRedisSetKey()) ? : array();
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