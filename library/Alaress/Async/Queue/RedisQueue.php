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
use Alaress\Async\Notification\NotificationBundle;
use InvalidArgumentException;
use Predis\ClientInterface;

abstract class RedisQueue implements QueueInterface
{

    const TTL = 1209600; // 2 weeks

    /** @var ClientInterface */
    protected $redis;


    /**
     * @param ClientInterface $redis
     */
    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
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

        $exists = (bool)$this->redis->exists($id);
        $listed = (bool)$this->redis->sismember($this->getRedisSetKey(), $id);

        // a little housekeeping: if the 'queue' contains the reference, but the msg has expired, drop the reference
        if ($listed && !$exists) {
            $this->redis->srem($this->getRedisSetKey(), $id);
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
        $this->redis->del([ $this->getRedisSetKey() ]);
    }


    /**
     * Push the given instance onto this queue
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
        $this->redis->expire($id, $this->getMessageExpiry());

        // insert the msg reference into this 'queue'
        $this->redis->sadd($this->getRedisSetKey(), [ $id ]);
    }


    /**
     * @param NotificationBundle $bundle
     * @param bool $dropCache
     * @throws InvalidArgumentException
     * @return void
     */
    public function fetchIntoBundle(NotificationBundle $bundle, $dropCache = false)
    {
        $bundle->appendInstances($this->fetch());
    }


    /**
     * @return Instance[]
     */
    private function fetch()
    {
        $instances = [];

        $idList = $this->fetchIdList();

        if (empty($idList)) {
            return $instances;
        }

        $result = $this->redis->mget($idList);

        $count = count($idList);

        for ($i = 0; $i < $count; ++$i) {
            $key  = $idList[$i];
            $data = $result[$i];

            try {
                $instances[] = $this->inflate($data);

            } catch (InvalidArgumentException $e) {
                // the thing we're pointing at doesn't exist or payload invalid? remove the reference
                $this->redis->srem($this->getRedisSetKey(), $key);
            }
        }

        return $instances;
    }


    /**
     * @return array
     */
    private function fetchIdList()
    {
        return $this->redis->smembers($this->getRedisSetKey()) ?: [];
    }


    /**
     * The key in Redis for the given Instance, in the form 'msg:INSTANCE:m:hash'
     *
     * @param Instance $instance
     * @return string
     */
    private function getRedisMsgKey(Instance $instance)
    {
        return sprintf(
            $this->getMessageKeyPattern(),
            $this->getNamespace(),
            $instance->getBaseHash(),
            $instance->getParamHash()
        );
    }


    /**
     * The key for the Redis SET that holds this queue's data, in the form 'msg:INSTANCE:q:USER:TYPE'
     *
     * @return string
     */
    private function getRedisSetKey()
    {
        return sprintf(
            $this->getSetKeyPattern(),
            $this->getNamespace(),
            $this->getName()
        );
    }

    /**
     * Prepare an instance for storage.
     * Inverse of inflate()
     *
     * @param Instance $instance
     * @return string
     */
    private function deflate(Instance $instance)
    {
        return gzcompress("$instance", 9);
    }


    /**
     * Revert an instance from its stored form.
     * Inverse of deflate()
     *
     * @param string $data
     * @throws InvalidArgumentException if the given payload is unable to be restored into an Instance
     * @return Instance
     */
    private function inflate($data)
    {
        return Instance::jsonUnserialize(gzuncompress($data));
    }


    /**
     * Returns the overall namespace for this queue's data
     *
     * @return string
     */
    protected abstract function getNamespace();


    /**
     * Return a pattern containing 2 string placeholders: namespace, and set identifier
     *
     * @return string
     */
    protected abstract function getSetKeyPattern();


    /**
     * Return a pattern containing 3 string placeholders: namespace, msg base-hash, and msg param-hash
     *
     * @return string
     */
    protected abstract function getMessageKeyPattern();


    /**
     * Return, in seconds, the TTL of any message held by this set
     *
     * @return int
     */
    protected abstract function getMessageExpiry();

}