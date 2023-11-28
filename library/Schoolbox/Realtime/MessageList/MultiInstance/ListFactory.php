<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList\MultiInstance;

use Alaress\Async\Queue\QueueInterface;
use Schoolbox\MultiInstance\RedisClientRepository;
use Schoolbox\Realtime\MessageList\Listed;

/**
 * Concrete implementation of a multi-instance aware list factory.
 */
class ListFactory implements IListFactory
{
    /**
     * Constructor
     *
     * @param RedisClientRepository $redisClientRepository Used for fetching the Redis client for a given instance.
     */
    public function __construct(private RedisClientRepository $redisClientRepository)
    {
    }

    /**
     * @inheritdoc
     */
    public function listed(string $instanceId, int $userId): QueueInterface
    {
        return new Listed($this->redisClientRepository->fetchForInstance($instanceId), $instanceId, $userId);
    }
}
