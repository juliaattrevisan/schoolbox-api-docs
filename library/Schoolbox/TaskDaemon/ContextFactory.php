<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon;

use Enqueue\Redis\RedisConnectionFactory;
use Interop\Queue\Context;

/**
 * Factory for building an Enqueue queue context.
 */
class ContextFactory
{
    /**
     * Build the queue context which the task daemon requires in order to send
     * and receive messages.
     *
     * @param string $redisUrl
     * @return Context
     */
    public function build(string $redisUrl): Context
    {
        $factory = new RedisConnectionFactory([
            'host'              => parse_url($redisUrl, PHP_URL_HOST),
            'port'              => parse_url($redisUrl, PHP_URL_PORT),
            'scheme_extensions' => ['predis'],
        ]);
        return $factory->createContext();
    }
}
