<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Queue;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Scheduler\Trigger\TriggerInterface;
use ErrorException;
use InvalidArgumentException;
use Predis\Client;
use Predis\ClientInterface;
use Predis\Transaction\MultiExec;

abstract class RedisQueue implements QueueInterface
{
    public const TTL_EXPIRED = -2;

    public const COMPRESSION_LEVEL = 9; // 9 is max, 6 is default, -1 is none

    /**
     * @param ClientInterface $redis
     */
    public function __construct(protected ClientInterface $redis)
    {
    }


    /**
     * Returns whether or not this queue contains the given instance
     *
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance): bool
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
    public function drain(): void
    {
        // delete the list, which is a user's reference to the messages. they will be expired automatically (and other users may still be attached)
        $this->redis->del([$this->getRedisSetKey()]);
    }


    /**
     * Push the given instance onto this queue
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance): void
    {
        $id   = $this->getRedisMsgKey($instance);
        $data = self::deflate($instance);

        if (!$this->redis instanceof Client) {
            throw new InvalidArgumentException('Unexpected redis client');
        }

        $this->redis->transaction(function (MultiExec $tx) use ($id, $data, $instance): void {
            $ttl = $instance->getTTL() > 0 ? $instance->getTTL() : Instance::DEFAULT_TTL;
            // insert the msg payload ONLY if it doesn't already exist
            $tx->setnx($id, $data)
                // insert the msg reference into this 'queue'
               ->sadd($this->getRedisSetKey(), [$id]);
            $tx->expire($id, $ttl);
        });
    }


    /**
     * @param NotificationBundle $bundle
     * @param bool               $dropCache
     * @return void
     * @throws InvalidArgumentException
     */
    public function fetchIntoBundle(NotificationBundle $bundle, bool $dropCache = false): void
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
            if (!$data) {
                // the thing we're pointing at doesn't exist, remove the reference
                $this->redis->srem($this->getRedisSetKey(), $key);
                continue;
            }
            try {
                $instances[] = self::inflate($data);
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
    private function fetchIdList(): array
    {
        return $this->redis->smembers($this->getRedisSetKey()) ?: [];
    }


    /**
     * The key in Redis for the given Instance, in the form 'msg:INSTANCE:m:hash'
     *
     * @param Instance $instance
     * @return string
     */
    public function getRedisMsgKey(Instance $instance): string
    {
        return sprintf(
            $this->getMessageKeyPattern(),
            $this->getNamespace(),
            $instance->getBaseHash(),
            $instance->getParamHash()
        );
    }


    /**
     * The base key in Redis for the given Instance, in the form 'msg:INSTANCE:m:baseHash:*'
     *
     * @param Instance $instance
     * @return string
     */
    public function getRedisMsgBaseKey(Instance $instance): string
    {
        return sprintf(
            $this->getMessageKeyPattern(),
            $this->getNamespace(),
            $instance->getBaseHash(),
            '*'
        );
    }


    /**
     * The key for the Redis SET that holds this queue's data, in the form 'msg:INSTANCE:q:USER:TYPE'
     *
     * @return string
     */
    protected function getRedisSetKey(): string
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
     * @param Instance|TriggerInterface $instance
     * @return string
     */
    private static function deflate(Instance|TriggerInterface $instance): string
    {
        return gzcompress(serialize($instance), self::COMPRESSION_LEVEL);
    }


    /**
     * Revert an instance from its stored form.
     * Inverse of deflate()
     *
     * @param string $data
     * @return Instance
     * @throws InvalidArgumentException if the given payload is unable to be restored into an Instance
     */
    private static function inflate(string $data): Instance
    {
        $unserial = false;

        if ($data) {
            try {
                $unserial = unserialize(gzuncompress($data), ['allowed_classes' => [Instance::class, Param::class,],]);
            } catch (ErrorException $e) {
                // noop
            }
        }
        if (!$unserial instanceof Instance) {
            throw new InvalidArgumentException('Could not inflate and unserialize payload');
        }
        return $unserial;
    }

    /**
     * @param Instance|TriggerInterface $trigger
     * @param string                    $instanceId
     * @return array
     */
    public static function getStorage(Instance|TriggerInterface $trigger, string $instanceId): array
    {
        $compressedData = self::deflate($trigger);
        $hash           = sha1($compressedData);
        $itemKey        = "msg:{$instanceId}:s:$hash";
        return [$itemKey, $compressedData];
    }


    /**
     * Returns the overall namespace for this queue's data
     *
     * @return string
     */
    abstract protected function getNamespace(): string;


    /**
     * Return a pattern containing 2 string placeholders: namespace, and set identifier
     *
     * @return string
     */
    abstract protected function getSetKeyPattern(): string;


    /**
     * Return a pattern containing 3 string placeholders: namespace, msg base-hash, and msg param-hash
     *
     * @return string
     */
    abstract protected function getMessageKeyPattern(): string;
}
