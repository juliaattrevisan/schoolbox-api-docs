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
     * @param string|null $redisHost
     * @param int|null $redisPort
     * @return Context
     */
    public function build(?string $redisHost, ?int $redisPort): Context
    {
        $factory = new RedisConnectionFactory([
            'host'              => $redisHost,
            'port'              => $redisPort,
            'scheme_extensions' => ['predis'],
        ]);
        return $factory->createContext();
    }
}
