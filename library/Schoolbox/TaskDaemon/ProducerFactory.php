<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon;

use Enqueue\Redis\RedisConnectionFactory;
use Schoolbox\TaskDaemon\Message\HeaderAdapter;
use Schoolbox\TaskDaemon\Request\MessageSerializer;

/**
 * Factory for the task daemon message producer class.
 */
class ProducerFactory
{
    /**
     * Build a message producer factory.
     *
     * @param string $redisUrl
     * @param string $redisQueue
     * @return Producer
     */
    public function build(string $redisUrl, string $redisQueue): Producer
    {
        $contextFactory = new RedisConnectionFactory([
            'host'              => parse_url($redisUrl, PHP_URL_HOST),
            'port'              => parse_url($redisUrl, PHP_URL_PORT),
            'scheme_extensions' => ['predis'],
        ]);
        return new Producer(
            $contextFactory->createContext(),
            $redisQueue,
            new MessageSerializer(),
            new HeaderAdapter(),
        );
    }
}
