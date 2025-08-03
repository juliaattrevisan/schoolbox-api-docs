<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\MultiInstance;

use Alaress\Redis\Async\Client;
use Clue\React\Redis\Factory as ReactRedisClientFactory;
use React\EventLoop\LoopInterface;

/**
 * Repository for asynchronous Redis clients for all instances this node knows
 * about.
 */
class RedisAsyncClientRepository
{
    /**
     * Cached clients, grouped by DSN rather than instance ID.
     *
     * Some instances may share a Redis URL, there's no point creating another
     * identical connection.
     *
     * @var array<string, Client>
     */
    private array $clients = [];

    /**
     * Constructor
     *
     * @param ConfigRepository $configRepository Config repository: required for fetching Redis URLs for instances.
     * @param string           $configKey        The config key which stores the Redis URL.
     *                                           Should be one of the `Schoolbox\Config\ConfigMapper` constants
     *                                           `NAME_REDIS_URL` or `NAME_REDIS_EPHEMERAL_URL`.
     * @param LoopInterface    $loop             Event loop: required for async clients.
     */
    public function __construct(
        private ConfigRepository $configRepository,
        private string $configKey,
        private LoopInterface $loop,
    ) {
    }

    /**
     * Build an async client for this instance.
     *
     * @param string $instanceId
     * @return Client
     */
    public function fetchForInstance(string $instanceId): Client
    {
        $dsn = $this->configRepository
            ->fetchConfigForInstance($instanceId)
            ->get($this->configKey);

        $reactRedisClientFactory = new ReactRedisClientFactory($this->loop);
        $reactRedisClient        = $reactRedisClientFactory->createLazyClient(
            sprintf('%s:%s', parse_url($dsn, PHP_URL_HOST), parse_url($dsn, PHP_URL_PORT)),
        );
        return $this->clients[$dsn] ??= new Client($reactRedisClient);
    }
}
