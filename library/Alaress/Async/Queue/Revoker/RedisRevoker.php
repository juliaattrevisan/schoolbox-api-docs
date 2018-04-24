<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Queue\Revoker;

use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;

/**
 * Revokes messages from Redis.
 */
class RedisRevoker implements RevokerInterface
{
    /**
     * @var ClientInterface
     */
    private $redis;

    /**
     * Constructor
     *
     * @param ClientInterface $redis
     */
    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @inheritdoc
     */
    public function revoke(Instance $instance): bool
    {
        $id = $this->getRedisMsgKey($instance);
        return $this->redis->del([$id]) > 0
            && $this->redis->srem($this->getRedisSetKey(), $id) > 0;
    }

    /**
     * @inheritdoc
     */
    public function revokeAllLike(Instance $instance): int
    {
        $ids = [];
        foreach (new Keyspace($this->redis, $this->getRedisMsgBaseKey($instance)) as $id) {
            $ids[] = $id;
        }

        // Delete from both the key-value store, and the set
        $count = 0;
        if (!empty($ids)) {
            $this->redis->del($ids);
            foreach ($ids as $id) {
                if ($this->redis->srem($this->getRedisSetKey(), $id) > 0) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
