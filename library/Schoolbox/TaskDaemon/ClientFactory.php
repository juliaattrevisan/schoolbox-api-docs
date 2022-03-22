<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon;

use Enqueue\SimpleClient\SimpleClient;

/**
 * Factory for building an Enqueue queue context.
 */
class ClientFactory
{
    /**
     * Build the queue context which the task daemon requires in order to send
     * and receive messages.
     *
     * @param string $redisUrl
     * @return SimpleClient
     */
    public function build(string $redisUrl): SimpleClient
    {
        $client = new SimpleClient([
            'transport' => sprintf(
                'redis+predis://%s:%d',
                parse_url($redisUrl, PHP_URL_HOST),
                parse_url($redisUrl, PHP_URL_PORT),
            ),
            'client' => [
                'prefix'                   => 'enqueue',
                'separator'                => '.',
                'app_name'                 => 'app',
                'router_topic'             => 'router',
                'router_queue'             => 'default',
                'default_queue'            => 'default',
                'redelivered_delay_time'   => 0,
            ],
        ]);
        $client->setupBroker();
        return $client;
    }
}
